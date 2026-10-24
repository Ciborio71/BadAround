import ftplib,ssl,os,io,re,json,pathlib,urllib.request
ctx=ssl.create_default_context(cafile='tls/curl-chain.pem')
f=ftplib.FTP_TLS(context=ctx,timeout=60);f.connect(os.environ['FTP_HOST'],21);f.host=os.environ['FTP_TLS_NAME'];f.login(os.environ['FTP_USER'],os.environ['FTP_PASS']);f.prot_p()
def read(path):
 b=io.BytesIO();f.retrbinary('RETR '+path,b.write);return b.getvalue().decode('utf-8','replace')
out={}
try:
 for label,root in [('production','/httpdocs'),('staging','/staging.badaround.it')]:
  cfg=read(root+'/wp-config.php')
  ver=read(root+'/wp-includes/version.php')
  pref=re.search(r"\$table_prefix\s*=\s*['\"]([^'\"]+)['\"]",cfg)
  dbhost=re.search(r"define\(\s*['\"]DB_HOST['\"]\s*,\s*['\"]([^'\"]+)['\"]",cfg)
  wpver=re.search(r"\$wp_version\s*=\s*['\"]([^'\"]+)['\"]",ver)
  out[label]={'table_prefix':pref.group(1) if pref else None,'db_host':dbhost.group(1) if dbhost else None,'wp_version':wpver.group(1) if wpver else None}
finally:f.quit()
for url in ['https://badaround.it/','https://www.badaround.it/','https://staging.badaround.it/']:
 try:
  req=urllib.request.Request(url,method='HEAD',headers={'User-Agent':'BadAround-release-probe/1.0'})
  with urllib.request.urlopen(req,timeout=20) as r: out[url]={'status':r.status,'final_url':r.geturl()}
 except Exception as e: out[url]={'error':str(e)[:240]}
pathlib.Path('evidence').mkdir(exist_ok=True)
pathlib.Path('evidence/prod-preflight.json').write_text(json.dumps(out,indent=2))
print(json.dumps(out))
