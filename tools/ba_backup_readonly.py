#!/usr/bin/env python3
"""Read-only inspection of the user-named backup. No live writes or public artifacts."""
import ftplib, ssl, os, tempfile, zipfile, hashlib, json, posixpath, re, io, subprocess
from pathlib import Path
TARGET='directory-completa-bak.zip'
ROOTS=('/', '/httpdocs', '/wordpress-backups', '/httpdocs/httpdocs')
MAX_BYTES=2*1024**3
def inspect_archive(path):
    with zipfile.ZipFile(path) as z:
        entries=z.infolist()
        if len(entries)>200000 or sum(e.file_size for e in entries)>10*1024**3:
            return {'zip_integrity':'NOT_CHECKED_SIZE_LIMIT'}
        if any(e.flag_bits&1 for e in entries):
            return {'zip_integrity':'PASSWORD_REQUIRED'}
        bad=z.testzip()
        names=['/'+e.filename.replace('\\','/').lstrip('/') for e in entries]
        return {'zip_integrity':'PASS' if bad is None else 'FAIL',
                'entries':len(entries),
                'wordpress_config_present':any(n.endswith('/wp-config.php') for n in names),
                'private_media_directory_present':any('/badaround-private-media/' in n for n in names),
                'identity_private_config_present':any(n.endswith('/ba-identity-private-qa.php') for n in names),
                'sql_members':sum(n.lower().endswith(('.sql','.sql.gz')) for n in names)}

def private_supplement(ftp):
    path='/ba-identity-private-qa.php';buf=io.BytesIO()
    ftp.retrbinary('RETR '+path,buf.write)
    source=buf.getvalue()
    if not source or len(source)>100000:raise RuntimeError('PRIVATE_CONFIG_SIZE')
    run=os.environ['GITHUB_RUN_ID']
    if not run.isdigit():raise RuntimeError('INVALID_RUN')
    target='/wordpress-backups/ba-pre-beta-private-config-'+run+'.zip'
    try:
        ftp.sendcmd('MLST '+target)
        raise RuntimeError('BACKUP_DESTINATION_OCCUPIED')
    except ftplib.error_perm as e:
        if not str(e).startswith('550'):raise
    archive=io.BytesIO()
    with zipfile.ZipFile(archive,'w',compression=zipfile.ZIP_DEFLATED) as z:
        info=zipfile.ZipInfo('ba-identity-private-qa.php')
        info.external_attr=0o600<<16;z.writestr(info,source)
    data=archive.getvalue()
    ftp.storbinary('STOR '+target,io.BytesIO(data));ftp.sendcmd('SITE CHMOD 600 '+target)
    verify=io.BytesIO();ftp.retrbinary('RETR '+target,verify.write)
    mode=re.search(r'(?i)unix\.mode=([0-7]{3,4});',ftp.sendcmd('MLST '+target))
    if verify.getvalue()!=data or not mode or int(mode.group(1),8)!=0o600:raise RuntimeError('PRIVATE_BACKUP_VERIFICATION')
    current=io.BytesIO();ftp.retrbinary('RETR '+path,current.write)
    if current.getvalue()!=source:raise RuntimeError('PRIVATE_SOURCE_CHANGED')
    with zipfile.ZipFile(io.BytesIO(data)) as z:
        if z.testzip() is not None or z.read('ba-identity-private-qa.php')!=source:raise RuntimeError('PRIVATE_BACKUP_CRC')
    return {'private_supplement_verified':True,'private_supplement_file':'ba-pre-beta-private-config-'+run+'.zip',
            'private_supplement_sha256':hashlib.sha256(data).hexdigest(),'private_supplement_mode':'0600',
            'private_source_unchanged':True}

