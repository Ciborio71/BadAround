#!/usr/bin/env python3
"""BA-IDENTITY-02C: exact-match private portal custody, no public exposure.

Uploads two new dormant files and replaces only the private bootstrap. The
current public Core and database are byte-for-byte left untouched.
"""
import ftplib,hashlib,io,os,subprocess
from pathlib import Path
ROOT=Path(__file__).resolve().parents[2]
DIR=ROOT/"prototypes/ba-identity-01c"
CORE="/httpdocs/wp-content/plugins/badaround-core"
DEST=CORE+"/identity-dormant"
BASELINE="1ed89d213c294ec02510d5853a63f95045e62295"
CORE_SHA="18cb9ad21a63e08449eb85b7cb9233846bb02e2c472d05f118cd7d5764b7d175"
BOOT="wordpress-private-bootstrap.php"
NEW=("wordpress-identity-portal.php","identity-portal.js")
TAG=".ba-id-02c"

def fetch(f,path):
    b=io.BytesIO();f.retrbinary("RETR "+path,b.write);return b.getvalue()
def put(f,path,blob):f.storbinary("STOR "+path,io.BytesIO(blob))

def main():
    old=subprocess.check_output(["git","show",BASELINE+":prototypes/ba-identity-01c/"+BOOT],cwd=ROOT)
    newboot=(DIR/BOOT).read_bytes()
    modules={n:(DIR/n).read_bytes() for n in NEW}
    subprocess.run(["php","-l",str(DIR/BOOT)],check=True)
    subprocess.run(["php","-l",str(DIR/NEW[0])],check=True)
    subprocess.run(["node","--check",str(DIR/NEW[1])],check=True)
    if b"BadAround_Identity_WordPress_Portal::register_hooks();" not in newboot:
        raise RuntimeError("Expected consent-gated portal hook missing")
    ftp=ftplib.FTP_TLS(timeout=35)
    previous=DEST+"/"+BOOT
    backup=previous+TAG+".bak"
    staged=previous+TAG+".tmp"
    created=[];moved=False;has_stage=False
    try:
        ftp.connect(os.environ["FTP_HOST"],21)
        ftp.login(os.environ["FTP_USER"],os.environ["FTP_PASSWORD"])
        ftp.prot_p()
        core=fetch(ftp,CORE+"/badaround-core.php")
        if hashlib.sha256(core).hexdigest()!=CORE_SHA:raise RuntimeError("Core changed")
        ftp.cwd(DEST)
        names={Path(x).name for x in ftp.nlst()}
        if any(Path(x).name in names for x in [backup,staged]+[DEST+"/"+n for n in NEW]):
            raise RuntimeError("Destination collision")
        if fetch(ftp,previous)!=old:raise RuntimeError("Private bootstrap baseline drift")
        put(ftp,backup,old)
        if fetch(ftp,backup)!=old:raise RuntimeError("Backup verification failed")
        for name,blob in modules.items():
            path=DEST+"/"+name
            put(ftp,path,blob);created.append(path)
            if fetch(ftp,path)!=blob:raise RuntimeError("Module checksum mismatch")
            print("PORTAL_FILE_VERIFIED="+name)
        put(ftp,staged,newboot);has_stage=True
        if fetch(ftp,staged)!=newboot:raise RuntimeError("Bootstrap stage mismatch")
        if fetch(ftp,previous)!=old:raise RuntimeError("Concurrent bootstrap edit")
        ftp.rename(staged,previous);moved=True;has_stage=False
        if fetch(ftp,previous)!=newboot:raise RuntimeError("Bootstrap checksum mismatch")
        if fetch(ftp,CORE+"/badaround-core.php")!=core:raise RuntimeError("Core drift")
        print("BA_IDENTITY_02C_PORTAL_CUSTODY_PASS=1; CORE_UNCHANGED=1; DB_UNCHANGED=1; FEATURE_FLAGS_OFF=1")
    except Exception:
        if moved:
            try:
                put(ftp,previous,fetch(ftp,backup))
                if fetch(ftp,previous)!=old:raise RuntimeError("Restore mismatch")
                print("ROLLBACK_BOOTSTRAP_VERIFIED=1")
            except Exception:print("ROLLBACK_BOOTSTRAP_FAILED=1")
        if has_stage:
            try:ftp.delete(staged)
            except Exception:pass
        for path in created:
            try:ftp.delete(path)
            except Exception:print("ROLLBACK_DELETE_FAILED="+path)
        raise
    finally:
        try:ftp.quit()
        except Exception:ftp.close()
if __name__=="__main__":main()
