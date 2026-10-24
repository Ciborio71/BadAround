import ftplib,ssl,os,io,json,pathlib
roots=['/httpdocs/wp-content/','/badaround.it/wp-content/','/wp-content/']
checks=['themes/badaround-child/functions.php','plugins/badaround-core/badaround-core.php']
ctx=ssl.create_default_context(cafile='tls/curl-chain.pem')
f=ftplib.FTP_TLS(context=ctx,timeout=60)
f.connect(os.environ['FTP_HOST'],21);f.host=os.environ['FTP_TLS_NAME'];f.login(os.environ['FTP_USER'],os.environ['FTP_PASS']);f.prot_p()
rows=[]
try:
 for root in roots:
  item={'root':root,'files':{}}
  for p in checks:
   try:
    b=io.BytesIO();f.retrbinary('RETR '+root+p,b.write);item['files'][p]={'exists':True,'bytes':len(b.getvalue())}
   except ftplib.error_perm as e:
    item['files'][p]={'exists':False,'error':str(e)}
  rows.append(item)
finally:f.quit()
pathlib.Path('evidence').mkdir(exist_ok=True)
pathlib.Path('evidence/prod-root-probe.json').write_text(json.dumps(rows,indent=2))
print(json.dumps(rows))
