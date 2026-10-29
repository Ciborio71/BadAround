import ftplib,ssl,os,pathlib,subprocess,io,hashlib,json
mode=os.environ['PROBE_MODE']
remote='/httpdocs/wp-content/mu-plugins/ba-intelligence-01p-probe.php'
data=pathlib.Path('ops/intelligence-01p-runtime-probe.php').read_bytes()
ctx=ssl.create_default_context(cafile='tls/curl-chain.pem')
ftp=ftplib.FTP_TLS(context=ctx,timeout=60)
ftp.connect(os.environ['FTP_HOST'],21);ftp.host=os.environ['FTP_TLS_NAME']
ftp.login(os.environ['FTP_USER'],os.environ['FTP_PASS']);ftp.prot_p()
def get(p):
 b=io.BytesIO()
 try:ftp.retrbinary('RETR '+p,b.write)
 except ftplib.error_perm as e:
  if str(e).startswith('550'):return None
  raise
 return b.getvalue()
try:
 if mode=='install':
  try:ftp.mkd('/httpdocs/wp-content/mu-plugins')
  except ftplib.error_perm as e:
   if not str(e).startswith('550'):raise
  old=get(remote)
  if old is not None and old!=data:raise RuntimeError('Existing file drift; no overwrite')
  if old is None:
   ftp.storbinary('STOR '+remote,io.BytesIO(data))
  if get(remote)!=data:raise RuntimeError('Probe install mismatch')
 elif mode=='remove':
  old=get(remote)
  if old is not None:
   if old!=data:raise RuntimeError('Probe changed; preserve and stop')
   ftp.delete(remote)
  if get(remote) is not None:raise RuntimeError('Probe removal not verified')
 else:raise RuntimeError('Invalid probe mode')
 print('PROBE '+mode+' PASS')
finally:
 try:ftp.quit()
 except Exception:ftp.close()
