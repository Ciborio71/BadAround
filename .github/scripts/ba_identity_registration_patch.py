#!/usr/bin/env python3
"""BA-IDENTITY append + exact two-file dormant registration patch.

No WordPress bootstrap, DB write, live route, or existing unrelated module.
Requires the existing file bytes from the original custody commit and
validates every uploaded byte; rollback restores patched files and removes
only newly created files. All backups are kept outside public access concerns
in the same plugin directory with .bak extension (PHP not executable).
"""
from __future__ import annotations
import ftplib,hashlib,io,os,subprocess
from pathlib import Path

ROOT=Path(__file__).resolve().parents[2]
LOCAL=ROOT/"prototypes/ba-identity-01c"
BASE="/httpdocs/wp-content/plugins/badaround-core"
DEST=BASE+"/identity-dormant"
CORE_HASH="eb5d6d24800f2150adb400ec410626ab5982264d3f36a0763b5c38cead90f7d0"
INITIAL="37ab700155ae68d1009a4c9c2feb15f5fcddb8f2"
REPLACE=("enrollment-policy.php","mysql-enrollment-store.php")
ADD=("registration-service.php","wordpress-registration-rest.php")
TAG=".ba-id-registration-01p"

def fetch(ftp,path):
    buff=io.BytesIO();ftp.retrbinary("RETR "+path,buff.write);return buff.getvalue()

def put(ftp,path,blob):
    ftp.storbinary("STOR "+path,io.BytesIO(blob))

def main():
    original={}
    desired={}
    for name in REPLACE:
        path="prototypes/ba-identity-01c/"+name
        original[name]=subprocess.check_output(["git","show",INITIAL+":"+path],cwd=ROOT)
    for name in REPLACE+ADD:
        source=LOCAL/name
        subprocess.run(["php","-l",str(source)],check=True,capture_output=True)
        desired[name]=source.read_bytes()
        if not desired[name] or len(desired[name])>100000:
            raise RuntimeError("Unexpected module size: "+name)
    ftp=ftplib.FTP_TLS(timeout=35)
    old_core=None
    backup_names=[]
    temporary=[]
    replaced=[]
    appended=[]
    try:
        ftp.connect(os.environ["FTP_HOST"],21)
        ftp.login(os.environ["FTP_USER"],os.environ["FTP_PASSWORD"])
        ftp.prot_p()
        old_core=fetch(ftp,BASE+"/badaround-core.php")
        if hashlib.sha256(old_core).hexdigest()!=CORE_HASH:
            raise RuntimeError("Core changed; stop")
        ftp.cwd(DEST)
        existing={Path(name).name for name in ftp.nlst()}
        for name in ADD+("REGISTRATION-ADDENDUM.txt",):
            if name in existing:
                raise RuntimeError("Refusing to replace existing "+name)
        for name in REPLACE:
            live=fetch(ftp,DEST+"/"+name)
            if live!=original[name]:
                raise RuntimeError("Unexpected existing module revision: "+name)
            backup=DEST+"/"+name+TAG+".bak"
            temp=DEST+"/"+name+TAG+".tmp"
            if Path(backup).name in existing or Path(temp).name in existing:
                raise RuntimeError("Existing backup/temp collision: "+name)
            put(ftp,backup,live)
            backup_names.append(backup)
            if fetch(ftp,backup)!=live:
                raise RuntimeError("Backup not verified: "+name)
            put(ftp,temp,desired[name])
            temporary.append(temp)
            if fetch(ftp,temp)!=desired[name]:
                raise RuntimeError("Candidate upload mismatch: "+name)
        # Both current files remain old until all backups and temp files exist.
        for name in REPLACE:
            live=DEST+"/"+name
            if fetch(ftp,live)!=original[name]:
                raise RuntimeError("Concurrent edit during patch: "+name)
            temp=live+TAG+".tmp"
            ftp.rename(temp,live)
            temporary.remove(temp)
            replaced.append(name)
            if fetch(ftp,live)!=desired[name]:
                raise RuntimeError("Post-patch mismatch: "+name)
            print("REGISTRATION_PATCH_VERIFIED="+name)
        for name in ADD:
            live=DEST+"/"+name
            put(ftp,live,desired[name])
            appended.append(live)
            if fetch(ftp,live)!=desired[name]:
                raise RuntimeError("Append mismatch: "+name)
            print("REGISTRATION_NEW_VERIFIED="+name)
        manifest=("BA-IDENTITY-01P registration modules DORMANT\n"
            "Bootstrap unchanged; no REST routes or data migration.\n"
            +"\n".join(f"{name}: {hashlib.sha256(content).hexdigest()}"
            for name,content in desired.items())+"\n").encode()
        path=DEST+"/REGISTRATION-ADDENDUM.txt"
        put(ftp,path,manifest)
        appended.append(path)
        if fetch(ftp,path)!=manifest:
            raise RuntimeError("Manifest mismatch")
        if fetch(ftp,BASE+"/badaround-core.php")!=old_core:
            raise RuntimeError("Unexpected live bootstrap change")
        print("DORMANT_REGISTRATION_PATCH_PASS=4; EXISTING_PATCHED=2; NEW_APPENDED=2; CORE_UNCHANGED=1; DB_UNCHANGED=1; ROUTES_ENABLED=0")
    except Exception:
        for path in reversed(appended):
            try:ftp.delete(path);print("ROLLBACK_DELETE="+Path(path).name)
            except Exception:print("ROLLBACK_DELETE_FAILED="+Path(path).name)
        for name in reversed(replaced):
            live=DEST+"/"+name
            try:
                put(ftp,live,fetch(ftp,live+TAG+".bak"))
                if fetch(ftp,live)!=original[name]:
                    raise RuntimeError("Restore checksum failed")
                print("ROLLBACK_RESTORED="+name)
            except Exception:
                print("ROLLBACK_RESTORE_FAILED="+name)
        for path in temporary:
            try:ftp.delete(path)
            except Exception:pass
        raise
    finally:
        try:ftp.quit()
        except Exception:ftp.close()

if __name__=="__main__":
    main()
