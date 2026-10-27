import ftplib, ssl, os, hashlib, json, pathlib, subprocess, io, sys
root='/httpdocs/wp-content/themes/badaround-child'
prefix='wordpress/themes/badaround-child/'
paths=['page-mappa.php']
e=pathlib.Path('evidence');e.mkdir(exist_ok=True)
head=os.environ['EXPECTED_HEAD'];parent=os.environ['EXPECTED_PARENT']
def sha(b):return hashlib.sha256(b).hexdigest()
def gitfile(rev,p):return subprocess.check_output(['git','show',rev+':'+prefix+p])
expected={p:gitfile(parent,p) for p in paths}
new={p:gitfile(head,p) for p in paths}
for p in paths:
 if (pathlib.Path(prefix+p).read_bytes()!=new[p]):raise RuntimeError('checkout mismatch '+p)
ctx=ssl.create_default_context(cafile='tls/curl-chain.pem')
ftp=ftplib.FTP_TLS(context=ctx,timeout=60)
ftp.connect(os.environ['FTP_HOST'],21)
ftp.host=os.environ['FTP_TLS_NAME']
ftp.login(os.environ['FTP_USER'],os.environ['FTP_PASS']);ftp.prot_p()
def get(path):
 out=io.BytesIO();ftp.retrbinary('RETR '+path,out.write);return out.getvalue()
def put(path,data):
 ftp.storbinary('STOR '+path,io.BytesIO(data))
def close():
 try:ftp.quit()
 except Exception:ftp.close()
old={}
report={}
try:
 for p in paths:
  path=root+'/'+p
  old[p]=get(path)
  mode='already-updated' if old[p]==new[p] else ('expected-baseline' if old[p]==expected[p] else 'DRIFT')
  report[p]={'remote_before_sha256':sha(old[p]),'expected_sha256':sha(expected[p]),'new_sha256':sha(new[p]),'status':mode}
 (e/'preflight.json').write_text(json.dumps(report,indent=2))
 if any(x['status']=='DRIFT' for x in report.values()):
  raise RuntimeError('Remote divergence, no files changed')
 # No changes on unknown branch head
 updated=[]
 try:
  for p in paths:
   if old[p]==new[p]:continue
   path=root+'/'+p
   tmp=path+'.homeux02-'+head[:8]+'.tmp'
   put(tmp,new[p])
   if get(tmp)!=new[p]:raise RuntimeError('temporary upload verification failure: '+p)
   # Retain exact remote backup before replacing; no destructive overwrite
   backup=path+'.homeux02-before-'+head[:8]+'.bak'
   put(backup,old[p])
   if get(backup)!=old[p]:raise RuntimeError('backup check failed '+p)
   ftp.delete(path)
   ftp.rename(tmp,path)
   updated.append(p)
  for p in paths:
   if get(root+'/'+p)!=new[p]:raise RuntimeError('post-deploy mismatch '+p)
 except Exception:
  for p in reversed(updated):
   put(root+'/'+p,old[p])
  raise
 finally:
  (e/'updated.json').write_text(json.dumps(updated))
 (e/'result.json').write_text(json.dumps({'status':'success','commit':head,'updated':updated,'files':paths},indent=2))
 print('DEPLOY SUCCESS',updated)
finally:close()
