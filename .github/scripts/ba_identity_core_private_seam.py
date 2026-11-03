#!/usr/bin/env python3
"""BA-IDENTITY-01R: guarded no-op-by-default production Core integration seam.

Backs up current Core, compiles candidate, checks exact SHA, atomically swaps,
and verifies. Restore original on any post-swap failure.
NO feature flag is set by this patch.
"""
import ftplib,hashlib,io,os,subprocess,tempfile
from pathlib import Path

BASE="/httpdocs/wp-content/plugins/badaround-core"
REMOTE=BASE+"/badaround-core.php"
CORE_SHA="eb5d6d24800f2150adb400ec410626ab5982264d3f36a0763b5c38cead90f7d0"
TAG=".ba-identity-01r-guard"
ANCHOR=b"register_activation_hook( BADAROUND_CORE_FILE, array( 'BadAround_Installer', 'activate' ) );"
PATCH=b"""// BA-IDENTITY-01R: private QA only; default OFF. No public routes.
if (
    defined( 'BADAROUND_IDENTITY_RUNTIME_INTEGRATION_ENABLED' )
    && BADAROUND_IDENTITY_RUNTIME_INTEGRATION_ENABLED === true
    && defined( 'BADAROUND_IDENTITY_PRIVATE_QA_ENABLED' )
    && BADAROUND_IDENTITY_PRIVATE_QA_ENABLED === true
) {
    require_once BADAROUND_CORE_PATH . 'identity-dormant/wordpress-runtime-loader.php';
    BadAround_Identity_WordPress_Runtime_Loader::boot();
}

"""

def get(ftp,path):
    b=io.BytesIO()
    ftp.retrbinary("RETR "+path,b.write)
    return b.getvalue()

def put(ftp,path,data):
    ftp.storbinary("STOR "+path,io.BytesIO(data))

def main():
    ftp=ftplib.FTP_TLS(timeout=35)
    backup=REMOTE+TAG+".bak"
    temporary=REMOTE+TAG+".tmp"
    switched=False
    try:
        ftp.connect(os.environ["FTP_HOST"],21)
        ftp.login(os.environ["FTP_USER"],os.environ["FTP_PASSWORD"])
        ftp.prot_p()
        original=get(ftp,REMOTE)
        if hashlib.sha256(original).hexdigest()!=CORE_SHA:
            raise RuntimeError("Core drifted from approved live baseline")
        if original.count(ANCHOR)!=1 or PATCH in original:
            raise RuntimeError("Expected unique Core hook missing or already patched")
        candidate=original.replace(ANCHOR,PATCH+ANCHOR,1)
        with tempfile.TemporaryDirectory() as d:
            local=Path(d)/"badaround-core.php"
            local.write_bytes(candidate)
            subprocess.run(["php","-l",str(local)],check=True,capture_output=True)
        existing={Path(x).name for x in ftp.nlst(BASE)}
        if Path(backup).name in existing or Path(temporary).name in existing:
            raise RuntimeError("Backup/temp path collision")
        # Verify required dependent loader exists before changing runtime.
        loader=get(ftp,BASE+"/identity-dormant/wordpress-runtime-loader.php")
        if b"class BadAround_Identity_WordPress_Runtime_Loader" not in loader:
            raise RuntimeError("Runtime loader is missing")
        put(ftp,backup,original)
        if get(ftp,backup)!=original:
            raise RuntimeError("Backup verification failed")
        put(ftp,temporary,candidate)
        if get(ftp,temporary)!=candidate:
            raise RuntimeError("Candidate read-back failed")
        if get(ftp,REMOTE)!=original:
            raise RuntimeError("Concurrent Core drift")
        ftp.rename(temporary,REMOTE)
        switched=True
        if get(ftp,REMOTE)!=candidate:
            raise RuntimeError("Core post-swap checksum failed")
        print("CORE_PRIVATE_SEAM_DEPLOY_PASS=1")
        print("CORE_POST_SHA256="+hashlib.sha256(candidate).hexdigest())
        print("BACKUP_VERIFIED=1; FEATURE_FLAG_SET=0; DB_UNCHANGED=1")
    except Exception:
        if switched:
            try:
                put(ftp,REMOTE,get(ftp,backup))
                if hashlib.sha256(get(ftp,REMOTE)).hexdigest()!=CORE_SHA:
                    raise RuntimeError("Rollback hash mismatch")
                print("ROLLBACK_RESTORED=1")
            except Exception:
                print("ROLLBACK_FAILED=1")
        raise
    finally:
        try:ftp.quit()
        except Exception:ftp.close()

if __name__=="__main__":
    main()
