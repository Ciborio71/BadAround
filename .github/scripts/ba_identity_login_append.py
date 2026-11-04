#!/usr/bin/env python3
"""Append-only custody of dormant Identity login modules to production.

Never modify existing files, bootstrap, MySQL, or HTTP routes.
"""
from __future__ import annotations
import hashlib,io,ftplib,os,subprocess
from pathlib import Path

ROOT=Path(__file__).resolve().parents[2]
BASE="/httpdocs/wp-content/plugins/badaround-core"
DEST=BASE+"/identity-dormant"
EXPECTED_CORE="eb5d6d24800f2150adb400ec410626ab5982264d3f36a0763b5c38cead90f7d0"
NAMES=[
    "mysql-verified-resolver.php",
    "wordpress-login-composition.php",
    "wordpress-session-cookie.php",
    "wordpress-login-rest.php",
]
def get(ftp,path):
    b=io.BytesIO();ftp.retrbinary("RETR "+path,b.write);return b.getvalue()
def main():
    sources={}
    for name in NAMES:
        path=ROOT/"prototypes/ba-identity-01c"/name
        subprocess.run(["php","-l",str(path)],check=True,capture_output=True)
        sources[name]=path.read_bytes()
    ftp=ftplib.FTP_TLS(timeout=35)
    created=[]
    try:
        ftp.connect(os.environ["FTP_HOST"],21)
        ftp.login(os.environ["FTP_USER"],os.environ["FTP_PASSWORD"])
        ftp.prot_p()
        baseline=get(ftp,BASE+"/badaround-core.php")
        if hashlib.sha256(baseline).hexdigest()!=EXPECTED_CORE:
            raise RuntimeError("Unexpected live Core SHA, stop")
        ftp.cwd(DEST)
        existing=set(ftp.nlst())
        # Explicitly refuse to overwrite ANY existing file or custody note.
        for name in NAMES+["LOGIN-ADDENDUM.txt"]:
            if name in {Path(item).name for item in existing}:
                raise RuntimeError("Refusing to overwrite existing module: "+name)
        for name,data in sources.items():
            remote=DEST+"/"+name
            ftp.storbinary("STOR "+remote,io.BytesIO(data))
            created.append(remote)
            if get(ftp,remote)!=data:
                raise RuntimeError("Post-write mismatch: "+name)
            print("AUTH_MODULE_VERIFIED="+name)
        note=("BA-IDENTITY-01O / LOGIN-DORMANT\n"
            "Four appended PHP modules only; no WordPress runtime bootstrap or routes enabled.\n"
            "Root key not provisioned, public authentication disabled.\n"
            +"\n".join(f"{name} {hashlib.sha256(data).hexdigest()}"
                for name,data in sources.items())+"\n").encode()
        remote=DEST+"/LOGIN-ADDENDUM.txt"
        ftp.storbinary("STOR "+remote,io.BytesIO(note))
        created.append(remote)
        if get(ftp,remote)!=note:
            raise RuntimeError("Manifest mismatch")
        if get(ftp,BASE+"/badaround-core.php")!=baseline:
            raise RuntimeError("Core drift during append")
        print("IDENTITY_LOGIN_APPEND_PASS=4; CORE_UNCHANGED=1; DB_UNCHANGED=1; FEATURE_ENABLED=0")
    except Exception:
        for path in reversed(created):
            try:ftp.delete(path);print("ROLLBACK_REMOVED="+Path(path).name)
            except Exception:print("ROLLBACK_FAILED="+Path(path).name)
        raise
    finally:
        try:ftp.quit()
        except Exception:ftp.close()
if __name__=="__main__":
    main()
