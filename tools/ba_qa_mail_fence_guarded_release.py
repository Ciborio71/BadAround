#!/usr/bin/env python3
"""Add private QA mail fence and update dormant bootstrap, with byte guards."""
import ftplib,hashlib,io,json,os
from pathlib import Path
root=Path.cwd()
base=root/'wordpress/plugins/badaround-core/identity-dormant'
remote='/httpdocs/wp-content/plugins/badaround-core/identity-dormant'
old_name='wordpress-private-bootstrap.php'
new_name='wordpress-qa-mail-fence.php'
core='/httpdocs/wp-content/plugins/badaround-core/badaround-core.php'
core_hash='18cb9ad21a63e08449eb85b7cb9233846bb02e2c472d05f118cd7d5764b7d175'
mode=os.getenv('BA_MAIL_QA_MODE','preflight')
run=os.getenv('GITHUB_RUN_ID','')
if mode not in ['preflight','deploy'] or not run.isdigit():
 raise RuntimeError('Invalid release context')
old=(root/'_ba_qa_mail_prior_bootstrap.php').read_bytes()
new=(base/old_name).read_bytes()
module=(base/new_name).read_bytes()
if any(not x.startswith(b'<?php') or len(x)>160000 for x in (old,new,module)):
 raise RuntimeError('Unexpected source')
def read(ftp,path):
 b=io.BytesIO()
 def cb(chunk):
  if b.tell()+len(chunk)>160000:raise RuntimeError('Oversized file')
  b.write(chunk)
 try:ftp.retrbinary('RETR '+path,cb)
 except ftplib.error_perm as e:
  if str(e).startswith('550'):return None
  raise
 return b.getvalue()
ftp=ftplib.FTP_TLS(timeout=65)
ftp.connect(os.environ['FTP_HOST'],21)
ftp.login(os.environ['FTP_USER'],os.environ['FTP_PASSWORD'])
ftp.prot_p()
temp=remote+'/.ba_mail_'+run+'.upload'
previous=remote+'/.ba_mail_prior_'+run+'.bak'
backup='/wordpress-backups/ba-qa-mail-bootstrap-'+run+'.php.bak'
added=False
moved=False
backed=False
try:
 if hashlib.sha256(read(ftp,core) or b'').hexdigest()!=core_hash:
  raise RuntimeError('Production Core baseline mismatch')
 if read(ftp,remote+'/'+old_name)!=old:raise RuntimeError('Unexpected prior private bootstrap')
 if read(ftp,remote+'/'+new_name) is not None:raise RuntimeError('Mail fence already present')
 if read(ftp,backup) is not None:raise RuntimeError('Private backup destination occupied')
 if mode=='deploy':
  ftp.storbinary('STOR '+backup,io.BytesIO(old))
  if read(ftp,backup)!=old:raise RuntimeError('Prior bootstrap backup integrity mismatch')
  backed=True
  ftp.storbinary('STOR '+temp,io.BytesIO(module))
  if read(ftp,temp)!=module:raise RuntimeError('Mail fence temporary integrity mismatch')
  ftp.rename(temp,remote+'/'+new_name)
  added=True
  if read(ftp,remote+'/'+new_name)!=module:raise RuntimeError('Mail fence final integrity mismatch')
  ftp.storbinary('STOR '+temp,io.BytesIO(new))
  if read(ftp,temp)!=new:raise RuntimeError('Bootstrap temporary integrity mismatch')
  if read(ftp,remote+'/'+old_name)!=old:raise RuntimeError('Bootstrap changed during deployment')
  ftp.rename(remote+'/'+old_name,previous)
  moved=True
  ftp.rename(temp,remote+'/'+old_name)
  if read(ftp,remote+'/'+old_name)!=new:raise RuntimeError('Bootstrap final integrity mismatch')
  ftp.delete(previous)
  moved=False
 evidence={'mode':mode,'old_core_verified':True,'prior_bootstrap_verified':True,
           'private_backup':backed,'new_mail_fence_added':added,
           'private_bootstrap_updated':mode=='deploy','active_core_modified':False,
           'database_modified':False,'identity_flags_modified':False}
 Path('ba-identity-mail-fence-deploy.json').write_text(json.dumps(evidence,indent=2)+'\n')
 print('BA_IDENTITY_MAIL_FENCE_'+mode.upper()+'_PASS')
except Exception:
 if moved:
  try:
   if read(ftp,previous)==old:
    if read(ftp,remote+'/'+old_name) is not None:ftp.delete(remote+'/'+old_name)
    ftp.rename(previous,remote+'/'+old_name)
  except ftplib.all_errors:pass
 if added:
  try:
   if read(ftp,remote+'/'+new_name)==module:ftp.delete(remote+'/'+new_name)
  except ftplib.all_errors:pass
 raise
finally:
 try:
  if read(ftp,temp) is not None:ftp.delete(temp)
 except ftplib.all_errors:pass
 try:ftp.quit()
 except ftplib.all_errors:ftp.close()
