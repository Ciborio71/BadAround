#!/usr/bin/env python3
"""Test-gated private QA review release; no flags, migrations or user-data writes."""
from pathlib import Path
import ftplib,importlib.util,os,ssl,io,hashlib,json
ROOT=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location('guard',ROOT/'tools/ba_identity_creator_grant_release.py')
guard=importlib.util.module_from_spec(spec);spec.loader.exec_module(guard)
guard.FILES=['wordpress-community-review.php','community-policy.php','community-gateway.php',
 'wordpress-community-rest-adapter.php','wordpress-composition.php','wordpress-activity-rest.php',
 'wordpress-identity-portal.php','wordpress-comment-form.php','identity-comment-form.js',
 'identity-portal.js','wordpress-private-bootstrap.php']
PUBLICATION=Path('wordpress/plugins/badaround-core/includes/class-badaround-publication-service.php')
LIVE_PUBLICATION='/httpdocs/wp-content/plugins/badaround-core/includes/class-badaround-publication-service.php'
def main():
 prior={p.name:p.read_bytes() for p in (ROOT/'prior-source'/guard.MODULES).iterdir() if p.suffix in ('.php','.js')}
 if len(prior)!=85:raise RuntimeError('Unexpected previous module inventory')
 candidate={name:(ROOT/guard.MODULES/name).read_bytes() for name in guard.FILES}
 old=(ROOT/'prior-source'/PUBLICATION).read_bytes();new=(ROOT/PUBLICATION).read_bytes()
 run=os.environ['GITHUB_RUN_ID']
 if not run.isdigit():raise RuntimeError('Invalid run')
 backup='/wordpress-backups/ba-identity-review-'+run+'.prior'
 temporary='/wordpress-backups/ba-identity-review-'+run+'.part'
 ftp=ftplib.FTP_TLS(context=ssl.create_default_context(cafile=os.environ['FTP_TLS_CA_FILE']),timeout=45)
 changed=False;mode=None;modules_installed=False
 try:
  ftp.connect('46.231.24.137',21);ftp.host='hostmld17.aziendeitalia.com'
  ftp.login(os.environ['FTP_USER'],os.environ['FTP_PASSWORD']);ftp.prot_p()
  if guard.read(ftp,LIVE_PUBLICATION)!=old:raise RuntimeError('Publication Service differs from pinned baseline')
  import re
  match=re.search(r'(?i)unix\.mode=([0-7]{3,4});',ftp.sendcmd('MLST '+LIVE_PUBLICATION))
  if match is None:raise RuntimeError('Cannot verify source mode')
  mode=match.group(1)
  guard.release(ftp,prior,candidate,run,False)
  if any(guard.read(ftp,p) is not None for p in [backup,temporary]):raise RuntimeError('Backup destination occupied')
  ftp.storbinary('STOR '+backup,io.BytesIO(old));ftp.sendcmd('SITE CHMOD 600 '+backup)
  if guard.read(ftp,backup)!=old:raise RuntimeError('Backup mismatch')
  ftp.storbinary('STOR '+temporary,io.BytesIO(new));ftp.sendcmd('SITE CHMOD '+mode+' '+temporary)
  if guard.read(ftp,temporary)!=new or guard.read(ftp,LIVE_PUBLICATION)!=old:raise RuntimeError('Source changed before installation')
  ftp.rename(temporary,LIVE_PUBLICATION);changed=True
  if guard.read(ftp,LIVE_PUBLICATION)!=new:raise RuntimeError('Publication source mismatch')
  evidence=guard.release(ftp,prior,candidate,run,True)
  modules_installed=True
  if guard.read(ftp,LIVE_PUBLICATION)!=new:raise RuntimeError('Publication source drift after release')
  evidence.update(publication_service_before_sha256=hashlib.sha256(old).hexdigest(),
   publication_service_after_sha256=hashlib.sha256(new).hexdigest(),
   private_qa_only=True,new_modules=1,previous_modules=85,current_modules=86)
  print('IDENTITY_REVIEW_DEVELOPMENT_RELEASE_PASS')
  print(json.dumps(evidence,sort_keys=True))
 except Exception:
  if changed:
   if modules_installed or any(guard.read(ftp,guard.BASE+name)!=data for name,data in prior.items()) or guard.read(ftp,guard.BASE+'wordpress-community-review.php') is not None:
    raise RuntimeError('Module reconciliation required before publication rollback') from None
   if guard.read(ftp,LIVE_PUBLICATION)!=new or guard.read(ftp,backup)!=old:
    raise RuntimeError('Publication rollback prevented by concurrent drift') from None
   ftp.sendcmd('SITE CHMOD '+mode+' '+backup);ftp.rename(backup,LIVE_PUBLICATION)
   if guard.read(ftp,LIVE_PUBLICATION)!=old:raise RuntimeError('Publication rollback verification failed') from None
  raise
 finally:
  try:ftp.quit()
  except ftplib.all_errors:ftp.close()
if __name__=='__main__':main()
