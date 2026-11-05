#!/usr/bin/env python3
"""Patch one dormant endpoint before the separate private-config step."""
from pathlib import Path
import ftplib
import importlib.util
import json
import os
import ssl

ROOT=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location('guarded',ROOT/'tools/ba_identity_creator_grant_release.py')
guarded=importlib.util.module_from_spec(spec);spec.loader.exec_module(guarded)
guarded.FILES=['wordpress-qa-report-rest.php','wordpress-private-bootstrap.php']

def main():
    prior={p.name:p.read_bytes() for p in (ROOT/'prior-source'/guarded.MODULES).iterdir()
        if p.suffix in ('.php','.js')}
    candidate={name:(ROOT/guarded.MODULES/name).read_bytes() for name in guarded.FILES}
    if len(prior)!=85:raise RuntimeError('Unexpected prior module inventory')
    ftp=ftplib.FTP_TLS(context=ssl.create_default_context(cafile=os.environ['FTP_TLS_CA_FILE']),timeout=45)
    try:
        ftp.connect(os.environ['FTP_HOST'],21);ftp.host='hostmld17.aziendeitalia.com'
        ftp.login(os.environ['FTP_USER'],os.environ['FTP_PASSWORD']);ftp.prot_p()
        evidence=guarded.release(ftp,prior,candidate,os.environ['GITHUB_RUN_ID'],True)
        (ROOT/'qa-activation-module-evidence.json').write_text(json.dumps(evidence,indent=2)+'\n')
        print('QA_REPORT_NOTICE_ALIGNMENT_PASS')
    finally:
        try:ftp.quit()
        except ftplib.all_errors:ftp.close()

if __name__=='__main__':main()
