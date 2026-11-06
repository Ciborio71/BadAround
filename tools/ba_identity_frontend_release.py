#!/usr/bin/env python3
"""Guarded private QA wiring. No flags, content, schema or public activation."""
from pathlib import Path
import os,io,ssl,ftplib,hashlib,re,importlib.util
ROOT=Path(__file__).resolve().parents[1]
FILES=['inc/native-report.php','assets/js/native-report/api.js','assets/js/native-report/errors.js','page-segnala-evento.php','single-ba_evento.php']
BASE='/httpdocs/wp-content/themes/badaround-child/'
def main():
 spec=importlib.util.spec_from_file_location('guard',ROOT/'tools/ba_identity_creator_grant_release.py')
 guard=importlib.util.module_from_spec(spec);spec.loader.exec_module(guard)
 run=os.environ['GITHUB_RUN_ID'];assert run.isdigit()
 ftp=ftplib.FTP_TLS(context=ssl.create_default_context(cafile=os.environ['FTP_TLS_CA_FILE']),timeout=40)
 original={};candidate={};modes={};installed=[]
 def read(path):return guard.read(ftp,path)
 def paths(name):
  stem='/wordpress-backups/ba-identity-frontend-'+run+'-'+name.replace('/','_')
  return stem+'.prior',stem+'.part'
 try:
  ftp.connect('46.231.24.137',21);ftp.host='hostmld17.aziendeitalia.com'
  ftp.login(os.environ['FTP_USER'],os.environ['FTP_PASSWORD']);ftp.prot_p()
  if hashlib.sha256(read(guard.CORE) or b'').hexdigest()!=guard.CORE_SHA:raise RuntimeError('Core custody mismatch')
  for name in FILES:
   path=Path('wordpress/themes/badaround-child')/name
   original[name]=(ROOT/'prior-source'/path).read_bytes();candidate[name]=(ROOT/path).read_bytes()
   if read(BASE+name)!=original[name]:raise RuntimeError('Live baseline differs: '+name)
   mode=re.search(r'(?i)unix\.mode=([0-7]{3,4});',ftp.sendcmd('MLST '+BASE+name))
   if mode is None:raise RuntimeError('Cannot verify file permissions')
   modes[name]=mode.group(1)
   if any(read(p) is not None for p in paths(name)):raise RuntimeError('Protected destination occupied')
  print('FRONTEND_BASELINE_PREFLIGHT_PASS')
  for name in FILES:
   backup,temp=paths(name)
   ftp.storbinary('STOR '+backup,io.BytesIO(original[name]));ftp.sendcmd('SITE CHMOD 600 '+backup)
   if read(backup)!=original[name]:raise RuntimeError('Backup mismatch')
   ftp.storbinary('STOR '+temp,io.BytesIO(candidate[name]));ftp.sendcmd('SITE CHMOD '+modes[name]+' '+temp)
   if read(temp)!=candidate[name] or read(BASE+name)!=original[name]:raise RuntimeError('Pre-install integrity mismatch')
  for name in FILES:
   backup,temp=paths(name)
   if read(BASE+name)!=original[name]:raise RuntimeError('Concurrent source change')
   ftp.rename(temp,BASE+name);installed.append(name)
   if read(BASE+name)!=candidate[name]:raise RuntimeError('Installed source differs')
  for name in FILES:
   if read(BASE+name)!=candidate[name]:raise RuntimeError('Post-release integrity failure')
  if hashlib.sha256(read(guard.CORE) or b'').hexdigest()!=guard.CORE_SHA:raise RuntimeError('Core changed')
  print('PRIVATE_QA_FRONTEND_RELEASE_PASS: five files; public runtime unchanged; backups verified')
 except Exception:
  for name in reversed(installed):
   backup,temp=paths(name)
   if read(BASE+name)!=candidate[name] or read(backup)!=original[name]:raise RuntimeError('Rollback prevented by source drift') from None
   ftp.sendcmd('SITE CHMOD '+modes[name]+' '+backup);ftp.rename(backup,BASE+name)
   if read(BASE+name)!=original[name]:raise RuntimeError('Rollback verification failed') from None
  raise
 finally:
  try:ftp.quit()
  except ftplib.all_errors:ftp.close()
if __name__=='__main__':main()
