'use strict';
const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const { JSDOM } = require('jsdom');
const source = fs.readFileSync('wordpress/themes/badaround-child/assets/js/map.js', 'utf8');
const detail = fs.readFileSync('wordpress/themes/badaround-child/template-parts/single-event-layout.php', 'utf8');
const exact = { id:303,title:'QA exact', event_type:{id:1}, public_geo:{mode:'exact_point',lat:41.8991622,lng:12.4730719,radius_meters:0} };
const approximate = { id:304,title:'QA approximate',event_type:{id:1},public_geo:{mode:'approximate_area',lat:41.89553,lng:12.479214,radius_meters:1000} };
const legacy = { id:290,title:'Legacy',event_type:{id:1},public_geo:{lat:41.866107,lng:12.436241,radius_m:1000} };
const territory = { id:269,title:'Territory',territory:{name:'Roma'},event_type:{id:1},public_geo:null,exact_lat:99,exact_lng:99 };
const svg = marker => decodeURIComponent(marker.icon.url.split(',').slice(1).join(','));
async function render(items, context='full', dataset={}) {
 const dom = new JSDOM('<h1>Public title</h1><section><div data-ba-map></div><div data-ba-map-status hidden></div><div data-ba-map-preview hidden><div data-ba-map-preview-content></div><button data-ba-map-preview-close></button></div></section>', {url:'https://staging.badaround.it/',runScripts:'outside-only'});
 const w=dom.window, node=w.document.querySelector('[data-ba-map]');
 node.dataset.baMapContext=context; Object.assign(node.dataset,dataset);
 const state={markers:[],circles:[],maps:[],overlays:[]};
 class Layer {
  constructor(o){Object.assign(this,o);this.listeners={};}
  addListener(k,fn){(this.listeners[k] ||= []).push(fn);return{remove(){}};}
  trigger(k){(this.listeners[k]||[]).forEach(f=>f());}
  setMap(m){this.map=m;} setIcon(i){this.icon=i;} setZIndex(z){this.zIndex=z;}
 }
 class Marker extends Layer {constructor(o){super(o);state.markers.push(this);}}
 class Circle extends Layer {constructor(o){super(o);state.circles.push(this);} getBounds(){return new Bounds();}}
 class Bounds { extend(){return this;}getCenter(){return {lat:41.9,lng:12.4};}}
 class Map extends Layer {
  constructor(n,o){super(o);this.controls={RIGHT_TOP:{push:el=>n.append(el)}};this.panCalls=[];state.maps.push(this);} panBy(x,y){this.panCalls.push([x,y]);}getZoom(){return this.zoom;}
  setZoom(z){this.zoom=z;}setCenter(p){this.center=p;}fitBounds(){this.zoom=14;}
 }
 class Overlay {
  constructor(){state.overlays.push(this);}setMap(m){this.map=m;}
  getProjection(){return{fromLatLngToDivPixel:p=>({x:p.lat*100,y:p.lng*100})};}
 }
 w.google={maps:{ControlPosition:{RIGHT_TOP:"RIGHT_TOP"},Map,Marker,Circle,LatLngBounds:Bounds,OverlayView:Overlay,LatLng:class{constructor(p){Object.assign(this,p);}},Size:class{constructor(w,h){this.width=w;this.height=h;}},Point:class{constructor(x,y){this.x=x;this.y=y;}},InfoWindow:class{setContent(){}open(){}close(){}}}};
 w.BadAroundMap={hasMaps:true,mapsUrl:'https://maps.googleapis.com',endpoint:'/wp-json/badaround/v1/discovery',categoryByTerm:{1:'spazi-pubblici'}};
 w.fetch=async()=>({ok:true,json:async()=>({items,total:items.length})});
 w.eval(fs.readFileSync("wordpress/themes/badaround-child/assets/js/map-navigation.js","utf8"));
 w.eval(source);
 await new Promise(r=>setTimeout(r,0));
 return {...state,dom,node,close:()=>w.close()};
}
const pointData={baEventId:'303',baEventLat:'41.8991622',baEventLng:'12.4730719',baEventMode:'exact_point',baEventRadius:'0',baEventCategory:'spazi-pubblici'};
test('explicit exact: one body, separate grounded shadow, correct tip, icon/category and no radius', async()=>{
 const r=await render([{...exact,public_geo:{...exact.public_geo,radius_meters:1000}}]);
 assert.equal(r.markers.length,2);assert.equal(r.circles.length,0);
 const [pin,shadow]=r.markers;
 assert.match(svg(pin),/M28 64C/);assert.match(svg(pin),/#0AA184/);assert.match(svg(pin),/translate\(16 15\)/);
 assert.equal(pin.icon.anchor.x,28);assert.equal(pin.icon.anchor.y,64);
 assert.equal(shadow.icon.anchor.x,12);assert.equal(shadow.icon.anchor.y,6);
 assert.deepEqual(pin.position,shadow.position);assert.match(svg(shadow),/<ellipse/);assert.equal(shadow.clickable,false);
 assert.match(pin.title,/Posizione esatta/);
 pin.trigger('mouseover');assert.equal(pin.icon.anchor.y,64);pin.trigger('click');pin.trigger('mouseout');assert.equal(pin.icon.anchor.y,64);
 assert.match(svg(pin),/fill-opacity=".16"/);r.close();
});
test('approximate: geographic meter circle, category badge without pin body and both click targets',async()=>{
 const r=await render([approximate]);const badge=r.markers[0],circle=r.circles[0];
 assert.equal(r.markers.length,1);assert.equal(circle.radius,1000);assert.equal(circle.fillOpacity,.1);
 assert.equal(circle.strokeColor,'#0AA184');assert.equal(circle.clickable,true);
 assert.equal(badge.icon.anchor.x,24);assert.equal(badge.icon.anchor.y,24);assert.doesNotMatch(svg(badge),/M28 64C|M24 58C/);
 assert.match(badge.title,/raggio 1000 metri/);
 circle.trigger('click');assert.equal(badge.zIndex,300);assert.equal(r.dom.window.document.querySelector('[data-ba-map-preview]').hidden,false);
 r.dom.window.document.querySelector('[data-ba-map-preview-close]').click();assert.equal(badge.zIndex,100);
 badge.trigger('click');assert.equal(badge.zIndex,300);r.close();
});
test('territory-only and invalid geo never derive from private fields or fabricate a marker',async()=>{
 const r=await render([territory,{...exact,public_geo:{mode:'unsupported',lat:41,lng:12}},{...exact,public_geo:{lat:null,lng:null}},{...exact,public_geo:{lat:100,lng:12}},{...approximate,public_geo:{...approximate.public_geo,radius_meters:0}}]);
 assert.equal(r.markers.length,0);assert.equal(r.circles.length,0);r.close();
 assert.doesNotMatch(source,/\bexact_lat\b|\bexact_lng\b|exact_address|ba_reports/);
});
test('legacy point, street, area and municipality retain existing generalized renderer and radius',async()=>{
 for(const mode of [undefined,'point','street','area','municipality']){
  const r=await render([{...legacy,public_geo:{...legacy.public_geo,mode}}]);
  assert.equal(r.markers.length,1);assert.match(svg(r.markers[0]),/M24 58C/);assert.doesNotMatch(r.markers[0].title,/Posizione esatta/);
  assert.equal(r.circles[0].radius,1000);r.close();
 }
});
test('Home and Mappa share exact/area semantics and public approved coordinates',async()=>{
 const signatures=[];
 for(const context of ['home','full']){
  const r=await render([exact,approximate,territory,legacy],context);
  signatures.push(JSON.stringify(r.markers.map(m=>({position:m.position,icon:m.icon.url}))));
  assert.equal(r.circles.length,2);assert.equal(r.maps.length,1);r.close();
 }
 assert.equal(signatures[0],signatures[1]);
});
test('detail uses same renderer, single map, approved dataset and meter fit bounds',async()=>{
 const r=await render([], 'detail',pointData);
 assert.equal(r.maps.length,1);assert.equal(r.maps[0].zoom,16);assert.equal(r.markers.length,2);assert.equal(r.circles.length,0);
 assert.match(svg(r.markers[0]),/M28 64C/);assert.match(svg(r.markers[0]),/#0AA184/);r.close();
 const a=await render([], 'detail',{...pointData,baEventId:'304',baEventMode:'approximate_area',baEventRadius:'1000',baEventLat:'41.89553',baEventLng:'12.479214'});
 assert.equal(a.maps.length,1);assert.equal(a.circles[0].radius,1000);assert.equal(a.markers.length,1);assert.doesNotMatch(svg(a.markers[0]),/M28 64C/);a.close();
 const t=await render([], 'detail',{baEventLat:'',baEventLng:''});
 assert.equal(t.maps.length,0);assert.equal(t.markers.length,0);t.close();
 assert.match(detail,/\$public_mode = \$approved_geo\['mode'\]/);assert.match(detail,/Zona territoriale/);
 assert.match(detail,/data-ba-event-mode/);assert.doesNotMatch(detail,/exact_address|exact_lat|exact_lng|ba_reports/);
});
test('cluster redraw hides and restores each projection as a unit without circle/shadow duplication',async()=>{
 const r=await render([exact,approximate]);
 r.overlays[0].draw();
 assert.equal(r.circles.length,1);assert.equal(r.markers.filter(m=>m.map).length,1);
 assert.equal(r.circles[0].map,null);
 const cluster=r.markers.at(-1);cluster.trigger('click');
 r.maps[0].setZoom(17);r.maps[0].trigger('idle');
 assert.equal(cluster.map,null);assert.equal(r.circles[0].map,r.maps[0]);
 assert.equal(r.markers.filter(m=>m.map).length,3);
 for(let i=0;i<5;i++){r.maps[0].setZoom(14);r.maps[0].trigger('idle');r.maps[0].setZoom(17);r.maps[0].trigger('idle');}
 assert.equal(r.circles.length,1);assert.equal(r.markers.filter(m=>m.map).length,3);r.close();
});
test('resolved state uses existing registry, category exclusions remain exclusions, no private DOM',async()=>{
 const r=await render([{...exact,event_status:'resolved'}, {...approximate,event_type:{id:999}}]);
 assert.equal(r.markers.length,2);assert.equal(r.circles.length,0);assert.match(svg(r.markers[0]),/#8292A8/);
 assert.doesNotMatch(r.dom.serialize(),/exact_lat|exact_lng|exact_address/);r.close();
});

test('radius-aware public Home/map/detail circles preserve all five approved meter radii',async()=>{
 for(const radius of [50,100,250,500,1000])for(const context of ['home','full','detail']){
  const r=await render([{...approximate,public_geo:{...approximate.public_geo,radius_meters:radius}}],context,{...pointData,baEventMode:'approximate_area',baEventRadius:String(radius)});
  assert.equal(r.circles.length,1);assert.equal(r.circles[0].radius,radius);r.close();
 }
});
