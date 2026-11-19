#!/usr/bin/env python3
"""Byte-guarded three-file dormant Identity recovery patch. No active WordPress changes."""
from pathlib import Path
import ftplib, hashlib, io, json, os

BASE='/httpdocs/wp-content/plugins/badaround-core/identity-dormant'
BACK='/wordpress-backups'
CORE='/httpdocs/wp-content/plugins/badaround-core/badaround-core.php'
CORE_SHA='18cb9ad21a63e08449eb85b7cb9233846bb02e2c472d05f118cd7d5764b7d175'
FILES=['pre-intake-recovery-worker.php','wordpress-native-receipt-proof.php','wordpress-pre-intake-recovery-composition.php']
ROOT=Path.cwd()
mode=os.environ.get('BA_ID_RECOVERY_MODE','preflight')
run=os.environ.get('GITHUB_RUN_ID','')
if mode not in ('preflight','deploy') or not run.isdigit():raise RuntimeError('Bad release context')

def source(path):
    b=path.read_bytes()
    if not b.startswith(b'<?php') or len(b)>120000:raise RuntimeError('Invalid PHP source')
    return b
prior={name:source(ROOT/'prior-files'/name) for name in FILES}
updated={name:source(ROOT/'wordpress/plugins/badaround-core/identity-dormant'/name) for name in FILES}

def fetch(ftp,path):
    b=io.BytesIO()
    def cb(chunk):
        if b.tell()+len(chunk)>140000:raise RuntimeError('File exceeded bound')
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
completed=[]
holding=None
try:
    if hashlib.sha256(fetch(ftp,CORE) or b'').hexdigest()!=CORE_SHA:
        raise RuntimeError('Production Core not at approved baseline')
    for name in FILES:
        if fetch(ftp,BASE+'/'+name)!=prior[name]:
            raise RuntimeError('Dormant source differs from previously deployed bytes')
        backup=BACK+'/ba-id-recovery-order-'+run+'-'+name+'.bak'
        if fetch(ftp,backup) is not None:
            raise RuntimeError('Protected backup destination occupied')
    if mode=='deploy':
        for index,name in enumerate(FILES):
            original=BASE+'/'+name
            backup=BACK+'/ba-id-recovery-order-'+run+'-'+name+'.bak'
            temp=BASE+'/.ba_id_patch_'+run+'_'+str(index)+'.part'
            hold=BASE+'/.ba_id_previous_'+run+'_'+str(index)+'.hold'
            if fetch(ftp,original)!=prior[name]:raise RuntimeError('Source changed during deployment')
            ftp.storbinary('STOR '+backup,io.BytesIO(prior[name]))
            if fetch(ftp,backup)!=prior[name]:raise RuntimeError('Private backup integrity failure')
            ftp.storbinary('STOR '+temp,io.BytesIO(updated[name]))
            if fetch(ftp,temp)!=updated[name]:raise RuntimeError('Upload integrity failure')
            if fetch(ftp,original)!=prior[name]:raise RuntimeError('Source changed just before swap')
            ftp.rename(original,hold)
            holding=(name,original,hold)
            ftp.rename(temp,original)
            if fetch(ftp,original)!=updated[name]:raise RuntimeError('Deployed module integrity failure')
            ftp.delete(hold)
            holding=None
            completed.append(name)
    print('BA_ID_RECOVERY_ORDER_'+mode.upper()+'_PASS count='+str(len(completed)))
    Path('ba-id-recovery-order-evidence.json').write_text(json.dumps({
        'mode':mode,'core_verified':True,'previous_versions_verified':True,
        'updated_dormant_files':completed,'active_core_modified':False,
        'database_modified':False,'settings_modified':False,
        'public_identity_enabled':False
    },indent=2)+'\n')
except Exception:
    if holding is not None:
        name,original,hold=holding
        try:
            if fetch(ftp,hold)==prior[name]:
                if fetch(ftp,original) is not None:ftp.delete(original)
                ftp.rename(hold,original)
        except ftplib.all_errors:pass
    for name in reversed(completed):
        try:
            original=BASE+'/'+name
            if fetch(ftp,original)==updated[name]:
                temp=BASE+'/.ba_recovery_'+run+'.part'
                ftp.storbinary('STOR '+temp,io.BytesIO(prior[name]))
                if fetch(ftp,temp)==prior[name]:
                    ftp.delete(original)
                    ftp.rename(temp,original)
        except ftplib.all_errors:pass
    raise
finally:
    try:ftp.quit()
    except ftplib.all_errors:ftp.close()