def inspect_sql(path):
    with zipfile.ZipFile(path) as z:
        members=[i for i in z.infolist() if i.filename.lower().endswith('.sql')]
        result={'sql_plain_members':len(members),'database_restore_tested':False}
        if len(members)!=1:return result
        member=members[0]
        result['sql_size_bytes']=member.file_size
        if member.file_size>2*1024**3:return result
        with tempfile.TemporaryDirectory(prefix='ba-private-sql-') as d:
            os.chmod(d,0o700);sql=Path(d)/'dump.sql';schemas=set();tail=b'';sensitive_features=False
            with z.open(member) as source,sql.open('wb') as out:
                os.chmod(sql,0o600)
                while True:
                    chunk=source.read(1024*1024)
                    if not chunk:break
                    out.write(chunk);window=tail+chunk
                    for match in re.finditer(rb'CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?(?:\x60[^\x60]+\x60\.)?\x60?([A-Za-z0-9_]+)',window,re.I):
                        schemas.add(match.group(1).lower())
                    if re.search(rb'\b(?:OUTFILE|INFILE|SONAME)\b|INSTALL\s+PLUGIN',window,re.I):sensitive_features=True
                    tail=window[-4096:]
            required=[b'_posts',b'_postmeta',b'_options',b'_users',b'_ba_reports',b'_ba_identities',b'_ba_identity_sessions']
            complete=all(any(t.endswith(suffix) for t in schemas) for suffix in required)
            result.update(sql_schema_count=len(schemas),sql_contains_full_wordpress_identity_schema=complete)
            if not complete:return result
            container=os.environ.get('RESTORE_CONTAINER_ID','')
            if not container or sensitive_features:
                result['restore_gate']='ISOLATION_UNAVAILABLE' if not container else 'SQL_FEATURES_REQUIRE_REVIEW';return result
            # SQL executes only in a disposable isolated container, never against live credentials.
            command=['docker','exec','-i',container,'mariadb','--binary-mode=1','-uroot',
                     '-pba-restore-ephemeral-only','ba_restore_verification']
            with sql.open('rb') as f:
                imported=subprocess.run(command,stdin=f,stdout=subprocess.DEVNULL,stderr=subprocess.PIPE,timeout=600)
            if imported.returncode:
                result['restore_result']='IMPORT_FAILED';return result
            query="SELECT TABLE_SCHEMA,TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA NOT IN ('information_schema','mysql','sys','performance_schema') AND TABLE_TYPE='BASE TABLE'"
            check=subprocess.run(command[:-1]+['-NBe',query],capture_output=True,text=True,timeout=30)
            rows=[l.split('\t') for l in check.stdout.splitlines() if '\t' in l] if check.returncode==0 else []
            restored={r[1].lower().encode() for r in rows if len(r)==2}
            valid=all(any(t.endswith(suffix) for t in restored) for suffix in required)
            result.update(database_restore_tested=True,restore_result='PASS' if valid else 'SCHEMA_INCOMPLETE',restored_tables=len(rows))
            counts={}
            for database,table in rows:
                key='reports' if table.endswith('_ba_reports') else 'events' if table.endswith('_posts') else None
                if not key or not re.fullmatch('[A-Za-z0-9_]+',database) or not re.fullmatch('[A-Za-z0-9_]+',table):continue
                q='SELECT COUNT(*) FROM `'+database+'`.`'+table+'`'+(" WHERE post_type='ba_evento'" if key=='events' else '')
                c=subprocess.run(command[:-1]+['-NBe',q],capture_output=True,text=True,timeout=30)
                if c.returncode==0 and c.stdout.strip().isdigit():counts[key]=int(c.stdout.strip())
            result['restored_counts']=counts
            return result

def main():
    ftp=ftplib.FTP_TLS(context=ssl.create_default_context(cafile=os.environ['FTP_TLS_CA_FILE']),timeout=45)
    result={'operation':'READ_ONLY_BACKUP_CHECK','site_modified':False,'database_restore_tested':False}
    try:
        ftp.connect('46.231.24.137',21);ftp.host='hostmld17.aziendeitalia.com'
        ftp.login(os.environ['FTP_USER'],os.environ['FTP_PASSWORD']);ftp.prot_p()
        if os.environ.get('BACKUP_SUPPLEMENT')=='1':
            result['operation']='BACKUP_VERIFICATION_AND_PRIVATE_SUPPLEMENT'
            result.update(private_supplement(ftp))
        candidates=[];sql_count=0;scanned=0
        for root in ROOTS:
            try: entries=list(ftp.mlsd(root))
            except ftplib.error_perm as e:
                if str(e).startswith('550'):continue
                raise
            scanned+=1
            for name,facts in entries:
                if facts.get('type')!='file':continue
                if name==TARGET:candidates.append((posixpath.join(root,name),facts))
                if name.lower().endswith(('.sql','.sql.gz')):sql_count+=1
        result.update(archive_matches=len(candidates),directories_scanned=scanned,sql_files_in_scanned_directories=sql_count)
        if len(candidates)!=1:
            result['status']='ARCHIVE_NOT_LOCATED' if not candidates else 'AMBIGUOUS_ARCHIVES'
        else:
            remote,facts=candidates[0];size=int(facts.get('size','0'))
            if not size or size>MAX_BYTES:
                result['status']='DOWNLOAD_SIZE_LIMIT';result['size_bytes']=size
            else:
                with tempfile.TemporaryDirectory(prefix='ba-private-backup-') as d:
                    os.chmod(d,0o700);path=Path(d)/'backup.zip';downloaded=0;digest=hashlib.sha256()
                    with path.open('wb') as f:
                        os.chmod(path,0o600)
                        def chunk(data):
                            nonlocal downloaded
                            downloaded+=len(data)
                            if downloaded>MAX_BYTES:raise RuntimeError('SIZE_LIMIT')
                            digest.update(data);f.write(data)
                        ftp.retrbinary('RETR '+remote,chunk,blocksize=1024*1024)
                    # Recheck size/mtime to avoid verifying a file changing during transfer.
                    ftp.voidcmd('TYPE I')
                    if ftp.size(remote)!=size or downloaded!=size:
                        result['status']='ARCHIVE_CHANGED_DURING_READ'
                    elif facts.get('modify') and ftp.sendcmd('MDTM '+remote).split()[-1]!=facts['modify']:
                        result['status']='ARCHIVE_CHANGED_DURING_READ'
                    else:
                        result.update(inspect_archive(path))
                        if os.environ.get('BACKUP_SUPPLEMENT')=='1':result.update(inspect_sql(path))
                        result.update(size_bytes=size,sha256=digest.hexdigest(),status='INSPECTED')
    except Exception as e:
        # Never disclose archive members, server paths or data via exception text.
        result.update(status='CHECK_INCOMPLETE',error_type=type(e).__name__)
    finally:
        try:ftp.quit()
        except ftplib.all_errors:ftp.close()
    print('BACKUP_CHECK_RESULT '+json.dumps(result,sort_keys=True))
if __name__=='__main__':main()
