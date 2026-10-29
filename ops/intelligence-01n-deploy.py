import ftplib,ssl,os,hashlib,json,pathlib,subprocess,io
root='/httpdocs/wp-content/plugins/badaround-core'
path='includes/class-badaround-intelligence-read-model.php'
prefix='wordpress/plugins/badaround-core/'
e=pathlib.Path('evidence');e.mkdir(exist_ok=True)
head=os.environ['EXPECTED_HEAD'];parent=os.environ['EXPECTED_PARENT']
def gitfile(rev):return subprocess.check_output(['git','show',rev+':'+prefix+path])
old_expected=gitfile(parent);new=gitfile(head)
if pathlib.Path(prefix+path).read_bytes()!=new:raise RuntimeError('Checkout mismatch')
ctx=ssl.create_default_context(cafile='tls/curl-chain.pem')
ftp=ftplib.FTP_TLS(context=ctx,timeout=60)
ftp.connect(os.environ['FTP_HOST'],21);ftp.host=os.environ['FTP_TLS_NAME']
ftp.login(os.environ['FTP_USER'],os.environ['FTP_PASS']);ftp.prot_p()
def get(p):
 out=io.BytesIO();ftp.retrbinary('RETR '+p,out.write);return out.getvalue()
def put(p,data):ftp.storbinary('STOR '+p,io.BytesIO(data))
try:
 remote=root+'/'+path;previous=get(remote)
 status='already-updated' if previous==new else ('baseline' if previous==old_expected else 'DRIFT')
 (e/'preflight.json').write_text(json.dumps({'status':status,
  'remote_sha256':hashlib.sha256(previous).hexdigest(),
  'expected_sha256':hashlib.sha256(old_expected).hexdigest()},indent=2))
 if status=='DRIFT':raise RuntimeError('Unexpected remote drift, stopped without changes')
 if status=='baseline':
  temp=remote+'.intelligence01n-'+head[:8]+'.tmp'
  backup=remote+'.intelligence01n-'+head[:8]+'.bak'
  put(temp,new)
  if get(temp)!=new:raise RuntimeError('Temp upload verification')
  put(backup,previous)
  if get(backup)!=previous:raise RuntimeError('Backup verification')
  ftp.delete(remote)
  try:
   ftp.rename(temp,remote)
   if get(remote)!=new:raise RuntimeError('Post deploy mismatch')
  except Exception:
   put(remote,previous)
   raise
 (e/'result.json').write_text(json.dumps({'status':'success','file':path},indent=2))
 print('INTELLIGENCE-01N SUCCESS')
finally:
 try:ftp.quit()
 except Exception:ftp.close()
