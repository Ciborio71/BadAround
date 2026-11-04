#!/usr/bin/env python3
"""BA Identity dormant production deploy; add-only, fail-closed FTPS.

No existing remote byte may be overwritten, edited, or deleted.
No WordPress runtime activation, configuration or database mutation.
"""
from __future__ import annotations
import ftplib
import hashlib
import io
import json
import os
from pathlib import Path

ROOT=Path(__file__).resolve().parents[1]
RELATIVE='wordpress/plugins/badaround-core/identity-dormant'
REMOTE='/httpdocs/wp-content/plugins/badaround-core/identity-dormant'
CORE='/httpdocs/wp-content/plugins/badaround-core/badaround-core.php'
CORE_SHA='18cb9ad21a63e08449eb85b7cb9233846bb02e2c472d05f118cd7d5764b7d175'
FILES=[
'native-report-receipt.php',
'native-ownership-coordinator.php',
'durable-ownership-handoff.php',
'recovery-store-bridge.php',
'ownership-recovery-reconciler.php',
'recovery-owner-bridge.php',
'native-intake-gate.php',
'native-intake-orchestrator.php',
'pre-intake-reservation.php',
'mysql-pre-intake-store.php',
'pre-reserved-native-intake.php',
'pre-intake-recovery-worker.php',
'pre-intake-recovery-store-bridge.php',
'pre-intake-schema-candidate.php',
'wordpress-pre-intake-recovery-composition.php',
'wordpress-qa-report-transport.php',
'wordpress-qa-report-rest.php',
'wordpress-qa-report-composition.php',
]
assert len(FILES)==len(set(FILES))==18
MAX_FILE=150_000

def read(ftp,path):
    b=io.BytesIO()
    def cb(chunk):
        if b.tell()+len(chunk)>MAX_FILE: raise RuntimeError('Remote file exceeds limit')
        b.write(chunk)
    try:ftp.retrbinary('RETR '+path,cb)
    except ftplib.error_perm as e:
        if str(e).startswith('550'):return None
        raise
    return b.getvalue()

def digest(data):return hashlib.sha256(data).hexdigest()

def main():
    mode=os.environ.get('BA_DEPLOY_MODE','preflight')
    if mode not in ('preflight','deploy'):raise RuntimeError('Invalid deployment mode')
    env={k:os.environ[k] for k in ('FTP_HOST','FTP_USER','FTP_PASSWORD')}
    if not all(env.values()):raise RuntimeError('Missing FTP credentials')
    inventory={}
    for name in FILES:
        if '/' in name or not name.endswith('.php') or name.startswith('.'):
            raise RuntimeError('Unsafe file name')
        p=ROOT/RELATIVE/name
        b=p.read_bytes()
        if len(b)>MAX_FILE or not b.startswith(b'<?php'):raise RuntimeError('Unsafe source')
        inventory[name]=b
    ftp=ftplib.FTP_TLS(timeout=35)
    created=[]
    try:
        ftp.connect(env['FTP_HOST'],21)
        ftp.login(env['FTP_USER'],env['FTP_PASSWORD'])
        ftp.prot_p()
        actual=read(ftp,CORE)
        if actual is None or digest(actual)!=CORE_SHA:
            raise RuntimeError('Production Core does not match approved SHA256 baseline')
        ftp.cwd(REMOTE)
        plan=[]
        for name,body in inventory.items():
            existing=read(ftp,REMOTE+'/'+name)
            status='new' if existing is None else ('identical' if existing==body else 'CONFLICT')
            if status=='CONFLICT':raise RuntimeError('Remote source conflict: '+name)
            plan.append({'file':name,'sha256':digest(body),'status':status})
        if mode=='deploy':
            for index,row in enumerate(plan):
                if row['status']=='identical':continue
                name=row['file'];body=inventory[name]
                # Never upload over an existing target. A temporary file is
                # uploaded and verified before its single final rename.
                if read(ftp,REMOTE+'/'+name) is not None:
                    raise RuntimeError('Race: target appeared before deployment')
                tmp='.ba_identity_tmp_'+os.environ.get('GITHUB_RUN_ID','manual')+'_'+str(index)+'.part'
                try:
                    ftp.storbinary('STOR '+REMOTE+'/'+tmp,io.BytesIO(body))
                    if read(ftp,REMOTE+'/'+tmp)!=body:
                        raise RuntimeError('Temporary upload integrity failure')
                    if read(ftp,REMOTE+'/'+name) is not None:
                        raise RuntimeError('Race: target appeared before rename')
                    ftp.rename(REMOTE+'/'+tmp,REMOTE+'/'+name)
                    created.append(name)
                    if read(ftp,REMOTE+'/'+name)!=body:
                        raise RuntimeError('Post-rename integrity mismatch')
                finally:
                    try:ftp.delete(REMOTE+'/'+tmp)
                    except ftplib.all_errors:pass
        evidence={'mode':mode,'production_core_sha256':CORE_SHA,
          'files':plan,'new_files_created':created,
          'public_routes_enabled':False,'existing_files_overwritten':0}
        Path('ba-identity-dormant-deploy-evidence.json').write_text(
          json.dumps(evidence,indent=2)+'\n',encoding='utf8')
        print('BA_IDENTITY_DORMANT_'+mode.upper()+'_PASS count='+str(len(FILES))+
              ' created='+str(len(created))+' existing_overwritten=0')
    except Exception:
        # Rollback ONLY files created by this invocation. Never delete any
        # pre-existing remote path; no modifications to legacy resources.
        for name in reversed(created):
            try:
                if read(ftp,REMOTE+'/'+name)==inventory[name]:
                    ftp.delete(REMOTE+'/'+name)
            except ftplib.all_errors:pass
        raise
    finally:
        try:ftp.quit()
        except ftplib.all_errors:ftp.close()

if __name__=='__main__':main()
