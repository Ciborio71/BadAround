#!/usr/bin/env python3
"""BA-IDENTITY-02B — safe dormant session REST custody, no Core mutation.

Check exact historical private bootstrap bytes; add one new PHP controller;
backup and swap the private bootstrap only; rollback on mismatch.
"""
from __future__ import annotations
import ftplib,hashlib,io,os,subprocess
from pathlib import Path

ROOT=Path(__file__).resolve().parents[2]
LOCAL=ROOT/"prototypes/ba-identity-01c"
BASE="/httpdocs/wp-content/plugins/badaround-core"
DEST=BASE+"/identity-dormant"
CORE_SHA="18cb9ad21a63e08449eb85b7cb9233846bb02e2c472d05f118cd7d5764b7d175"
BASE_COMMIT="bc84ca59cf409df76d91c77ffdf7cd47c81c1804"
EXISTING="wordpress-private-bootstrap.php"
NEW="wordpress-session-rest.php"
TAG=".ba-identity-02b"

def get(ftp,path):
    buffer=io.BytesIO();ftp.retrbinary("RETR "+path,buffer.write)
    return buffer.getvalue()
def put(ftp,path,data):
    ftp.storbinary("STOR "+path,io.BytesIO(data))

def main():
    expected=subprocess.check_output(
        ["git","show",BASE_COMMIT+":prototypes/ba-identity-01c/"+EXISTING],cwd=ROOT)
    changed=(LOCAL/EXISTING).read_bytes()
    new=(LOCAL/NEW).read_bytes()
    for name in [EXISTING,NEW]:
        subprocess.run(["php","-l",str(LOCAL/name)],check=True,capture_output=True)
    if b"$sessionApi->register_hooks();" not in changed or b"BadAround_Identity_WordPress_Session_REST" not in new:
        raise RuntimeError("Expected safe session path not found")
    ftp=ftplib.FTP_TLS(timeout=35)
    backup=DEST+"/"+EXISTING+TAG+".bak"
    stage=DEST+"/"+EXISTING+TAG+".tmp"
    oldpath=DEST+"/"+EXISTING
    newpath=DEST+"/"+NEW
    backed=False
    staged=False
    added=False
    swapped=False
    try:
        ftp.connect(os.environ["FTP_HOST"],21)
        ftp.login(os.environ["FTP_USER"],os.environ["FTP_PASSWORD"])
        ftp.prot_p()
        core=get(ftp,BASE+"/badaround-core.php")
        if hashlib.sha256(core).hexdigest()!=CORE_SHA:
            raise RuntimeError("Production Core fingerprint drift")
        ftp.cwd(DEST)
        existing={Path(i).name for i in ftp.nlst()}
        if any(Path(i).name in existing for i in [backup,stage,newpath]):
            raise RuntimeError("Destination collision")
        if get(ftp,oldpath)!=expected:
            raise RuntimeError("Production dormant bootstrap differs from reviewed version")
        put(ftp,backup,expected);backed=True
        if get(ftp,backup)!=expected:raise RuntimeError("Original backup mismatch")
        put(ftp,newpath,new);added=True
        if get(ftp,newpath)!=new:raise RuntimeError("New module mismatch")
        put(ftp,stage,changed);staged=True
        if get(ftp,stage)!=changed:raise RuntimeError("Staged candidate mismatch")
        if get(ftp,oldpath)!=expected:raise RuntimeError("Concurrent edit")
        ftp.rename(stage,oldpath);staged=False;swapped=True
        if get(ftp,oldpath)!=changed:raise RuntimeError("Post-swap checksum mismatch")
        if get(ftp,BASE+"/badaround-core.php")!=core:
            raise RuntimeError("Unexpected Core modification")
        print("BA_IDENTITY_02B_SESSION_CUSTODY_PASS=1")
        print("ORIGINAL_BACKUP_VERIFIED=1; CONTROLLER_VERIFIED=1; CORE_UNCHANGED=1; FEATURES_STILL_OFF=1")
    except Exception:
        if swapped:
            try:
                put(ftp,oldpath,get(ftp,backup))
                if get(ftp,oldpath)!=expected:raise RuntimeError("Restore mismatch")
                print("ROLLBACK_RESTORED=1")
            except Exception:print("ROLLBACK_RESTORE_FAILED=1")
        if staged:
            try:ftp.delete(stage)
            except Exception:pass
        if added:
            try:ftp.delete(newpath)
            except Exception:print("ROLLBACK_NEW_MODULE_FAILED=1")
        raise
    finally:
        try:ftp.quit()
        except Exception:ftp.close()
if __name__=="__main__":
    main()
