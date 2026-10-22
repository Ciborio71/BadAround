import ftplib,ssl,os,pathlib,hashlib,io,time,json
ROOT='/staging.badaround.it/'
e=pathlib.Path('evidence');e.mkdir(exist_ok=True)
f=ftplib.FTP_TLS(context=ssl.create_default_context(cafile='tls/curl-chain.pem'),timeout=60)
f.connect(os.environ['FTP_HOST'],21);f.host=os.environ['FTP_TLS_NAME'];f.login(os.environ['FTP_USER'],os.environ['FTP_PASS']);f.prot_p()
name=ROOT+'wp-content/mu-plugins/nf05b-verification.php'
body=pathlib.Path('ops/nf05b-verification.php').read_bytes()
def read():
 b=io.BytesIO();f.retrbinary('RETR '+name,b.write);return b.getvalue()
def absent():
 try:read();return False
 except ftplib.error_perm as ex:
  if not str(ex).startswith('550'):raise
  return True
assert absent(),'QA_PATH_COLLISION'
try:
 tmp=name+'.tmp';f.storbinary('STOR '+tmp,io.BytesIO(body));f.rename(tmp,name);assert read()==body
 (e/'channel-ready.json').write_text(json.dumps(dict(ready=True,route='/nf05b-qa/v1/verify',role='administrator',secret_token_required=True,application_runtime_changed=False)))
 print('QA_CHANNEL_READY',flush=True)
 for i in range(48):
  if absent():break
  time.sleep(10)
finally:
 if not absent():f.delete(name)
 assert absent()
 (e/'channel-cleanup.json').write_text(json.dumps(dict(removed=True,absence_verified=True)))
 print('QA_CHANNEL_REMOVED_VERIFIED',flush=True)
 f.quit()
