#!/usr/bin/env python3
"""Create a fresh private production DB backup, without logging or publishing SQL.

Reads production wp-config privately over verified FTPS, runs a consistent,
non-locking MariaDB dump via MYSQL_PWD (never CLI arguments), checks the gzip
stream and uploads outside /httpdocs to an exclusive remote backup filename.
Never mutates the database or modifies any existing backup.
"""
from __future__ import annotations
import ftplib, gzip, hashlib, io, json, os, pathlib, re, ssl, subprocess, tempfile
from datetime import datetime, timezone

REMOTE_BACKUP_DIR='/wordpress-backups'
MAX_SQL_BYTES=2_000_000_000
MAX_GZIP_BYTES=800_000_000

def lookup(config, name):
    pat=r"define\s*\(\s*['\"]"+re.escape(name)+r"['\"]\s*,\s*['\"]([^'\"]*)['\"]\s*\)"
    match=re.search(pat,config)
    if match is None or not match.group(1):
        raise RuntimeError('Missing required production database configuration')
    return match.group(1)

def sha(data):
    return hashlib.sha256(data).hexdigest()

def main():
    host=os.environ['FTP_HOST']; tlsname=os.environ['FTP_TLS_NAME']
    username=os.environ['FTP_USER'];password=os.environ['FTP_PASSWORD']
    ctx=ssl.create_default_context(cafile=os.environ['FTP_TLS_CA_FILE'])
    ftp=ftplib.FTP_TLS(context=ctx,timeout=55)
    remote_new=None
    remote_temp=None
    try:
        ftp.connect(host,21)
        ftp.host=tlsname
        ftp.login(username,password)
        ftp.prot_p()
        config=io.BytesIO()
        ftp.retrbinary('RETR /httpdocs/wp-config.php',config.write)
        cfg=config.getvalue().decode('utf-8')
        dbname=lookup(cfg,'DB_NAME')
        dbuser=lookup(cfg,'DB_USER')
        dbpass=lookup(cfg,'DB_PASSWORD')
        prefix_match=re.search(r"\$table_prefix\s*=\s*['\"]([A-Za-z0-9_]+)['\"]",cfg)
        if not prefix_match:
            raise RuntimeError('Missing production WordPress table prefix')
        prefix=prefix_match.group(1)
        with tempfile.TemporaryDirectory(prefix='ba-db-secure-') as wd:
            archive=pathlib.Path(wd)/'production.sql.gz'
            en=os.environ.copy()
            en['MYSQL_PWD']=dbpass
            command=[
                'mariadb-dump',
                '--protocol=tcp','--connect-timeout=15',
                '--host',host,'--port','3306','--user',dbuser,
                '--single-transaction','--quick','--skip-lock-tables',
                '--hex-blob','--default-character-set=utf8mb4',
                '--skip-comments',
                dbname
            ]
            with gzip.open(archive,'wb',compresslevel=6) as compressed:
                proc=subprocess.Popen(command,stdout=subprocess.PIPE,
                                      stderr=subprocess.DEVNULL,env=en)
                total=0
                assert proc.stdout is not None
                for block in iter(lambda:proc.stdout.read(65536),b''):
                    total+=len(block)
                    if total>MAX_SQL_BYTES:
                        proc.kill()
                        raise RuntimeError('Backup exceeded size bound')
                    compressed.write(block)
                code=proc.wait(timeout=100)
            del cfg,dbpass,config
            if code!=0 or total<8192:
                raise RuntimeError('Database dump did not complete successfully')
            if archive.stat().st_size>MAX_GZIP_BYTES:
                raise RuntimeError('Compressed backup exceeds size bound')
            sqlbytes=0
            create_tables=0
            prefix_found=False
            has_dump_header=False
            with gzip.open(archive,'rb') as stream:
                for line in stream:
                    sqlbytes+=len(line)
                    if sqlbytes>MAX_SQL_BYTES:
                        raise RuntimeError('Backup validation exceeded size bound')
                    if b'CREATE TABLE' in line: create_tables+=1
                    if prefix.encode() in line:prefix_found=True
                    if b'MariaDB dump' in line or b'MySQL dump' in line:has_dump_header=True
            # --skip-comments may omit the dump header; essential checks are
            # table creation and expected production prefix.
            if sqlbytes!=total or create_tables<15 or not prefix_found:
                raise RuntimeError('Backup content integrity validation failed')
            checksum=hashlib.sha256()
            with archive.open('rb') as rd:
                for block in iter(lambda:rd.read(65536),b''):
                    checksum.update(block)
            stamp=datetime.now(timezone.utc).strftime('%Y%m%dT%H%M%SZ')
            remote_new=REMOTE_BACKUP_DIR+'/badaround-prod-db-'+stamp+'.sql.gz'
            remote_temp=REMOTE_BACKUP_DIR+'/.ba-db-'+stamp+'.uploading'
            # Never overwrite a backup. nlst may include full paths.
            entries=set(ftp.nlst(REMOTE_BACKUP_DIR))
            if any(s.endswith('/'+pathlib.PurePosixPath(remote_new).name) for s in entries):
                raise RuntimeError('A backup with this name already exists')
            with archive.open('rb') as rd:
                ftp.storbinary('STOR '+remote_temp,rd,blocksize=65536)
            # Verify the remote stored archive matches exactly. No SQL lines
            # or password are read into a log or GitHub artifact.
            r_hash=hashlib.sha256(); remote_size=0
            def cb(data):
                nonlocal remote_size
                remote_size+=len(data)
                if remote_size>MAX_GZIP_BYTES:raise RuntimeError('Remote backup exceeded limit')
                r_hash.update(data)
            ftp.retrbinary('RETR '+remote_temp,cb)
            if remote_size!=archive.stat().st_size or r_hash.hexdigest()!=checksum.hexdigest():
                raise RuntimeError('Remote DB archive digest mismatch')
            if remote_new in set(ftp.nlst(REMOTE_BACKUP_DIR)):
                raise RuntimeError('Backup final destination already exists')
            ftp.rename(remote_temp,remote_new)
            remote_temp=None
            evidence={'backup_location':'private server directory outside public root',
                'created_utc':stamp,'mode':'consistent readonly SQL dump',
                'sql_bytes':sqlbytes,'archive_bytes':remote_size,
                'archive_sha256':checksum.hexdigest(),
                'create_table_statements':create_tables,
                'gzip_integrity_verified':True,'remote_sha256_verified':True,
                'restore_exercised':False,'existing_backup_overwritten':False,
                'production_database_modified':False}
            pathlib.Path('ba-fresh-db-backup-evidence.json').write_text(
                json.dumps(evidence,indent=2)+'\n',encoding='utf8')
            print('FRESH_DB_BACKUP=PASS')
            print('GZIP_AND_REMOTE_DIGEST=PASS')
            print('RESTORE_TESTED=NO')
            print('EXISTING_BACKUPS_MODIFIED=0; DATABASE_WRITES=0')
    finally:
        if remote_temp:
            try:ftp.delete(remote_temp)
            except ftplib.all_errors:pass
        try:ftp.quit()
        except ftplib.all_errors:ftp.close()

if __name__=='__main__':main()
