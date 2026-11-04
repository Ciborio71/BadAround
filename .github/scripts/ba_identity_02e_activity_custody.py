#!/usr/bin/env python3
"""BA-IDENTITY-02E: guarded private activity read model custody.
NO Core changes, DB writes, public route flag or user data.
Three exact-match replacements and two new modules; restore on failure.
"""
import ftplib,hashlib,io,os,subprocess
from pathlib import Path

ROOT=Path(__file__).resolve().parents[2]
SRC=ROOT/"prototypes/ba-identity-01c"
BASE="/httpdocs/wp-content/plugins/badaround-core"
DEST=BASE+"/identity-dormant"
CORE_SHA="18cb9ad21a63e08449eb85b7cb9233846bb02e2c472d05f118cd7d5764b7d175"
BASELINE="78a1c4902f5086a54381b32db6d1d8d897244f1a"
REPLACE=("wordpress-private-bootstrap.php","wordpress-identity-portal.php","identity-portal.js")
NEW=("mysql-private-activity.php","wordpress-activity-rest.php")
TAG=".ba-id-02e"

def read(ftp,path):
    b=io.BytesIO()
    ftp.retrbinary("RETR "+path,b.write)
    return b.getvalue()
def write(ftp,path,value):
    ftp.storbinary("STOR "+path,io.BytesIO(value))

def main():
    old={}
    new={}
    for name in REPLACE:
        old[name]=subprocess.check_output(["git","show",
            BASELINE+":prototypes/ba-identity-01c/"+name],cwd=ROOT)
    for name in REPLACE+NEW:
        data=(SRC/name).read_bytes()
        if not data or len(data)>50000:raise RuntimeError("Unexpected module size")
        if name.endswith(".php"):
            subprocess.run(["php","-l",str(SRC/name)],check=True,capture_output=True)
        elif name.endswith(".js"):
            subprocess.run(["node","--check",str(SRC/name)],check=True,capture_output=True)
        new[name]=data
    if b"$activityApi->register_hooks();" not in new["wordpress-private-bootstrap.php"]:
        raise RuntimeError("Missing gated activity registration")
    ftp=ftplib.FTP_TLS(timeout=35)
    created=[];stages=[];replaced=[]
    try:
        ftp.connect(os.environ["FTP_HOST"],21)
        ftp.login(os.environ["FTP_USER"],os.environ["FTP_PASSWORD"])
        ftp.prot_p()
        core=read(ftp,BASE+"/badaround-core.php")
        if hashlib.sha256(core).hexdigest()!=CORE_SHA:
            raise RuntimeError("Production Core has changed")
        ftp.cwd(DEST)
        existing={Path(n).name for n in ftp.nlst()}
        for name in NEW:
            if name in existing:raise RuntimeError("Refuse to overwrite existing new module")
        for name in REPLACE:
            if read(ftp,DEST+"/"+name)!=old[name]:
                raise RuntimeError("Dormant source drift: "+name)
            backup=DEST+"/"+name+TAG+".bak"
            stage=DEST+"/"+name+TAG+".tmp"
            if Path(backup).name in existing or Path(stage).name in existing:
                raise RuntimeError("Backup/stage name collision")
            write(ftp,backup,old[name])
            if read(ftp,backup)!=old[name]:raise RuntimeError("Backup invalid: "+name)
            write(ftp,stage,new[name]);stages.append(stage)
            if read(ftp,stage)!=new[name]:raise RuntimeError("Stage invalid: "+name)
        for name in NEW:
            path=DEST+"/"+name
            write(ftp,path,new[name]);created.append(path)
            if read(ftp,path)!=new[name]:raise RuntimeError("Upload mismatch: "+name)
            print("PRIVATE_ACTIVITY_FILE_VERIFIED="+name)
        for name in REPLACE:
            path=DEST+"/"+name
            if read(ftp,path)!=old[name]:raise RuntimeError("Concurrent file drift: "+name)
            stage=path+TAG+".tmp"
            ftp.rename(stage,path);stages.remove(stage);replaced.append(name)
            if read(ftp,path)!=new[name]:raise RuntimeError("Replacement mismatch: "+name)
            print("PRIVATE_ACTIVITY_UPDATED="+name)
        if read(ftp,BASE+"/badaround-core.php")!=core:
            raise RuntimeError("Core changed during custody")
        print("BA_IDENTITY_02E_PRIVATE_ACTIVITY_CUSTODY_PASS=1; CORE_UNCHANGED=1; DB_UNCHANGED=1; FEATURE_FLAGS_OFF=1")
    except Exception:
        for name in reversed(replaced):
            try:
                write(ftp,DEST+"/"+name,read(ftp,DEST+"/"+name+TAG+".bak"))
                if read(ftp,DEST+"/"+name)!=old[name]:raise RuntimeError("Restore invalid")
                print("ROLLBACK_RESTORED="+name)
            except Exception:print("ROLLBACK_FAILED="+name)
        for path in stages+created:
            try:ftp.delete(path)
            except Exception:print("ROLLBACK_CLEANUP_FAILED="+path)
        raise
    finally:
        try:ftp.quit()
        except Exception:ftp.close()

if __name__=="__main__":
    main()
