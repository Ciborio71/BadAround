#!/usr/bin/env python3
"""Add only the Identity QA template and scoped stylesheet; preserve live files."""
from pathlib import Path
import ftplib,hashlib,io,json,os,ssl
ROOT=Path(__file__).resolve().parents[1]
BASE='/httpdocs/wp-content/themes/badaround-child/'
FILES=['assets/css/identity-qa.css','page-identity-qa.php']
def read(ftp,path):
    data=io.BytesIO()
    def chunk(b):
        if data.tell()+len(b)>300000:raise RuntimeError('Remote size bound exceeded')
        data.write(b)
    try:ftp.retrbinary('RETR '+path,chunk)
    except ftplib.error_perm as e:
        if str(e).startswith('550'):return None
        raise
    return data.getvalue()
def main():
    run=os.environ['GITHUB_RUN_ID']
    if not run.isdigit():raise RuntimeError('Invalid release context')
    ftp=ftplib.FTP_TLS(context=ssl.create_default_context(cafile=os.environ['FTP_TLS_CA_FILE']),timeout=40)
    added=[]
    candidate={n:(ROOT/'wordpress/themes/badaround-child'/n).read_bytes() for n in FILES}
    try:
        ftp.connect(os.environ['FTP_HOST'],21);ftp.host='hostmld17.aziendeitalia.com'
        ftp.login(os.environ['FTP_USER'],os.environ['FTP_PASSWORD']);ftp.prot_p()
        dependencies=['header.php','footer.php','functions.php','style.css','assets/css/base.css',
            'assets/css/components.css','assets/css/shell.css','assets/js/navigation.js']
        previous={n:read(ftp,BASE+n) for n in dependencies}
        if any(not b for b in previous.values()):raise RuntimeError('Missing active BadAround dependencies')
        for name in FILES:
            live=read(ftp,BASE+name)
            if live is not None and live!=candidate[name]:raise RuntimeError('Destination occupied by different source')
        for index,name in enumerate(FILES):
            if read(ftp,BASE+name)==candidate[name]:continue
            temp='/wordpress-backups/ba-identity-qa-template-'+run+'-'+str(index)+'.part'
            if read(ftp,temp) is not None:raise RuntimeError('Temporary destination occupied')
            ftp.storbinary('STOR '+temp,io.BytesIO(candidate[name]))
            if read(ftp,temp)!=candidate[name] or read(ftp,BASE+name) is not None:
                raise RuntimeError('Upload integrity or destination changed')
            ftp.rename(temp,BASE+name);added.append(name)
            if read(ftp,BASE+name)!=candidate[name]:raise RuntimeError('Installed source differs')
        for name,original in previous.items():
            if read(ftp,BASE+name)!=original:raise RuntimeError('Existing theme dependency changed during release')
        (ROOT/'identity-qa-template-evidence.json').write_text(json.dumps({
            'installed':FILES,'existing_theme_files_modified':False,
            'identity_logic_modified':False,'private_configuration_modified':False,
            'sha256':{n:hashlib.sha256(b).hexdigest() for n,b in candidate.items()}
        },indent=2)+'\n')
        print('IDENTITY_QA_TEMPLATE_DEPLOY_PASS')
    except Exception as error:
        failures=[]
        for name in reversed(added):
            try:
                if read(ftp,BASE+name)!=candidate[name]:raise RuntimeError('Unexpected source prevents rollback')
                ftp.delete(BASE+name)
            except Exception:failures.append(name)
        if failures:raise RuntimeError('Rollback requires intervention for '+','.join(failures)) from error
        raise
    finally:
        try:ftp.quit()
        except ftplib.all_errors:ftp.close()
if __name__=='__main__':main()
