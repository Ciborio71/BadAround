#!/usr/bin/env python3
"""BA-IDENTITY-01R append three inert bootstrap modules, no existing-file writes."""
import ftplib,hashlib,io,os,subprocess
from pathlib import Path

ROOT=Path(__file__).resolve().parents[2]
BASE="/httpdocs/wp-content/plugins/badaround-core"
DEST=BASE+"/identity-dormant"
CORE_SHA="eb5d6d24800f2150adb400ec410626ab5982264d3f36a0763b5c38cead90f7d0"
FILES=("wordpress-privacy-notice.php","wordpress-private-bootstrap.php","wordpress-runtime-loader.php")

def get(ftp,path):
    b=io.BytesIO()
    ftp.retrbinary("RETR "+path,b.write)
    return b.getvalue()

def main():
    sources={}
    for name in FILES:
        path=ROOT/"prototypes/ba-identity-01c"/name
        subprocess.run(["php","-l",str(path)],check=True,capture_output=True)
        sources[name]=path.read_bytes()
    ftp=ftplib.FTP_TLS(timeout=35)
    written=[]
    try:
        ftp.connect(os.environ["FTP_HOST"],21)
        ftp.login(os.environ["FTP_USER"],os.environ["FTP_PASSWORD"])
        ftp.prot_p()
        core=get(ftp,BASE+"/badaround-core.php")
        if hashlib.sha256(core).hexdigest()!=CORE_SHA:
            raise RuntimeError("Live Core changed; refuse deployment")
        ftp.cwd(DEST)
        current={Path(x).name for x in ftp.nlst()}
        if any(name in current for name in FILES):
            raise RuntimeError("A destination file already exists; refuse overwrite")
        for name,data in sources.items():
            path=DEST+"/"+name
            ftp.storbinary("STOR "+path,io.BytesIO(data))
            written.append(path)
            if get(ftp,path)!=data:
                raise RuntimeError("Module checksum failed: "+name)
            print("PRIVATE_BOOTSTRAP_VERIFIED="+name)
        if get(ftp,BASE+"/badaround-core.php")!=core:
            raise RuntimeError("Core was modified during module transfer")
        print("PRIVATE_BOOTSTRAP_APPEND_PASS=3; CORE_UNCHANGED=1; DB_UNCHANGED=1; ROUTES_ENABLED=0")
    except Exception:
        for path in reversed(written):
            try:ftp.delete(path);print("ROLLBACK_DELETED="+Path(path).name)
            except Exception:print("ROLLBACK_FAILED="+Path(path).name)
        raise
    finally:
        try:ftp.quit()
        except Exception:ftp.close()
if __name__=="__main__":
    main()
