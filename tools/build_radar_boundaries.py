"""Build small display boundaries from ISTAT-derived geojson-italy TopoJSON.
Input is downloaded explicitly; never used to place or infer an event.
"""
import json,sys,math
from pathlib import Path
d=json.load(open(sys.argv[1])); scale=d['transform']['scale']; translate=d['transform']['translate']
def simplify(points,tolerance=.015):
    if len(points)<3:return points
    a,b=points[0],points[-1]; dx,dy=b[0]-a[0],b[1]-a[1]; denom=dx*dx+dy*dy
    distances=[]
    for p in points[1:-1]:
        t=max(0,min(1,((p[0]-a[0])*dx+(p[1]-a[1])*dy)/denom)) if denom else 0
        distances.append(math.hypot(p[0]-a[0]-t*dx,p[1]-a[1]-t*dy))
    if not distances or max(distances)<=tolerance:return [a,b]
    i=distances.index(max(distances))+1
    return simplify(points[:i+1],tolerance)[:-1]+simplify(points[i:],tolerance)
arcs=[]
for arc in d['arcs']:
    x=y=0; out=[]
    for dx,dy in arc:
        x+=dx;y+=dy;out.append([round(x*scale[0]+translate[0],5),round(y*scale[1]+translate[1],5)])
    arcs.append(simplify(out))
def ring(indices):
    out=[]
    for i in indices:
        part=arcs[i] if i>=0 else list(reversed(arcs[~i]))
        out.extend(part if not out else part[1:])
    if out and out[0]!=out[-1]:out.append(out[0])
    return out if len(out)>=4 else []
def feature(g,level):
    polygons=[g['arcs']] if g['type']=='Polygon' else g['arcs']
    coords=[]
    for p in polygons:
        r=[ring(a) for a in p];r=[a for a in r if a]
        if r:coords.append(r)
    p=g['properties']; name=p['reg_name'] if level==0 else p['prov_name']
    return {'type':'Feature','properties':{'name':name,'region':p['reg_name'],'code':int(p['reg_istat_code'] if level==0 else p['prov_istat_code'])},'geometry':{'type':'MultiPolygon','coordinates':coords}}
root=Path(__file__).resolve().parents[1]/'wordpress/themes/badaround-child/assets'
for level,key in enumerate(['regions','provinces']):
    features=[feature(g,level) for g in d['objects'][key]['geometries']]
    (root/('radar-'+key+'.json')).write_text(json.dumps({'type':'FeatureCollection','features':features},ensure_ascii=False,separators=(',',':')))
    print(key,len(features),(root/('radar-'+key+'.json')).stat().st_size)
import gzip,base64
packed={key:json.loads((root/('radar-'+key+'.json')).read_text()) for key in ['regions','provinces']}
encoded=base64.b64encode(gzip.compress(json.dumps(packed,ensure_ascii=False,separators=(',',':')).encode(),mtime=0)).decode()
out=root.parent/'inc/radar-geography.php'
out.write_text("<?php\n/** ISTAT 2026 via geojson-italy ba3347d; CC BY 4.0. Display simplification 0.015 degrees. */\nif (!defined('ABSPATH')) exit;\nreturn json_decode(gzdecode(base64_decode('"+encoded+"')),true);\n")
for key in ['regions','provinces']:(root/('radar-'+key+'.json')).unlink()
