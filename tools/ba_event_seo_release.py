#!/usr/bin/env python3
"""SEO-only guarded release, no activation, migrations, data reset or legacy URL writes."""
from pathlib import Path
import ftplib,ssl,io,re,hashlib,os,json
ROOT=Path(__file__).resolve().parents[1]
FILES=['plugins/badaround-core/includes/class-badaround-event-seo.php',
       'plugins/badaround-core/includes/class-badaround-publication-service.php',
       'themes/badaround-child/functions.php',
       'themes/badaround-child/template-parts/single-event-layout.php',
       'plugins/badaround-core/identity-dormant/wordpress-community-review.php']
CORE='plugins/badaround-core/badaround-core.php'
def read(ftp,path):
    buf=io.BytesIO()
    try:ftp.retrbinary('RETR '+path,buf.write)
    except ftplib.error_perm as e:
        if str(e).startswith('550'):return None
        raise
    return buf.getvalue()
def release(ftp,prior,candidate,run):
    if not run.isdigit() or set(prior)!=set(FILES) or set(candidate)!=set(FILES):raise RuntimeError('Invalid release manifest')
    base='/httpdocs/wp-content/'
    if hashlib.sha256(read(ftp,base+CORE) or b'').hexdigest()!='18cb9ad21a63e08449eb85b7cb9233846bb02e2c472d05f118cd7d5764b7d175':raise RuntimeError('Core baseline changed')
    modes={};installed=[]
    def paths(i):return ('/wordpress-backups/ba-seo-'+run+'-'+str(i)+'.prior','/wordpress-backups/ba-seo-'+run+'-'+str(i)+'.part')
    for i,name in enumerate(FILES):
        if read(ftp,base+name)!=prior[name]:raise RuntimeError('Baseline drift: '+name)
        modes[name]='644'
        if prior[name] is not None:
            match=re.search(r'(?i)unix\.mode=([0-7]{3,4});',ftp.sendcmd('MLST '+base+name))
            if not match:raise RuntimeError('Cannot verify mode')
            modes[name]=match.group(1)
        if any(read(ftp,p) is not None for p in paths(i)):raise RuntimeError('Backup destination occupied')
    try:
        for i,name in enumerate(FILES):
            backup,temp=paths(i)
            if prior[name] is not None:
                ftp.storbinary('STOR '+backup,io.BytesIO(prior[name]));ftp.sendcmd('SITE CHMOD 600 '+backup)
                if read(ftp,backup)!=prior[name]:raise RuntimeError('Backup verification failed')
            ftp.storbinary('STOR '+temp,io.BytesIO(candidate[name]));ftp.sendcmd('SITE CHMOD '+modes[name]+' '+temp)
            if read(ftp,temp)!=candidate[name]:raise RuntimeError('Upload verification failed')
        for i,name in enumerate(FILES):
            backup,temp=paths(i)
            if read(ftp,base+name)!=prior[name]:raise RuntimeError('Concurrent live drift')
            ftp.rename(temp,base+name);installed.append((i,name))
            if read(ftp,base+name)!=candidate[name]:raise RuntimeError('Readback verification failed')
        if any(read(ftp,base+n)!=candidate[n] for n in FILES):raise RuntimeError('Final source mismatch')
        return {'result':'PASS','files':{n:hashlib.sha256(candidate[n]).hexdigest() for n in FILES},'legacy_urls_changed':False,'data_reset':False,'activation_changed':False}
    except Exception:
        for i,name in reversed(installed):
            backup,temp=paths(i)
            if read(ftp,base+name)!=candidate[name]:raise RuntimeError('Concurrent drift prevents rollback') from None
            if prior[name] is None:ftp.delete(base+name)
            else:
                if read(ftp,backup)!=prior[name]:raise RuntimeError('Invalid rollback backup') from None
                ftp.sendcmd('SITE CHMOD '+modes[name]+' '+backup);ftp.rename(backup,base+name)
            if read(ftp,base+name)!=prior[name]:raise RuntimeError('Rollback readback failed') from None
        raise

def main():
    prior={n:(ROOT/'prior-source/wordpress'/n).read_bytes() if (ROOT/'prior-source/wordpress'/n).exists() else None for n in FILES}
    candidate={n:(ROOT/'wordpress'/n).read_bytes() for n in FILES}
    ftp=ftplib.FTP_TLS(context=ssl.create_default_context(cafile=os.environ['FTP_TLS_CA_FILE']),timeout=45)
    try:
        ftp.connect('46.231.24.137',21);ftp.host='hostmld17.aziendeitalia.com'
        ftp.login(os.environ['FTP_USER'],os.environ['FTP_PASSWORD']);ftp.prot_p()
        # Preserve the complete deployed Identity runtime, including its CSS.
        module=Path('wordpress/plugins/badaround-core/identity-dormant')
        for p in (ROOT/'prior-source'/module).iterdir():
            if p.suffix in ('.php','.js','.css') and read(ftp,'/httpdocs/wp-content/plugins/badaround-core/identity-dormant/'+p.name)!=p.read_bytes():raise RuntimeError('Identity runtime baseline drift')
        evidence=release(ftp,prior,candidate,os.environ['GITHUB_RUN_ID'])
        print('EVENT_SEO_RELEASE_PASS');print(json.dumps(evidence,sort_keys=True))
        (ROOT/'event-seo-release-evidence.json').write_text(json.dumps(evidence,indent=2))
    finally:
        try:ftp.quit()
        except ftplib.all_errors:ftp.close()
if __name__=='__main__':main()
