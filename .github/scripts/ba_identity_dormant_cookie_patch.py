#!/usr/bin/env python3
"""Production patch: one dormant PHP file, exact old/new comparison, rollback.
No WordPress bootstrap change, no database or event mutation.
"""
import ftplib
import hashlib
import io
import os
import subprocess
from pathlib import Path

ROOT=Path(__file__).resolve().parents[2]
REL="prototypes/ba-identity-01c/wordpress-community-rest-adapter.php"
CORE="/httpdocs/wp-content/plugins/badaround-core"
TARGET=CORE+"/identity-dormant/wordpress-community-rest-adapter.php"
EXPECTED_CORE="eb5d6d24800f2150adb400ec410626ab5982264d3f36a0763b5c38cead90f7d0"
OLD_REV="911a0b03a460568fae553f6a5088d4d1c8574ef1"
OLD_COOKIE=b"public const COOKIE_NAME = 'ba_identity_session';"
NEW_COOKIE=b"public const COOKIE_NAME = '__Host-ba_identity';"

def retrieve(ftp,path):
    b=io.BytesIO()
    ftp.retrbinary("RETR "+path,b.write)
    return b.getvalue()

def upload(ftp,path,data):
    ftp.storbinary("STOR "+path,io.BytesIO(data))

def main():
    old=subprocess.check_output(["git","show",OLD_REV+":"+REL],cwd=ROOT)
    new=(ROOT/REL).read_bytes()
    if old.count(OLD_COOKIE)!=1 or old.replace(OLD_COOKIE,NEW_COOKIE,1)!=new:
        raise RuntimeError("Unrelated PHP changes; abort one-line patch")
    subprocess.run(["php","-l",str(ROOT/REL)],check=True)
    ftp=ftplib.FTP_TLS(timeout=35)
    backup=TARGET+".ba-identity-cookie-security.bak"
    temp=TARGET+".ba-identity-cookie-security.tmp"
    wrote=False
    try:
        ftp.connect(os.environ["FTP_HOST"],21)
        ftp.login(os.environ["FTP_USER"],os.environ["FTP_PASSWORD"])
        ftp.prot_p()
        core=retrieve(ftp,CORE+"/badaround-core.php")
        if hashlib.sha256(core).hexdigest()!=EXPECTED_CORE:
            raise RuntimeError("Core SHA drift; refusing")
        live=retrieve(ftp,TARGET)
        if live!=old:
            raise RuntimeError("Dormant file drift; refusing")
        try:
            retrieve(ftp,backup)
        except ftplib.error_perm:
            pass
        else:
            raise RuntimeError("Backup already exists; refusing overwrite")
        upload(ftp,backup,live)
        if retrieve(ftp,backup)!=live:
            raise RuntimeError("Backup verification failed")
        upload(ftp,temp,new)
        if retrieve(ftp,temp)!=new or retrieve(ftp,TARGET)!=old:
            raise RuntimeError("Temp or concurrency verification failed")
        ftp.rename(temp,TARGET)
        wrote=True
        if retrieve(ftp,TARGET)!=new:
            raise RuntimeError("Post-write checksum mismatch")
        if retrieve(ftp,CORE+"/badaround-core.php")!=core:
            raise RuntimeError("Unexpected Core drift")
        print("DORMANT_COOKIE_PATCH_PASS=1; CORE_UNCHANGED=1; DB_UNCHANGED=1; BACKUP_VERIFIED=1")
    except Exception:
        if wrote:
            try:
                upload(ftp,TARGET,retrieve(ftp,backup))
                print("ROLLBACK_RESTORED=1")
            except Exception:
                print("ROLLBACK_FAILED=1")
        raise
    finally:
        try: ftp.quit()
        except Exception: ftp.close()

if __name__=="__main__":
    main()
