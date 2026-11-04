#!/usr/bin/env python3
"""BA-REPORT-IDENTITY-05: append only dormant report OTP components.
No Core/theme/database modifications; no migrations; no runtime inclusion.
"""
import ftplib,hashlib,io,os,subprocess
from pathlib import Path

ROOT=Path(__file__).resolve().parents[2]
SRC=ROOT/"prototypes/ba-identity-01c"
BASE="/httpdocs/wp-content/plugins/badaround-core"
DEST=BASE+"/identity-dormant"
CORE_SHA="18cb9ad21a63e08449eb85b7cb9233846bb02e2c472d05f118cd7d5764b7d175"
FILES=(
    "report-submission-verification-policy.php",
    "report-otp-service.php",
    "report-otp-schema-candidate.php",
    "report-otp-store.php",
    "report-otp-reservation-policy.php",
    "report-otp-atomic-intake.php",
    "report-otp-outbox-schema.php",
    "report-otp-outbox-store.php",
    "report-otp-outbox-coordinator.php",
    "report-otp-canonical-reader.php",
)
def get(f,path):
    out=io.BytesIO()
    f.retrbinary("RETR "+path,out.write)
    return out.getvalue()
def put(f,path,data):
    f.storbinary("STOR "+path,io.BytesIO(data))
def main():
    data={}
    for name in FILES:
        path=SRC/name
        binary=path.read_bytes()
        if not binary.startswith(b"<?php") or len(binary)>30000:
            raise RuntimeError("Unexpected or too large PHP module")
        subprocess.run(["php","-l",str(path)],check=True,capture_output=True)
        data[name]=binary
    ftp=ftplib.FTP_TLS(timeout=40)
    appended=[]
    try:
        ftp.connect(os.environ["FTP_HOST"],21)
        ftp.login(os.environ["FTP_USER"],os.environ["FTP_PASSWORD"])
        ftp.prot_p()
        core=get(ftp,BASE+"/badaround-core.php")
        if hashlib.sha256(core).hexdigest()!=CORE_SHA:
            raise RuntimeError("Production Core changed: refuse deploy")
        ftp.cwd(DEST)
        existing={Path(item).name for item in ftp.nlst()}
        collisions=set(FILES)&existing
        if collisions:raise RuntimeError("Refuse overwrite existing dormant files: "+str(sorted(collisions)))
        for name in FILES:
            remote=DEST+"/"+name
            put(ftp,remote,data[name])
            appended.append(remote)
            if get(ftp,remote)!=data[name]:raise RuntimeError("Verification mismatch: "+name)
            print("REPORT_OTP_DORMANT_VERIFIED="+name)
        if get(ftp,BASE+"/badaround-core.php")!=core:
            raise RuntimeError("Core changed during transfer")
        print("BA_REPORT_IDENTITY_05_DORMANT_DEPLOY_PASS=1")
        print("CORE_UNCHANGED=1; THEME_UNCHANGED=1; DB_UNCHANGED=1; API_ROUTES_UNCHANGED=1")
    except Exception:
        for remote in reversed(appended):
            try:ftp.delete(remote)
            except Exception:print("ROLLBACK_DELETE_FAILED="+remote)
        raise
    finally:
        try:ftp.quit()
        except Exception:ftp.close()
if __name__=="__main__":main()
