import os,ftplib,ssl,io,subprocess,hashlib
name='page-radar-demo.php'
path='/httpdocs/wp-content/themes/badaround-child/'+name
expected='ee71d6a8499bfb0f6d9a8f5af4fd212c594695f0'
source=subprocess.check_output(['git','show',expected+':wordpress/themes/badaround-child/'+name])
assert subprocess.check_output(['git','rev-parse','--verify',expected]).strip().decode()==expected
ctx=ssl.create_default_context(cafile='tls/chain.pem')
f=ftplib.FTP_TLS(context=ctx,timeout=60)
f.connect(os.environ['FTP_HOST'],21);f.host=os.environ['FTP_TLS_NAME']
f.login(os.environ['FTP_USER'],os.environ['FTP_PASS']);f.prot_p()
def get(p):
 b=io.BytesIO();f.retrbinary('RETR '+p,b.write);return b.getvalue()
try:
 try:
  remote=get(path)
 except ftplib.error_perm as e:
  if not str(e).startswith('550'):raise
  remote=None
 if remote is not None and remote!=source:raise RuntimeError('DRIFT: existing Radar template differs; STOP')
 if remote is None:
  temp=path+'.radar-demo-tmp'
  f.storbinary('STOR '+temp,io.BytesIO(source))
  if get(temp)!=source:raise RuntimeError('Upload verification failed')
  f.rename(temp,path)
 if get(path)!=source:raise RuntimeError('Post-deploy verification failed')
 print('RADAR DEMO FILE VERIFIED',hashlib.sha256(source).hexdigest())
finally:f.quit()
