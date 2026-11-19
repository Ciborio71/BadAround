#!/usr/bin/env python3
"""Release six dormant QA notice/mail modules. Never edit wp-config or flags."""
from pathlib import Path
import ftplib
import importlib.util
import json
import os
import ssl

ROOT=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location('guarded',ROOT/'tools/ba_identity_creator_grant_release.py')
guarded=importlib.util.module_from_spec(spec)
spec.loader.exec_module(guarded)
guarded.FILES=[
    'wordpress-privacy-notice.php','wordpress-qa-mail-fence.php',
    'wordpress-private-bootstrap.php','wordpress-qa-report-bootstrap.php',
    'wordpress-qa-readiness.php','wordpress-identity-portal.php',
]

def main():
    prior={p.name:p.read_bytes() for p in (ROOT/'prior-source'/guarded.MODULES).iterdir()
        if p.suffix in ('.php','.js')}
    candidate={name:(ROOT/guarded.MODULES/name).read_bytes() for name in guarded.FILES}
    if len(prior)!=85 or any(not b.startswith(b'<?php') for b in candidate.values()):
        raise RuntimeError('Unexpected baseline inventory')
    ftp=ftplib.FTP_TLS(context=ssl.create_default_context(cafile=os.environ['FTP_TLS_CA_FILE']),timeout=45)
    try:
        ftp.connect(os.environ['FTP_HOST'],21)
        ftp.host='hostmld17.aziendeitalia.com'
        ftp.login(os.environ['FTP_USER'],os.environ['FTP_PASSWORD']);ftp.prot_p()
        result=guarded.release(ftp,prior,candidate,os.environ['GITHUB_RUN_ID'],True)
        (ROOT/'qa-notice-release-evidence.json').write_text(json.dumps(result,indent=2)+'\n')
        print('QA_NOTICE_DEPLOY_PASS')
    finally:
        try:ftp.quit()
        except ftplib.all_errors:ftp.close()

if __name__=='__main__':main()
