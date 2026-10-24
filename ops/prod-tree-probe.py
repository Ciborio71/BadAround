import ftplib,ssl,os,json,pathlib
ctx=ssl.create_default_context(cafile='tls/curl-chain.pem')
f=ftplib.FTP_TLS(context=ctx,timeout=60)
f.connect(os.environ['FTP_HOST'],21);f.host=os.environ['FTP_TLS_NAME'];f.login(os.environ['FTP_USER'],os.environ['FTP_PASS']);f.prot_p()
out={}
try:
 for root in ['/','.']:
  try:
   out[root]=f.nlst(root)
  except Exception as e:
   out[root]=['ERROR '+str(e)]
finally:f.quit()
pathlib.Path('evidence').mkdir(exist_ok=True)
pathlib.Path('evidence/prod-tree-probe.json').write_text(json.dumps(out,indent=2))
print(json.dumps(out))
