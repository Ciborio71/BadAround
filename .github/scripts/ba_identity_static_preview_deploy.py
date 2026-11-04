#!/usr/bin/env python3
"""Upload standalone fake-data HTML preview; no WordPress routes or dynamic code."""
import ftplib,hashlib,io,os
from pathlib import Path
ROOT=Path(__file__).resolve().parents[2]
NAME="identity-demo-static-20261104.html"
SOURCE=ROOT/"prototypes/ba-identity-01c"/NAME
BASE="/httpdocs/wp-content/plugins/badaround-core"
DEST=BASE+"/identity-dormant/"+NAME
EXPECTED="18cb9ad21a63e08449eb85b7cb9233846bb02e2c472d05f118cd7d5764b7d175"
def read(ftp,path):
    b=io.BytesIO();ftp.retrbinary("RETR "+path,b.write);return b.getvalue()
def main():
    data=SOURCE.read_bytes()
    if len(data)>50000 or not data.startswith(b"<!doctype html>") or b'<meta name="robots" content="noindex,nofollow,noarchive">' not in data:
        raise RuntimeError("Not an approved static HTML preview")
    if b"<script" in data.lower() or b"<form" in data.lower() or b"wp-json" in data.lower():
        raise RuntimeError("Dynamic elements not allowed in static preview")
    ftp=ftplib.FTP_TLS(timeout=35);added=False
    try:
        ftp.connect(os.environ["FTP_HOST"],21)
        ftp.login(os.environ["FTP_USER"],os.environ["FTP_PASSWORD"])
        ftp.prot_p()
        core=read(ftp,BASE+"/badaround-core.php")
        if hashlib.sha256(core).hexdigest()!=EXPECTED:raise RuntimeError("Core SHA changed")
        ftp.cwd(BASE+"/identity-dormant")
        if NAME in {Path(s).name for s in ftp.nlst()}:raise RuntimeError("Static demo already exists; no overwrite")
        ftp.storbinary("STOR "+DEST,io.BytesIO(data));added=True
        if read(ftp,DEST)!=data:raise RuntimeError("Demo byte mismatch")
        if read(ftp,BASE+"/badaround-core.php")!=core:raise RuntimeError("Core changed during upload")
        print("BADAROUND_STATIC_PREVIEW_DEPLOY_PASS=1")
        print("STATIC_PREVIEW_SHA256="+hashlib.sha256(data).hexdigest())
        print("CORE_UNCHANGED=1; DATABASE_UNCHANGED=1; NO_USER_DATA=1; NO_API_CALLS=1")
    except Exception:
        if added:
            try:ftp.delete(DEST);print("ROLLBACK_PREVIEW_REMOVED=1")
            except Exception:print("ROLLBACK_PREVIEW_FAILED=1")
        raise
    finally:
        try:ftp.quit()
        except Exception:ftp.close()
if __name__=="__main__":main()
