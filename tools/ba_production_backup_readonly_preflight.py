#!/usr/bin/env python3
"""BadAround production backup inventory — read only; no backup contents copied.
A listing alone is NOT restore verification and must not be claimed as such.
"""
from __future__ import annotations
import ftplib
import json
import os
from pathlib import Path
from datetime import datetime,timezone

PATHS=['/wordpress-backups','/httpdocs']
SUFFIXES=('.sql','.sql.gz','.sql.zip','.zip','.tar','.tar.gz','.tgz','.dump','.bak')
def main():
    f=ftplib.FTP_TLS(timeout=35)
    report={'mode':'readonly','site':'badaround.it','restore_test_passed':False,'directories':[]}
    try:
        f.connect(os.environ['FTP_HOST'],21)
        f.login(os.environ['FTP_USER'],os.environ['FTP_PASSWORD'])
        f.prot_p()
        for folder in PATHS:
            entry={'directory':folder,'accessible':False,'backup_candidates':[]}
            try:
                listing=list(f.mlsd(folder))
                entry['accessible']=True
                for name,facts in listing:
                    if facts.get('type')!='file' or not name.lower().endswith(SUFFIXES):
                        continue
                    size=int(facts.get('size','0') or 0)
                    if size<=0: continue
                    entry['backup_candidates'].append({
                        # no source filenames are exposed in the artifact
                        'size_bytes':size,
                        'last_modified_utc':facts.get('modify',''),
                        'database_backup_proven':False,
                        'restore_verified':False,
                    })
                entry['backup_candidates']=entry['backup_candidates'][:30]
            except ftplib.all_errors:
                entry['accessible']=False
            report['directories'].append(entry)
    finally:
        try:f.quit()
        except ftplib.all_errors:f.close()
    Path('ba-production-backup-preflight.json').write_text(
        json.dumps(report,indent=2)+'\n',encoding='utf-8')
    for d in report['directories']:
        print('BACKUP_INVENTORY directory='+d['directory']
              +' accessible='+str(d['accessible'])
              +' candidate_files='+str(len(d['backup_candidates'])))
    print('RESTORE_VERIFIED=NO')
    print('NO_SERVER_FILE_OR_DATABASE_WRITE=YES')
if __name__=='__main__':main()
