#!/usr/bin/env python3
"""Verify user-generated complete WordPress backup in public root, read-only.

Reads only one recent expected-size archive from /httpdocs. ZIP archive is
streamed to runner scratch disk and deleted; no contents or path names are
uploaded to artifacts or printed. Checks ZIP CRC, counts, and site+DB payload.
"""
from __future__ import annotations
import ftplib
import hashlib
import json
import os
import tempfile
import zipfile
from pathlib import Path

ROOT='/httpdocs'
EXPECTED_BYTES=84911583
EXPECTED_MTIME='20261105082941'
MAX_UNCOMPRESSED=1_800_000_000
MAX_ENTRIES=18000

def main():
    ftp=ftplib.FTP_TLS(timeout=90)
    result={'site':'badaround.it','mode':'read-only','archive_verified':False,
            'database_content_verified':False,'site_files_verified':False,
            'restore_tested':False,'server_files_changed':0}
    try:
        ftp.connect(os.environ['FTP_HOST'],21)
        ftp.login(os.environ['FTP_USER'],os.environ['FTP_PASSWORD'])
        ftp.prot_p()
        matches=[]
        for name,facts in ftp.mlsd(ROOT):
            if facts.get('type')!='file':continue
            if int(facts.get('size','0'))==EXPECTED_BYTES and facts.get('modify')==EXPECTED_MTIME:
                if '/' not in name and '\\' not in name and name.lower().endswith(('.zip','.tar.gz','.tgz','.sql.gz','.gz')):
                    matches.append(name)
        if len(matches)!=1:
            raise RuntimeError('Expected unique backup not located')
        name=matches[0]
        suffix='.zip' if name.lower().endswith('.zip') else '.other'
        if suffix!='.zip':
            raise RuntimeError('Archive is not ZIP; needs format-specific verification')
        with tempfile.TemporaryDirectory(prefix='badaround-backup-check-') as td:
            archive=Path(td)/'backup.zip'
            size=0
            digest=hashlib.sha256()
            with archive.open('wb') as out:
                def callback(part):
                    nonlocal size
                    size+=len(part)
                    if size>EXPECTED_BYTES:
                        raise RuntimeError('Archive exceeds expected size')
                    digest.update(part);out.write(part)
                ftp.retrbinary('RETR '+ROOT+'/'+name,callback,blocksize=65536)
            if size!=EXPECTED_BYTES:raise RuntimeError('Archive size mismatch')
            with zipfile.ZipFile(archive,'r') as z:
                entries=z.infolist()
                total_uncompressed=sum(x.file_size for x in entries)
                if not entries or len(entries)>MAX_ENTRIES or total_uncompressed>MAX_UNCOMPRESSED:
                    raise RuntimeError('Archive entry count/size unsafe')
                names=[x.filename.lower() for x in entries if not x.is_dir()]
                contains_core=any(n.endswith('/wp-includes/version.php') or n=='wp-includes/version.php' for n in names)
                contains_config=any(n.endswith('/wp-config.php') or n=='wp-config.php' for n in names)
                contains_plugin=any('/wp-content/plugins/badaround-core/' in '/'+n for n in names)
                sqlfiles=[x for x in entries if not x.is_dir()
                          and x.filename.lower().endswith(('.sql','.sql.gz','.sql.zip','.dump'))]
                # Generic complete-site archives may place the SQL separately;
                # no database proof when the archive has no dump entry.
                crc_bad=z.testzip()
                if crc_bad is not None:raise RuntimeError('ZIP CRC integrity check failed')
                result.update({
                  'archive_verified':True,
                  'zip_crc_verified':True,
                  'archive_bytes':size,
                  'archive_sha256':digest.hexdigest(),
                  'entries':len(entries),
                  'uncompressed_bytes':total_uncompressed,
                  'site_files_verified':contains_core and contains_config and contains_plugin,
                  'wp_config_present':contains_config,
                  'sql_dump_entries':len(sqlfiles),
                  'database_content_verified':False,
                })
                if sqlfiles:
                    # Verify SQL payload has actual CREATE TABLE and known WordPress
                    # naming pattern; never print or persist raw SQL.
                    for entry in sqlfiles[:5]:
                        if entry.file_size>300_000_000:continue
                        with z.open(entry) as stream:
                            head=stream.read(min(entry.file_size,2_500_000))
                        if b'CREATE TABLE' in head and b'cixe6qoXf_' in head:
                            result['database_content_verified']=True
                            break
        Path('ba-current-backup-validation.json').write_text(
            json.dumps(result,indent=2)+'\n',encoding='utf8')
        print('USER_ARCHIVE_ZIP_CRC='+('PASS' if result['archive_verified'] else 'FAIL'))
        print('SITE_FILES_PROVEN='+('YES' if result['site_files_verified'] else 'NO'))
        print('DB_DUMP_PROVEN='+('YES' if result['database_content_verified'] else 'NO'))
        print('RESTORE_EXERCISED=NO; SERVER_WRITES=0')
    finally:
        try:ftp.quit()
        except ftplib.all_errors:ftp.close()

if __name__=='__main__':main()
