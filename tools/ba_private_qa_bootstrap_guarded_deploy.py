#!/usr/bin/env python3
"""Guarded Identity private bootstrap update: add one dormant PHP file, replace one.
Existing bootstrap must match the pinned previous GitHub bytes. Rollback is
saved outside webroot. Never change active Core, flags or DB.
"""
from __future__ import annotations
import ftplib,hashlib,io,os,json
from pathlib import Path

HERE=Path(__file__).resolve().parents[1]
ROOT=HERE/'wordpress/plugins/badaround-core/identity-dormant'
BASE='/httpdocs/wp-content/plugins/badaround-core/identity-dormant'
PRIVATE='/wordpress-backups'
CORE='/httpdocs/wp-content/plugins/badaround-core/badaround-core.php'
CORE_EXPECTED='18cb9ad21a63e08449eb85b7cb9233846bb02e2c472d05f118cd7d5764b7d175'
REPLACE='wordpress-private-bootstrap.php'
ADD='wordpress-qa-report-bootstrap.php'
MAX=160000

def read(ftp,path):
    b=io.BytesIO()
    def handle(data):
        if b.tell()+len(data)>MAX:raise RuntimeError('Remote byte bound exceeded')
        b.write(data)
    try:ftp.retrbinary('RETR '+path,handle)
    except ftplib.error_perm as ex:
        if str(ex).startswith('550'):return None
        raise
    return b.getvalue()

def check_bytes(path):
    b=path.read_bytes()
    if len(b)>MAX or not b.startswith(b'<?php'):raise RuntimeError('Invalid source')
    return b

def main():
    mode=os.environ.get('BA_BOOTSTRAP_RELEASE','preflight')
    if mode not in ('preflight','deploy'):raise RuntimeError('Invalid mode')
    old=check_bytes(HERE/'_ba_prior_wordpress_private_bootstrap.php')
    new=check_bytes(ROOT/REPLACE)
    added=check_bytes(ROOT/ADD)
    run=os.environ.get('GITHUB_RUN_ID','manual')
    if not run.isdigit():raise RuntimeError('Invalid run ID')
    ftp=ftplib.FTP_TLS(timeout=70)
    initial_moved=False; added_created=False; target_backup_done=False
    live=BASE+'/'+REPLACE; optional=BASE+'/'+ADD
    temp=BASE+'/.ba_private_qa_'+run+'.tmp'
    safe=BASE+'/.ba_private_qa_prior_'+run+'.bak'
    saved=PRIVATE+'/ba-identity-private-bootstrap-'+run+'.php.bak'
    try:
        ftp.connect(os.environ['FTP_HOST'],21)
        ftp.login(os.environ['FTP_USER'],os.environ['FTP_PASSWORD'])
        ftp.prot_p()
        if hashlib.sha256(read(ftp,CORE) or b'').hexdigest()!=CORE_EXPECTED:
            raise RuntimeError('Production Core baseline SHA mismatch')
        if read(ftp,live)!=old:raise RuntimeError('Existing WordPress bootstrap bytes differ from pinned source')
        if read(ftp,optional) is not None:raise RuntimeError('New bootstrap already exists')
        if read(ftp,saved) is not None:raise RuntimeError('Private backup filename conflict')
        if mode=='deploy':
            ftp.storbinary('STOR '+saved,io.BytesIO(old))
            if read(ftp,saved)!=old:raise RuntimeError('Protected backup integrity failure')
            target_backup_done=True
            ftp.storbinary('STOR '+temp,io.BytesIO(added))
            if read(ftp,temp)!=added:raise RuntimeError('New QA bootstrap temp file corrupt')
            if read(ftp,optional) is not None:raise RuntimeError('QA bootstrap destination appeared unexpectedly')
            ftp.rename(temp,optional)
            added_created=True
            if read(ftp,optional)!=added:raise RuntimeError('New QA bootstrap checksum failed')
            ftp.storbinary('STOR '+temp,io.BytesIO(new))
            if read(ftp,temp)!=new:raise RuntimeError('Private bootstrap temp file corrupt')
            if read(ftp,live)!=old:raise RuntimeError('Live bootstrap changed before swap')
            ftp.rename(live,safe)
            initial_moved=True
            ftp.rename(temp,live)
            if read(ftp,live)!=new:raise RuntimeError('Live bootstrap post-swap mismatch')
            ftp.delete(safe)
            initial_moved=False
        outcome={'mode':mode,'old_core_sha256_verified':True,
                 'existing_private_bootstrap_matched':True,
                 'new_qa_bootstrap_added':mode=='deploy',
                 'private_bootstrap_replaced':mode=='deploy',
                 'old_copy_backed_up_outside_public_root':target_backup_done,
                 'active_core_changed':False,'theme_changed':False,
                 'database_changed':False,'identity_flags_enabled':False}
        Path('ba-private-qa-bootstrap-deploy-evidence.json').write_text(json.dumps(outcome,indent=2)+'\n')
        print('BA_PRIVATE_QA_BOOTSTRAP_'+mode.upper()+'_PASS')
    except Exception:
        if initial_moved:
            try:
                if read(ftp,safe)==old:
                    if read(ftp,live) is not None:ftp.delete(live)
                    ftp.rename(safe,live)
            except ftplib.all_errors:pass
        if added_created:
            try:
                if read(ftp,optional)==added:ftp.delete(optional)
            except ftplib.all_errors:pass
        raise
    finally:
        try:
            if read(ftp,temp) is not None:ftp.delete(temp)
        except ftplib.all_errors:pass
        try:ftp.quit()
        except ftplib.all_errors:ftp.close()

if __name__=='__main__':main()
