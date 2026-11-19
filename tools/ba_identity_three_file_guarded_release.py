#!/usr/bin/env python3
"""BA Identity guarded three-file production update — dormant files only.

- Fail closed on unexpected existing file or Core baseline drift.
- Verify existing composition is EXACTLY prior deployed version.
- Save prior composition to a private server backup BEFORE rename.
- Only add two missing modules; rollback exact bytes on failure.
- Do not register Identity routes, change flags, touch DB or modify active Core.
"""
from __future__ import annotations
import ftplib,hashlib,io,json,os
from pathlib import Path

ROOT=Path(__file__).resolve().parents[1]
DIR='/httpdocs/wp-content/plugins/badaround-core/identity-dormant'
BACK='/wordpress-backups'
CORE='/httpdocs/wp-content/plugins/badaround-core/badaround-core.php'
CORE_HASH='18cb9ad21a63e08449eb85b7cb9233846bb02e2c472d05f118cd7d5764b7d175'
COMPOSER='wordpress-qa-report-composition.php'
NEW=['locked-canonical-reader.php','wordpress-native-receipt-proof.php']
MAX_SIZE=160_000
def sha(buf):return hashlib.sha256(buf).hexdigest()

def get(ftp,path):
    data=io.BytesIO()
    def add(buf):
        if data.tell()+len(buf)>MAX_SIZE:raise RuntimeError('Size limit exceeded')
        data.write(buf)
    try:ftp.retrbinary('RETR '+path,add)
    except ftplib.error_perm as e:
        if str(e).startswith('550'):return None
        raise
    return data.getvalue()

def main():
    mode=os.environ.get('BA_IDENTITY_DEPLOY_MODE','preflight')
    if mode not in ('preflight','deploy'):raise RuntimeError('Invalid mode')
    old=ROOT/'_ba_identity_predeploy_composition.php'
    if not old.exists():raise RuntimeError('Missing prior deployed version from pinned commit')
    old_bytes=old.read_bytes()
    sources={}
    for name in [COMPOSER]+NEW:
        b=(ROOT/'wordpress/plugins/badaround-core/identity-dormant'/name).read_bytes()
        if not b.startswith(b'<?php') or len(b)>MAX_SIZE:raise RuntimeError('Unexpected source')
        sources[name]=b
    if sources[COMPOSER]==old_bytes:raise RuntimeError('No change to compose')
    run_id=os.environ.get('GITHUB_RUN_ID','manual')
    if not run_id.isdigit():raise RuntimeError('Unsafe run ID')
    ftp=ftplib.FTP_TLS(timeout=70)
    created=[]
    swapped=False
    backup_path=BACK+'/ba-id-private-composer-'+run_id+'.php.bak'
    temp=DIR+'/.ba_identity_release_'+run_id+'.part'
    try:
        ftp.connect(os.environ['FTP_HOST'],21)
        ftp.login(os.environ['FTP_USER'],os.environ['FTP_PASSWORD'])
        ftp.prot_p()
        if sha(get(ftp,CORE) or b'')!=CORE_HASH:
            raise RuntimeError('Live Core baseline mismatch')
        remote_old=get(ftp,DIR+'/'+COMPOSER)
        if remote_old!=old_bytes:raise RuntimeError('Existing composition differs from pinned deployed version')
        if get(ftp,backup_path) is not None:raise RuntimeError('Backup target already occupied')
        for name in NEW:
            if get(ftp,DIR+'/'+name) is not None:raise RuntimeError('New dormant module path already exists')
        if mode=='deploy':
            # Keep the prior server-composer byte copy outside public root.
            ftp.storbinary('STOR '+backup_path,io.BytesIO(remote_old))
            if get(ftp,backup_path)!=remote_old:raise RuntimeError('Private backup mismatch')
            for name in NEW:
                destination=DIR+'/'+name
                ftp.storbinary('STOR '+temp,io.BytesIO(sources[name]))
                if get(ftp,temp)!=sources[name]:raise RuntimeError('Temporary module mismatch')
                if get(ftp,destination) is not None:raise RuntimeError('Destination appeared during upload')
                ftp.rename(temp,destination)
                created.append(name)
                if get(ftp,destination)!=sources[name]:raise RuntimeError('New module mismatch')
            ftp.storbinary('STOR '+temp,io.BytesIO(sources[COMPOSER]))
            if get(ftp,temp)!=sources[COMPOSER]:raise RuntimeError('Temporary composition mismatch')
            if get(ftp,DIR+'/'+COMPOSER)!=old_bytes:raise RuntimeError('Composition changed during upload')
            # Rename-to-backup before rename new ensures prior bytes are recoverable
            # even when connection drops.
            ftp.rename(DIR+'/'+COMPOSER,DIR+'/.ba_identity_prev_'+run_id+'.bak')
            swapped=True
            ftp.rename(temp,DIR+'/'+COMPOSER)
            if get(ftp,DIR+'/'+COMPOSER)!=sources[COMPOSER]:
                raise RuntimeError('Deployed composition integrity mismatch')
            # Delete temporary private dir backup only after successful checksum;
            # the outside-webroot backup is retained.
            ftp.delete(DIR+'/.ba_identity_prev_'+run_id+'.bak')
            swapped=False
        evidence={'mode':mode,'files_checked':[COMPOSER]+NEW,
            'core_match':True,'old_composer_match':True,
            'new_modules':len(created),
            'composition_replaced':mode=='deploy',
            'old_composition_preserved_in_private_backup':mode=='deploy',
            'active_plugin_changed':False,'database_changed':False,'flags_changed':False}
        Path('ba-identity-three-file-release.json').write_text(json.dumps(evidence,indent=2)+'\n')
        print('IDENTITY_THREE_FILE_'+mode.upper()+'_PASS new='+str(len(created))+' active_core_modified=0')
    except Exception:
        if swapped:
            try:
                if get(ftp,DIR+'/.ba_identity_prev_'+run_id+'.bak')==old_bytes:
                    current=get(ftp,DIR+'/'+COMPOSER)
                    if current is not None:ftp.delete(DIR+'/'+COMPOSER)
                    ftp.rename(DIR+'/.ba_identity_prev_'+run_id+'.bak',DIR+'/'+COMPOSER)
            except ftplib.all_errors:pass
        for name in created:
            try:
                if get(ftp,DIR+'/'+name)==sources[name]:ftp.delete(DIR+'/'+name)
            except ftplib.all_errors:pass
        raise
    finally:
        try:
            if get(ftp,temp) is not None:ftp.delete(temp)
        except ftplib.all_errors:pass
        try:ftp.quit()
        except ftplib.all_errors:ftp.close()
if __name__=='__main__':main()
