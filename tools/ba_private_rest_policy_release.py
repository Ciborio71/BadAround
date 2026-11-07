#!/usr/bin/env python3
"""BA-IDENTITY: guarded deployment of one dormant REST source file.

Fail closed on any drift. Keep exact prior version in private backup.
No active plugin, DB, flags, routes or theme files changed.
"""
import ftplib,hashlib,io,json,os
from pathlib import Path

repo=Path.cwd()
module='wordpress-qa-report-rest.php'
target='/httpdocs/wp-content/plugins/badaround-core/identity-dormant/'+module
core='/httpdocs/wp-content/plugins/badaround-core/badaround-core.php'
expected_core='18cb9ad21a63e08449eb85b7cb9233846bb02e2c472d05f118cd7d5764b7d175'
prior=(repo/'_ba_prior_private_report_rest.php').read_bytes()
updated=(repo/'wordpress/plugins/badaround-core/identity-dormant'/module).read_bytes()
mode=os.getenv('BA_PRIVATE_REST_MODE','preflight')
run=os.getenv('GITHUB_RUN_ID','')
if mode not in ('preflight','deploy') or not run.isdecimal():raise RuntimeError('Invalid release context')
if any(not x.startswith(b'<?php') or len(x)>120000 for x in (prior,updated)):
    raise RuntimeError('Unexpected source bytes')
def get(ftp,path):
    b=io.BytesIO()
    def cb(chunk):
        if b.tell()+len(chunk)>150000:raise RuntimeError('Remote file too large')
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
temp=target+'.'+run+'.part'
hold=target+'.'+run+'.previous'
backup='/wordpress-backups/ba-qa-private-report-rest-'+run+'.php.bak'
held=False;backup_verified=False
try:
    if hashlib.sha256(get(ftp,core) or b'').hexdigest()!=expected_core:
        raise RuntimeError('Production Core hash mismatch')
    if get(ftp,target)!=prior:raise RuntimeError('Private QA REST source drift')
    if get(ftp,backup) is not None:raise RuntimeError('Private backup target occupied')
    if mode=='deploy':
        ftp.storbinary('STOR '+backup,io.BytesIO(prior))
        if get(ftp,backup)!=prior:raise RuntimeError('Private rollback backup failed')
        backup_verified=True
        ftp.storbinary('STOR '+temp,io.BytesIO(updated))
        if get(ftp,temp)!=updated:raise RuntimeError('Upload integrity mismatch')
        if get(ftp,target)!=prior:raise RuntimeError('Concurrent source modification')
        ftp.rename(target,hold)
        held=True
        ftp.rename(temp,target)
        if get(ftp,target)!=updated:raise RuntimeError('Post-swap integrity mismatch')
        ftp.delete(hold)
        held=False
    Path('ba-private-rest-policy-release.json').write_text(json.dumps({
        'mode':mode,'core_sha256_match':True,
        'pinned_prior_source_match':True,
        'private_rollback_verified':backup_verified,
        'qa_rest_source_updated':mode=='deploy',
        'active_code_or_flags_modified':False,
        'database_modified':False
    },indent=2)+'\n')
    print('BA_PRIVATE_REST_POLICY_'+mode.upper()+'_PASS')
except Exception:
    if held:
        try:
            if get(ftp,hold)==prior:
                if get(ftp,target) is not None:ftp.delete(target)
                ftp.rename(hold,target)
        except ftplib.all_errors:pass
    raise
finally:
    try:
        if get(ftp,temp) is not None:ftp.delete(temp)
    except ftplib.all_errors:pass
    try:ftp.quit()
    except ftplib.all_errors:ftp.close()
