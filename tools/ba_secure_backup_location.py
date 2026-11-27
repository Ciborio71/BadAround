#!/usr/bin/env python3
"""Move uniquely identified verified full-site ZIP from webroot to private backups.

Server-side FTP rename only; never overwrite, extract or disclose backup paths.
Verify destination SHA-256 against independently observed archive digest.
"""
from __future__ import annotations
import ftplib,os,hashlib,io,json
from pathlib import Path
SITE='/httpdocs'; PRIVATE='/wordpress-backups'
SIZE=84911583
MTIME='20261105082941'
EXPECTED='578fb1996a57300b65a06a2bfcc31b8ae3ad6362f2437af8151707db56d517e8'

def hash_remote(ftp,path):
    h=hashlib.sha256();size=0
    def callback(block):
        nonlocal size
        size+=len(block)
        if size>SIZE:raise RuntimeError('Unexpected remote archive size')
        h.update(block)
    ftp.retrbinary('RETR '+path,callback,blocksize=65536)
    return size,h.hexdigest()

def main():
    ftp=ftplib.FTP_TLS(timeout=90)
    renamed=False
    source=None;target=None
    try:
        ftp.connect(os.environ['FTP_HOST'],21)
        ftp.login(os.environ['FTP_USER'],os.environ['FTP_PASSWORD'])
        ftp.prot_p()
        candidates=[]
        for name,meta in ftp.mlsd(SITE):
            if meta.get('type')=='file' and int(meta.get('size','0'))==SIZE and meta.get('modify')==MTIME:
                if '/' not in name and '\\' not in name and name.lower().endswith('.zip'):
                    candidates.append(name)
        if len(candidates)!=1:raise RuntimeError('Expected unique public archive not found')
        source=SITE+'/'+candidates[0]
        target=PRIVATE+'/'+candidates[0]
        private_names={name for name,meta in ftp.mlsd(PRIVATE)}
        if candidates[0] in private_names:
            raise RuntimeError('Private destination name already exists')
        n,d=hash_remote(ftp,source)
        if n!=SIZE or d!=EXPECTED:raise RuntimeError('Public source archive digest changed')
        ftp.rename(source,target)
        renamed=True
        n2,d2=hash_remote(ftp,target)
        if n2!=SIZE or d2!=EXPECTED:
            raise RuntimeError('Private archive digest mismatch after move')
        report={'source':'public web root','destination':'private server backup area',
                'server_side_rename_verified':True,'source_archive_removed_from_public_root':True,
                'archive_bytes':n2,'sha256_verified':True,'database_backup_verified':False,
                'other_backups_overwritten':0}
        Path('ba-secure-backup-location.json').write_text(json.dumps(report,indent=2)+'\n')
        print('PUBLIC_BACKUP_RELOCATED_PRIVATE=PASS')
        print('ARCHIVE_SHA256_AFTER_MOVE=PASS')
        print('OTHER_BACKUPS_OVERWRITTEN=0')
    except Exception:
        if renamed and source and target:
            # On an integrity error, leave the copy OUTSIDE public root,
            # never expose wp-config.php by rolling back to public access.
            print('BACKUP_RELOCATION_NEEDS_MANUAL_CHECK')
        raise
    finally:
        try:ftp.quit()
        except ftplib.all_errors:ftp.close()
if __name__=='__main__':main()
