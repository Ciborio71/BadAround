#!/usr/bin/env python3
"""BA-IDENTITY-02D: exact-match privacy draft denial update (dormant only)."""
import ftplib,io,hashlib,os,subprocess
from pathlib import Path
ROOT=Path(__file__).resolve().parents[2]
NAME="wordpress-privacy-notice.php"
LOCAL=ROOT/"prototypes/ba-identity-01c"/NAME
ORIGINAL_COMMIT="bc84ca59cf409df76d91c77ffdf7cd47c81c1804"
CORE="/httpdocs/wp-content/plugins/badaround-core"
DEST=CORE+"/identity-dormant/"
CORE_SHA="18cb9ad21a63e08449eb85b7cb9233846bb02e2c472d05f118cd7d5764b7d175"
def get(ftp,path):
    b=io.BytesIO();ftp.retrbinary("RETR "+path,b.write);return b.getvalue()
def put(ftp,path,blob):ftp.storbinary("STOR "+path,io.BytesIO(blob))
def main():
    original=subprocess.check_output(["git","show",ORIGINAL_COMMIT+":prototypes/ba-identity-01c/"+NAME],cwd=ROOT)
    new=LOCAL.read_bytes()
    subprocess.run(["php","-l",str(LOCAL)],check=True,capture_output=True)
    if b"'bozza pre-pubblicazione'" not in new:
        raise RuntimeError("Draft privacy guard missing")
    ftp=ftplib.FTP_TLS(timeout=35)
    target=DEST+NAME; backup=target+".ba-id-02d.bak"; stage=target+".ba-id-02d.tmp"
    moved=False;staged=False
    try:
        ftp.connect(os.environ["FTP_HOST"],21);ftp.login(os.environ["FTP_USER"],os.environ["FTP_PASSWORD"]);ftp.prot_p()
        core=get(ftp,CORE+"/badaround-core.php")
        if hashlib.sha256(core).hexdigest()!=CORE_SHA:
            raise RuntimeError("Core SHA mismatch")
        ftp.cwd(DEST)
        known={Path(v).name for v in ftp.nlst()}
        if Path(backup).name in known or Path(stage).name in known:
            raise RuntimeError("Backup or staged path collision")
        if get(ftp,target)!=original:raise RuntimeError("Existing privacy code drift")
        put(ftp,backup,original)
        if get(ftp,backup)!=original:raise RuntimeError("Backup mismatch")
        put(ftp,stage,new);staged=True
        if get(ftp,stage)!=new:raise RuntimeError("New privacy module mismatch")
        if get(ftp,target)!=original:raise RuntimeError("Concurrent edit")
        ftp.rename(stage,target);moved=True;staged=False
        if get(ftp,target)!=new:raise RuntimeError("Post-write mismatch")
        if get(ftp,CORE+"/badaround-core.php")!=core:raise RuntimeError("Core changed")
        print("BA_IDENTITY_02D_PRIVACY_DRAFT_GUARD_PASS=1; CORE_UNCHANGED=1; ROUTES_ENABLED=0")
    except Exception:
        if moved:
            try:
                put(ftp,target,get(ftp,backup))
                if get(ftp,target)!=original:raise RuntimeError("Restore mismatch")
                print("ROLLBACK_RESTORED=1")
            except Exception:print("ROLLBACK_FAILED=1")
        if staged:
            try:ftp.delete(stage)
            except Exception:pass
        raise
    finally:
        try:ftp.quit()
        except Exception:ftp.close()
if __name__=="__main__":main()
