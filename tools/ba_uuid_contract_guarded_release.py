#!/usr/bin/env python3
"""Replace only the inactive QA intake-contract PHP source with byte-exact guards."""
import ftplib,hashlib,io,os,json
from pathlib import Path
base=Path.cwd()
name='pre-intake-reservation.php'
remote='/httpdocs/wp-content/plugins/badaround-core/identity-dormant/'+name
core='/httpdocs/wp-content/plugins/badaround-core/badaround-core.php'
expected='18cb9ad21a63e08449eb85b7cb9233846bb02e2c472d05f118cd7d5764b7d175'
previous=(base/'_ba_prior_uuid_contract.php').read_bytes()
current=(base/'wordpress/plugins/badaround-core/identity-dormant'/name).read_bytes()
mode=os.getenv('BA_UUID_CONTRACT_MODE','preflight')
run=os.getenv('GITHUB_RUN_ID','')
if mode not in ('preflight','deploy') or not run.isdigit():raise RuntimeError('Invalid deployment context')
for data in (previous,current):
 if len(data)>120000 or not data.startswith(b'<?php'):raise RuntimeError('Invalid PHP source')
def read(ftp,path):
 b=io.BytesIO()
 def cb(chunk):
  if b.tell()+len(chunk)>120000:raise RuntimeError('Unsafe remote size')
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
backup='/wordpress-backups/ba-identity-uuid-contract-'+run+'.php.bak'
old=remote+'.'+run+'.previous'
temp=remote+'.'+run+'.upload'
moved=False;backed=False
try:
 if hashlib.sha256(read(ftp,core) or b'').hexdigest()!=expected:raise RuntimeError('Core baseline mismatch')
 if read(ftp,remote)!=previous:raise RuntimeError('Readiness source differs from pinned version')
 if read(ftp,backup) is not None:raise RuntimeError('Backup path already used')
 if mode=='deploy':
  ftp.storbinary('STOR '+backup,io.BytesIO(previous))
  if read(ftp,backup)!=previous:raise RuntimeError('Private backup verification failed')
  backed=True
  ftp.storbinary('STOR '+temp,io.BytesIO(current))
  if read(ftp,temp)!=current:raise RuntimeError('Temporary upload mismatch')
  if read(ftp,remote)!=previous:raise RuntimeError('Concurrent edit detected')
  ftp.rename(remote,old);moved=True
  ftp.rename(temp,remote)
  if read(ftp,remote)!=current:raise RuntimeError('Post-deploy byte mismatch')
  ftp.delete(old);moved=False
 report={'mode':mode,'core_verified':True,'previous_source_verified':True,
         'private_rollback_copy':backed,'intake-contract_updated':mode=='deploy',
         'active_core_modified':False,'database_modified':False,
         'runtime_flags_modified':False}
 Path('ba-intake-contract-release-evidence.json').write_text(json.dumps(report,indent=2)+'\n')
 print('BA_INTAKE_CONTRACT_'+mode.upper()+'_PASS')
except Exception:
 if moved:
  try:
   if read(ftp,old)==previous:
    if read(ftp,remote) is not None:ftp.delete(remote)
    ftp.rename(old,remote)
  except ftplib.all_errors:pass
 raise
finally:
 try:
  if read(ftp,temp) is not None:ftp.delete(temp)
 except ftplib.all_errors:pass
 try:ftp.quit()
 except ftplib.all_errors:ftp.close()
