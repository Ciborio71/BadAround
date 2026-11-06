#!/usr/bin/env python3
"""Read-only inspection of the user-named backup. No live writes or public artifacts."""
import ftplib, ssl, os, tempfile, zipfile, hashlib, json, posixpath
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
def main():
    ftp=ftplib.FTP_TLS(context=ssl.create_default_context(cafile=os.environ['FTP_TLS_CA_FILE']),timeout=45)
    result={'operation':'READ_ONLY_BACKUP_CHECK','site_modified':False,'database_restore_tested':False}
    try:
        ftp.connect('46.231.24.137',21);ftp.host='hostmld17.aziendeitalia.com'
        ftp.login(os.environ['FTP_USER'],os.environ['FTP_PASSWORD']);ftp.prot_p()
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
                        result.update(size_bytes=size,sha256=digest.hexdigest(),status='INSPECTED')
    except Exception as e:
        # Never disclose archive members, server paths or data via exception text.
        result.update(status='CHECK_INCOMPLETE',error_type=type(e).__name__)
    finally:
        try:ftp.quit()
        except ftplib.all_errors:ftp.close()
    print('BACKUP_CHECK_RESULT '+json.dumps(result,sort_keys=True))
if __name__=='__main__':main()
