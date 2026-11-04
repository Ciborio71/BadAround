#!/usr/bin/env python3
"""BA-REPORT-IDENTITY-06 append-only dormant reconciler custody."""
import ftplib,hashlib,io,os,subprocess
from pathlib import Path
ROOT=Path(__file__).resolve().parents[2]
SRC=ROOT/"prototypes/ba-identity-01c"
BASE="/httpdocs/wp-content/plugins/badaround-core"
DEST=BASE+"/identity-dormant"
CORE_SHA="18cb9ad21a63e08449eb85b7cb9233846bb02e2c472d05f118cd7d5764b7d175"
FILES=("report-otp-wordpress-reconciler.php","report-otp-wordpress-adapters.php")
def download(ftp,path):
    b=io.BytesIO()
    ftp.retrbinary("RETR "+path,b.write)
    return b.getvalue()
def main():
    sources={}
    for name in FILES:
        p=SRC/name
        sources[name]=p.read_bytes()
        if not sources[name].startswith(b"<?php"):
            raise RuntimeError("Expected inert PHP")
        subprocess.run(["php","-l",str(p)],check=True,capture_output=True)
    ftp=ftplib.FTP_TLS(timeout=35)
    created=[]
    try:
        ftp.connect(os.environ["FTP_HOST"],21)
        ftp.login(os.environ["FTP_USER"],os.environ["FTP_PASSWORD"])
        ftp.prot_p()
        core=download(ftp,BASE+"/badaround-core.php")
        if hashlib.sha256(core).hexdigest()!=CORE_SHA:
            raise RuntimeError("Live Core changed")
        ftp.cwd(DEST)
        existing={Path(x).name for x in ftp.nlst()}
        if any(x in existing for x in FILES):
            raise RuntimeError("Unsafe overwrite")
        for name in FILES:
            path=DEST+"/"+name
            ftp.storbinary("STOR "+path,io.BytesIO(sources[name]))
            created.append(path)
            if download(ftp,path)!=sources[name]:
                raise RuntimeError("Module checksum mismatch")
            print("RECONCILER_DORMANT_VERIFIED="+name)
        if download(ftp,BASE+"/badaround-core.php")!=core:
            raise RuntimeError("Core changed during upload")
        print("BA_REPORT_IDENTITY_06_DORMANT_PASS=1")
        print("CORE_UNCHANGED=1; DB_UNCHANGED=1; THEME_UNCHANGED=1; ROUTES_OFF=1")
    except Exception:
        for path in reversed(created):
            try:ftp.delete(path)
            except Exception:print("ROLLBACK_DELETE_FAILED="+path)
        raise
    finally:
        try:ftp.quit()
        except Exception:ftp.close()
if __name__=="__main__":main()
