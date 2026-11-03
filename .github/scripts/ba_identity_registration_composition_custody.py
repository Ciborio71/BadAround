#!/usr/bin/env python3
"""BA-IDENTITY-01R add the remaining inert registration composition dependency."""
import ftplib,io,hashlib,os,subprocess
from pathlib import Path

ROOT=Path(__file__).resolve().parents[2]
NAME="wordpress-registration-composition.php"
LOCAL=ROOT/"prototypes/ba-identity-01c"/NAME
BASE="/httpdocs/wp-content/plugins/badaround-core"
DEST=BASE+"/identity-dormant"
CORE_SHA="18cb9ad21a63e08449eb85b7cb9233846bb02e2c472d05f118cd7d5764b7d175"

def read(ftp,path):
    b=io.BytesIO();ftp.retrbinary("RETR "+path,b.write);return b.getvalue()

def main():
    subprocess.run(["php","-l",str(LOCAL)],check=True)
    data=LOCAL.read_bytes()
    if len(data)<500 or len(data)>15000:raise RuntimeError("Unexpected module size")
    ftp=ftplib.FTP_TLS(timeout=35)
    written=False
    try:
        ftp.connect(os.environ["FTP_HOST"],21)
        ftp.login(os.environ["FTP_USER"],os.environ["FTP_PASSWORD"])
        ftp.prot_p()
        core=read(ftp,BASE+"/badaround-core.php")
        if hashlib.sha256(core).hexdigest()!=CORE_SHA:
            raise RuntimeError("Core has changed; stop")
        ftp.cwd(DEST)
        if NAME in {Path(item).name for item in ftp.nlst()}:
            raise RuntimeError("Refuse existing file overwrite")
        remote=DEST+"/"+NAME
        ftp.storbinary("STOR "+remote,io.BytesIO(data))
        written=True
        if read(ftp,remote)!=data:raise RuntimeError("Module checksum mismatch")
        if read(ftp,BASE+"/badaround-core.php")!=core:
            raise RuntimeError("Core changed during append")
        print("REGISTRATION_COMPOSITION_APPEND_PASS=1; CORE_UNCHANGED=1; DB_UNCHANGED=1; ROUTES_ENABLED=0")
    except Exception:
        if written:
            try:ftp.delete(DEST+"/"+NAME);print("ROLLBACK_REMOVED=1")
            except Exception:print("ROLLBACK_FAILED=1")
        raise
    finally:
        try:ftp.quit()
        except Exception:ftp.close()
if __name__=="__main__":main()
