#!/usr/bin/env python3
"""Read-only recover EXACT approved live source files to a private CI artifact.

No uploads, no runtime mutations, no user content. Never prints file contents.
This is source custody, not a deployment or automatic GitHub commit.
"""
from __future__ import annotations
import ftplib
import hashlib
import io
import json
import os
import zipfile
from pathlib import PurePosixPath

BASE = "/httpdocs/wp-content"
FILES = [
    "plugins/badaround-core/includes/class-badaround-core.php",
    "plugins/badaround-core/includes/class-badaround-publication-service.php",
    "themes/badaround-child/assets/css/home.css",
    "themes/badaround-child/front-page.php",
    "themes/badaround-child/page-radar-demo.php",
    "plugins/badaround-core/includes/class-badaround-external-report-intake.php",
    "plugins/badaround-core/includes/class-badaround-intelligence-data-quality.php",
    "plugins/badaround-core/includes/class-badaround-intelligence-peak-candidates.php",
    "plugins/badaround-core/includes/class-badaround-intelligence-trends.php",
    "plugins/badaround-core/includes/class-badaround-radar-canonical-adapter.php",
    "plugins/badaround-core/includes/class-badaround-radar-offline-bridge.php",
    "plugins/badaround-core/includes/class-badaround-radar-private-qa.php",
    "plugins/badaround-core/includes/class-badaround-radar-public-counts.php",
    "plugins/badaround-core/includes/class-badaround-radar-public-renderer.php",
    "plugins/badaround-core/includes/class-badaround-radar-quality-registry.php",
    "plugins/badaround-core/includes/class-badaround-radar-review-admin.php",
    "plugins/badaround-core/includes/class-badaround-radar-view-model.php",
    "plugins/badaround-core/includes/class-badaround-radar-window-counts.php",
    "plugins/badaround-core/includes/class-badaround-radar-window-renderer.php",
]
MAX_PER_FILE = 2_000_000

def main():
    for p in FILES:
        obj=PurePosixPath(p)
        if obj.is_absolute() or ".." in obj.parts or obj.suffix not in (".php",".css"):
            raise RuntimeError("Unapproved source path")
    ftp = ftplib.FTP_TLS(timeout=30)
    try:
        ftp.connect(os.environ["FTP_HOST"],21)
        ftp.login(os.environ["FTP_USER"],os.environ["FTP_PASSWORD"])
        ftp.prot_p()
        manifest=[]
        with zipfile.ZipFile("ba-live-source-recovery.zip","w",compression=zipfile.ZIP_DEFLATED) as archive:
            for rel in FILES:
                b=io.BytesIO()
                def recv(chunk):
                    if b.tell()+len(chunk)>MAX_PER_FILE:
                        raise RuntimeError("Source file too large")
                    b.write(chunk)
                ftp.retrbinary("RETR "+BASE+"/"+rel,recv)
                data=b.getvalue()
                if rel.endswith(".php") and not data.startswith(b"<?php"):
                    raise RuntimeError("Unexpected PHP source marker")
                archive.writestr(rel,data)
                manifest.append({"path":rel,"sha256":hashlib.sha256(data).hexdigest(),"bytes":len(data)})
        with open("ba-live-source-recovery-manifest.json","w") as fh:
            json.dump({"mode":"readonly","files":manifest},fh,indent=2)
        print("LIVE_SOURCE_READONLY_RECOVERY_PASS="+str(len(manifest)))
        print("NO_PRODUCTION_FILES_MODIFIED=1")
    finally:
        try:ftp.quit()
        except Exception:ftp.close()

if __name__ == "__main__":
    main()
