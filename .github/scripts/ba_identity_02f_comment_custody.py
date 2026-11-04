#!/usr/bin/env python3
"""BA-IDENTITY-02F: append private-only event comment UI without public changes."""
import ftplib,hashlib,io,os,subprocess
from pathlib import Path
ROOT=Path(__file__).resolve().parents[2]
SRC=ROOT/"prototypes/ba-identity-01c"
CORE="/httpdocs/wp-content/plugins/badaround-core"
DEST=CORE+"/identity-dormant"
CORE_SHA="18cb9ad21a63e08449eb85b7cb9233846bb02e2c472d05f118cd7d5764b7d175"
BASELINE="8cd9250de5526ec0f298ee2d4a3f279302449031"
BOOT="wordpress-private-bootstrap.php"
NEW=("wordpress-comment-form.php","identity-comment-form.js")
TAG=".ba-id-02f"
def get(ftp,path):
    b=io.BytesIO();ftp.retrbinary("RETR "+path,b.write);return b.getvalue()
def put(ftp,path,data):
    ftp.storbinary("STOR "+path,io.BytesIO(data))
def main():
    original=subprocess.check_output(["git","show",
        BASELINE+":prototypes/ba-identity-01c/"+BOOT],cwd=ROOT)
    updated=(SRC/BOOT).read_bytes()
    files={n:(SRC/n).read_bytes() for n in NEW}
    for name in [BOOT]+list(NEW):
        if name.endswith(".php"):
            subprocess.run(["php","-l",str(SRC/name)],check=True,capture_output=True)
        else:subprocess.run(["node","--check",str(SRC/name)],check=True,capture_output=True)
    if b"BadAround_Identity_WordPress_Comment_Form::register_hooks();" not in updated:
        raise RuntimeError("Expected gate missing")
    ftp=ftplib.FTP_TLS(timeout=35)
    stage=DEST+"/"+BOOT+TAG+".tmp"
    backup=DEST+"/"+BOOT+TAG+".bak"
    created=[];staged=False;moved=False
    try:
        ftp.connect(os.environ["FTP_HOST"],21)
        ftp.login(os.environ["FTP_USER"],os.environ["FTP_PASSWORD"])
        ftp.prot_p()
        core=get(ftp,CORE+"/badaround-core.php")
        if hashlib.sha256(core).hexdigest()!=CORE_SHA:
            raise RuntimeError("Core fingerprint differs")
        ftp.cwd(DEST)
        names={Path(x).name for x in ftp.nlst()}
        if any(Path(x).name in names for x in [backup,stage]+[DEST+"/"+n for n in NEW]):
            raise RuntimeError("Destination collision")
        if get(ftp,DEST+"/"+BOOT)!=original:
            raise RuntimeError("Private bootstrap changed since review")
        put(ftp,backup,original)
        if get(ftp,backup)!=original:raise RuntimeError("Backup verification failed")
        for name,data in files.items():
            path=DEST+"/"+name
            put(ftp,path,data);created.append(path)
            if get(ftp,path)!=data:raise RuntimeError("Upload verification failed")
            print("PRIVATE_COMMENT_FILE_VERIFIED="+name)
        put(ftp,stage,updated);staged=True
        if get(ftp,stage)!=updated:raise RuntimeError("Candidate mismatch")
        if get(ftp,DEST+"/"+BOOT)!=original:raise RuntimeError("Concurrent edit")
        ftp.rename(stage,DEST+"/"+BOOT);moved=True;staged=False
        if get(ftp,DEST+"/"+BOOT)!=updated:raise RuntimeError("Bootstrap checksum mismatch")
        if get(ftp,CORE+"/badaround-core.php")!=core:raise RuntimeError("Core drift")
        print("BA_IDENTITY_02F_DORMANT_COMMENT_FORM_PASS=1; CORE_UNCHANGED=1; DB_UNCHANGED=1; PUBLIC_FEATURES_OFF=1")
    except Exception:
        if moved:
            try:
                put(ftp,DEST+"/"+BOOT,get(ftp,backup))
                if get(ftp,DEST+"/"+BOOT)!=original:raise RuntimeError("Restore mismatch")
                print("ROLLBACK_BOOTSTRAP_RESTORED=1")
            except Exception:print("ROLLBACK_BOOTSTRAP_FAILED=1")
        if staged:
            try:ftp.delete(stage)
            except Exception:pass
        for path in created:
            try:ftp.delete(path)
            except Exception:print("ROLLBACK_DELETE_FAILED="+path)
        raise
    finally:
        try:ftp.quit()
        except Exception:ftp.close()
if __name__=="__main__":main()
