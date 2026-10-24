import ftplib,ssl,os,json,pathlib
ctx=ssl.create_default_context(cafile='tls/curl-chain.pem')
f=ftplib.FTP_TLS(context=ctx,timeout=60)
f.connect(os.environ['FTP_HOST'],21);f.host=os.environ['FTP_TLS_NAME'];f.login(os.environ['FTP_USER'],os.environ['FTP_PASS']);f.prot_p()
paths=['/httpdocs','/httpdocs/wp-content','/httpdocs/wp-content/themes','/httpdocs/wp-content/plugins']
out={}
try:
 for p in paths:
  try: out[p]=f.nlst(p)
  except Exception as e: out[p]=['ERROR '+str(e)]
finally:f.quit()
pathlib.Path('evidence').mkdir(exist_ok=True)
pathlib.Path('evidence/prod-httpdocs-tree.json').write_text(json.dumps(out,indent=2))
print(json.dumps(out))
