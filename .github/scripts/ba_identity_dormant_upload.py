#!/usr/bin/env python3
"""BA-IDENTITY production custody: upload dormant code only, NEVER enable it.

New subdirectory only. Existing runtime plugin files are never overwritten.
Refuse unexpected Core hash, unexpected remote directory, or missing PHP files.
Rollback deletes only files created by this run.
"""
from __future__ import annotations
import ftplib
import hashlib
import io
import os
from pathlib import Path
import subprocess

ROOT = Path(__file__).resolve().parents[2]
SOURCE = ROOT / "prototypes" / "ba-identity-01c"
BASE = "/httpdocs/wp-content/plugins/badaround-core"
DEST = BASE + "/identity-dormant"
CORE_HASH = "eb5d6d24800f2150adb400ec410626ab5982264d3f36a0763b5c38cead90f7d0"
FILES = sorted(p for p in SOURCE.glob("*.php") if not p.name.startswith("test-"))
FILES = [p for p in FILES if p.name != "schema-candidate.php"]

def get(ftp: ftplib.FTP_TLS, path: str) -> bytes:
    out = io.BytesIO()
    ftp.retrbinary("RETR " + path, out.write)
    return out.getvalue()

def main() -> None:
    if len(FILES) < 20 or not (SOURCE / "wordpress-composition.php").is_file():
        raise RuntimeError("Candidate module set incomplete")
    for path in FILES:
        subprocess.run(["php", "-l", str(path)], check=True, capture_output=True)
    host = os.environ["FTP_HOST"]
    user = os.environ["FTP_USER"]
    password = os.environ["FTP_PASSWORD"]
    if not host or not user or not password:
        raise RuntimeError("FTPS settings missing")

    ftp = ftplib.FTP_TLS(timeout=35)
    created_dir = False
    created_files: list[str] = []
    try:
        ftp.connect(host, 21)
        ftp.login(user, password)
        ftp.prot_p()
        core_before = get(ftp, BASE + "/badaround-core.php")
        if hashlib.sha256(core_before).hexdigest() != CORE_HASH:
            raise RuntimeError("Live Core hash drift; refusing upload")
        try:
            ftp.cwd(DEST)
        except ftplib.error_perm:
            pass
        else:
            raise RuntimeError("Identity target already exists: do not overwrite")
        ftp.mkd(DEST)
        created_dir = True

        manifest = []
        for local in FILES:
            data = local.read_bytes()
            remote = DEST + "/" + local.name
            ftp.storbinary("STOR " + remote, io.BytesIO(data))
            created_files.append(remote)
            if hashlib.sha256(get(ftp, remote)).digest() != hashlib.sha256(data).digest():
                raise RuntimeError("Checksum mismatch: " + local.name)
            manifest.append(f"{local.name}  {hashlib.sha256(data).hexdigest()}")
            print("DORMANT_MODULE_VERIFIED=" + local.name)

        # The PHP bootstrap isn't modified and doesn't load this directory.
        core_after = get(ftp, BASE + "/badaround-core.php")
        if core_after != core_before:
            raise RuntimeError("Unexpected Core mutation during upload")
        readme = (
            "BA-IDENTITY-01 | DORMANT MODULE CUSTODY\n"
            "No WordPress hooks, REST endpoints or migrations are registered.\n"
            "This folder is NOT included by badaround-core.php.\n"
            "DO NOT enable modules without complete privacy, migrations, QA and rollback.\n"
            + "\n".join(manifest) + "\n"
        ).encode("utf-8")
        readme_remote = DEST + "/DEPLOY-MANIFEST.txt"
        ftp.storbinary("STOR " + readme_remote, io.BytesIO(readme))
        created_files.append(readme_remote)
        if get(ftp, readme_remote) != readme:
            raise RuntimeError("Manifest verification failed")
        print(f"DORMANT_UPLOAD_PASS={len(FILES)} modules; CORE_UNCHANGED=1; DB_UNCHANGED=1")
    except Exception:
        for remote in reversed(created_files):
            try:
                ftp.delete(remote)
                print("ROLLBACK_DELETED=" + remote.rsplit("/", 1)[-1])
            except Exception:
                print("ROLLBACK_DELETE_FAILED=" + remote.rsplit("/", 1)[-1])
        if created_dir:
            try:
                ftp.rmd(DEST)
            except Exception:
                print("ROLLBACK_DIR_REMOVE_FAILED")
        raise
    finally:
        try: ftp.quit()
        except Exception: ftp.close()

if __name__ == "__main__":
    main()
