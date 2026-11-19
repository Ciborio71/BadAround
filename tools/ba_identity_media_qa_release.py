#!/usr/bin/env python3
"""Bounded private media maintenance alignment after full Identity CI."""
from pathlib import Path
import ftplib,importlib.util,os,ssl,json
ROOT=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location('guard',ROOT/'tools/ba_identity_creator_grant_release.py')
guard=importlib.util.module_from_spec(spec);spec.loader.exec_module(guard)
guard.FILES=['wordpress-private-bootstrap.php']
def main():
 prior={p.name:p.read_bytes() for p in (ROOT/'prior-source'/guard.MODULES).iterdir() if p.suffix in ('.php','.js')}
 if len(prior)!=85:raise RuntimeError('Unexpected module inventory')
 candidate={name:(ROOT/guard.MODULES/name).read_bytes() for name in guard.FILES}
 ftp=ftplib.FTP_TLS(context=ssl.create_default_context(cafile=os.environ['FTP_TLS_CA_FILE']),timeout=45)
 try:
  ftp.connect('46.231.24.137',21);ftp.host='hostmld17.aziendeitalia.com'
  ftp.login(os.environ['FTP_USER'],os.environ['FTP_PASSWORD']);ftp.prot_p()
  evidence=guard.release(ftp,prior,candidate,os.environ['GITHUB_RUN_ID'],True)
  print('PRIVATE_MEDIA_MAINTENANCE_ALIGNMENT_PASS')
  print(json.dumps(evidence,sort_keys=True))
 finally:
  try:ftp.quit()
  except ftplib.all_errors:ftp.close()
if __name__=='__main__':main()
