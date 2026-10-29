import ftplib, ssl, os, hashlib, json, pathlib, subprocess, io
root='/httpdocs/wp-content/plugins/badaround-core'
prefix='wordpress/plugins/badaround-core/'
files=['badaround-core.php','includes/class-badaround-intelligence-read-model.php']
e=pathlib.Path('evidence');e.mkdir(exist_ok=True)
head=os.environ['EXPECTED_HEAD'];parent=os.environ['EXPECTED_PARENT']
def sha(b):return hashlib.sha256(b).hexdigest()
def gitfile(rev,p):return subprocess.check_output(['git','show',rev+':'+prefix+p])
expected={'badaround-core.php':gitfile(parent,'badaround-core.php'),
          'includes/class-badaround-intelligence-read-model.php':None}
new={p:gitfile(head,p) for p in files}
for p in files:
 if pathlib.Path(prefix+p).read_bytes()!=new[p]:raise RuntimeError('checkout mismatch '+p)
ctx=ssl.create_default_context(cafile='tls/curl-chain.pem')
ftp=ftplib.FTP_TLS(context=ctx,timeout=60)
ftp.connect(os.environ['FTP_HOST'],21);ftp.host=os.environ['FTP_TLS_NAME']
ftp.login(os.environ['FTP_USER'],os.environ['FTP_PASS']);ftp.prot_p()
def read(path):
 result=io.BytesIO()
 try:ftp.retrbinary('RETR '+path,result.write)
 except ftplib.error_perm as ex:
  if str(ex).startswith('550'):return None
  raise
 return result.getvalue()
def put(path,b):ftp.storbinary('STOR '+path,io.BytesIO(b))
try:
 old={p:read(root+'/'+p) for p in files}
 status={}
 for p in files:
  if old[p]==new[p]:status[p]='already-updated'
  elif old[p]==expected[p]:status[p]='baseline'
  else:status[p]='DRIFT'
 (e/'preflight.json').write_text(json.dumps({'status':status,'remote_sha':{p:sha(v) if v is not None else None for p,v in old.items()}},indent=2))
 if 'DRIFT' in status.values():raise RuntimeError('Unexpected remote drift; no files modified')
 updated=[]
 try:
  for p in files:
   if status[p]=='already-updated':continue
   remote=root+'/'+p
   tmp=remote+'.intelligence01m-'+head[:8]+'.tmp'
   put(tmp,new[p])
   if read(tmp)!=new[p]:raise RuntimeError('Temporary upload failed: '+p)
   if old[p] is not None:
    backup=remote+'.intelligence01m-'+head[:8]+'.bak'
    put(backup,old[p])
    if read(backup)!=old[p]:raise RuntimeError('Backup verify failed: '+p)
    ftp.delete(remote)
   ftp.rename(tmp,remote)
   updated.append(p)
  for p in files:
   if read(root+'/'+p)!=new[p]:raise RuntimeError('Remote verification failed: '+p)
 except Exception:
  for p in reversed(updated):
   remote=root+'/'+p
   if old[p] is None:
    try:ftp.delete(remote)
    except ftplib.error_perm:pass
   else:put(remote,old[p])
  raise
 (e/'result.json').write_text(json.dumps({'status':'success','updated':updated,'expected_head':head},indent=2))
 print('INTELLIGENCE-01M DEPLOY SUCCESS',updated)
finally:
 try:ftp.quit()
 except Exception:ftp.close()
