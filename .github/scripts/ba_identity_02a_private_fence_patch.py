#!/usr/bin/env python3
"""BA-IDENTITY-02A: exact-match dormant bootstrap QA network fence patch.

One new PHP dependency plus an exact previous-version replacement.
No Core edit, configuration changes, secrets, email or database writes.
"""
import ftplib,hashlib,io,os,subprocess,tempfile
from pathlib import Path

ROOT=Path(__file__).resolve().parents[2]
SOURCE=ROOT/"prototypes/ba-identity-01c"
BASE="/httpdocs/wp-content/plugins/badaround-core"
DEST=BASE+"/identity-dormant"
CORE_SHA="18cb9ad21a63e08449eb85b7cb9233846bb02e2c472d05f118cd7d5764b7d175"
PREVIOUS="4155aa01cf79dba0f9442b48a1829096c1993b9a"
TARGET="wordpress-private-bootstrap.php"
EXTRA="wordpress-private-access.php"
TAG=".ba-id-02a"

def download(ftp,path):
    output=io.BytesIO()
    ftp.retrbinary("RETR "+path,output.write)
    return output.getvalue()

def upload(ftp,path,data):
    ftp.storbinary("STOR "+path,io.BytesIO(data))

def main():
    original=subprocess.check_output([
        "git","show",PREVIOUS+":prototypes/ba-identity-01c/"+TARGET
    ],cwd=ROOT)
    updated=(SOURCE/TARGET).read_bytes()
    guard=(SOURCE/EXTRA).read_bytes()
    for name in [TARGET,EXTRA]:
        subprocess.run(["php","-l",str(SOURCE/name)],check=True,capture_output=True)
    if b"BadAround_Identity_Private_Access::authorized()" not in updated:
        raise RuntimeError("Network guard missing from replacement")
    ftp=ftplib.FTP_TLS(timeout=35)
    backup=DEST+"/"+TARGET+TAG+".bak"
    stage=DEST+"/"+TARGET+TAG+".tmp"
    extra=DEST+"/"+EXTRA
    previous=DEST+"/"+TARGET
    copied=False
    staged=False
    swapped=False
    added=False
    try:
        ftp.connect(os.environ["FTP_HOST"],21)
        ftp.login(os.environ["FTP_USER"],os.environ["FTP_PASSWORD"])
        ftp.prot_p()
        core=download(ftp,BASE+"/badaround-core.php")
        if hashlib.sha256(core).hexdigest()!=CORE_SHA:
            raise RuntimeError("Live Core drift")
        ftp.cwd(DEST)
        existing={Path(p).name for p in ftp.nlst()}
        if any(Path(p).name in existing for p in [backup,stage,extra]):
            raise RuntimeError("Unexpected existing backup, stage or guard")
        if download(ftp,previous)!=original:
            raise RuntimeError("Live dormant bootstrap differs from baseline")
        upload(ftp,backup,original);copied=True
        if download(ftp,backup)!=original:
            raise RuntimeError("Bootstrap backup checksum mismatch")
        upload(ftp,extra,guard);added=True
        if download(ftp,extra)!=guard:
            raise RuntimeError("QA guard checksum mismatch")
        upload(ftp,stage,updated);staged=True
        if download(ftp,stage)!=updated:
            raise RuntimeError("Candidate bootstrap checksum mismatch")
        if download(ftp,previous)!=original:
            raise RuntimeError("Concurrent dormant bootstrap modification")
        ftp.rename(stage,previous);swapped=True;staged=False
        if download(ftp,previous)!=updated:
            raise RuntimeError("Patched bootstrap mismatch")
        if download(ftp,BASE+"/badaround-core.php")!=core:
            raise RuntimeError("Core changed during guarded patch")
        print("BA_IDENTITY_02A_DORMANT_QA_FENCE_PASS=1")
        print("PRIVATE_GUARD_VERIFIED=1; BOOTSTRAP_PATCH_VERIFIED=1; CORE_UNCHANGED=1; DB_UNCHANGED=1; ROUTES_ENABLED=0")
    except Exception:
        if swapped:
            try:
                upload(ftp,previous,download(ftp,backup))
                if download(ftp,previous)!=original:
                    raise RuntimeError("Restore mismatch")
                print("ROLLBACK_BOOTSTRAP_RESTORED=1")
            except Exception:
                print("ROLLBACK_BOOTSTRAP_FAILED=1")
        if staged:
            try:ftp.delete(stage)
            except Exception:pass
        if added:
            try:ftp.delete(extra)
            except Exception:print("ROLLBACK_GUARD_FAILED=1")
        raise
    finally:
        try:ftp.quit()
        except Exception:ftp.close()
if __name__=="__main__":
    main()
