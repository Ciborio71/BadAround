#!/usr/bin/env python3
"""Deploy exactly two inactive Identity QA source files with a pinned byte guard.
No active Core, database, settings or routes are modified.
"""
import ftplib, hashlib, io, json, os
from pathlib import Path

root=Path.cwd()
src=root/'wordpress/plugins/badaround-core/identity-dormant'
remote='/httpdocs/wp-content/plugins/badaround-core/identity-dormant'
core='/httpdocs/wp-content/plugins/badaround-core/badaround-core.php'
core_sha='18cb9ad21a63e08449eb85b7cb9233846bb02e2c472d05f118cd7d5764b7d175'
rest='wordpress-qa-report-rest.php'
diag='wordpress-qa-readiness.php'
mode=os.environ.get('BA_QA_PATCH_MODE','preflight')
if mode not in ('preflight','deploy'):raise RuntimeError('Bad mode')
run=os.environ.get('GITHUB_RUN_ID','manual')
if not run.isdigit():raise RuntimeError('Bad run ID')
old=(root/'_prior_qa_rest.php').read_bytes()
new=(src/rest).read_bytes()
new_diag=(src/diag).read_bytes()
for b in (old,new,new_diag):
    if not b.startswith(b'<?php') or len(b)>150000:raise RuntimeError('Unexpected source')
def read(ftp,path):
    buf=io.BytesIO()
    def callback(block):
        if buf.tell()+len(block)>150000:raise RuntimeError('File too large')
        buf.write(block)
    try:ftp.retrbinary('RETR '+path,callback)
    except ftplib.error_perm as e:
        if str(e).startswith('550'):return None
        raise
    return buf.getvalue()
ftp=ftplib.FTP_TLS(timeout=60)
ftp.connect(os.environ['FTP_HOST'],21)
ftp.login(os.environ['FTP_USER'],os.environ['FTP_PASSWORD'])
ftp.prot_p()
tmp=remote+'/.ba_qa_secure_'+run+'.upload'
old_tmp=remote+'/.ba_qa_old_'+run+'.hold'
backup='/wordpress-backups/ba-private-qa-rest-'+run+'.php.bak'
added=False
saved=False
swapped=False
try:
    if hashlib.sha256(read(ftp,core) or b'').hexdigest()!=core_sha:
        raise RuntimeError('Core baseline changed')
    if read(ftp,remote+'/'+rest)!=old:raise RuntimeError('Unexpected existing QA REST bytes')
    if read(ftp,remote+'/'+diag) is not None:raise RuntimeError('QA readiness already exists')
    if read(ftp,backup) is not None:raise RuntimeError('Backup name occupied')
    if mode=='deploy':
        ftp.storbinary('STOR '+backup,io.BytesIO(old))
        if read(ftp,backup)!=old:raise RuntimeError('Private backup failed')
        saved=True
        ftp.storbinary('STOR '+tmp,io.BytesIO(new_diag))
        if read(ftp,tmp)!=new_diag:raise RuntimeError('Readiness temporary integrity failed')
        ftp.rename(tmp,remote+'/'+diag)
        added=True
        if read(ftp,remote+'/'+diag)!=new_diag:raise RuntimeError('Readiness final integrity failed')
        ftp.storbinary('STOR '+tmp,io.BytesIO(new))
        if read(ftp,tmp)!=new:raise RuntimeError('REST temporary integrity failed')
        if read(ftp,remote+'/'+rest)!=old:raise RuntimeError('REST changed before rename')
        ftp.rename(remote+'/'+rest,old_tmp)
        swapped=True
        ftp.rename(tmp,remote+'/'+rest)
        if read(ftp,remote+'/'+rest)!=new:raise RuntimeError('REST final integrity failed')
        ftp.delete(old_tmp)
        swapped=False
    evidence={'mode':mode,'core_verified':True,'prior_rest_verified':True,
      'new_module_installed':added,'rest_updated':mode=='deploy',
      'previous_rest_backed_up_privately':saved,
      'active_core_changed':False,'database_changed':False,'flags_enabled':False}
    Path('ba-qa-security-release-evidence.json').write_text(json.dumps(evidence,indent=2))
    print('BA_QA_SECURITY_'+mode.upper()+'_PASS')
except Exception:
    if swapped:
        try:
            if read(ftp,old_tmp)==old:
                if read(ftp,remote+'/'+rest) is not None:ftp.delete(remote+'/'+rest)
                ftp.rename(old_tmp,remote+'/'+rest)
        except ftplib.all_errors:pass
    if added:
        try:
            if read(ftp,remote+'/'+diag)==new_diag:ftp.delete(remote+'/'+diag)
        except ftplib.all_errors:pass
    raise
finally:
    try:
        if read(ftp,tmp) is not None:ftp.delete(tmp)
    except ftplib.all_errors:pass
    try:ftp.quit()
    except ftplib.all_errors:ftp.close()
