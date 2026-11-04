#!/usr/bin/env python3
"""BA-IDENTITY live-vs-GitHub reconciliation. Read-only FTPS; no deploy.

Requires FTP_HOST, FTP_USER, FTP_PASSWORD in the job environment.
Compares live files to the selected GitHub source using SHA256. Does not
print or store source content, credentials, uploaded media or personal data.
"""
from __future__ import annotations
import ftplib
import hashlib
import io
import json
import os
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
LIVE_PLUGIN = "/httpdocs/wp-content/plugins/badaround-core"
LOCAL_PLUGIN = ROOT / "wordpress/plugins/badaround-core"
PROTOTYPE = ROOT / "prototype-source/prototypes/ba-identity-01c"

def digest(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()

def fetch(ftp: ftplib.FTP_TLS, name: str) -> bytes | None:
    data = io.BytesIO()
    try:
        ftp.retrbinary("RETR " + name, data.write)
    except ftplib.error_perm as exc:
        if str(exc).startswith(("550", "450")):
            return None
        raise
    if data.tell() > 1024 * 1024:
        raise RuntimeError("Unexpectedly large source file")
    return data.getvalue()

def main() -> None:
    host = os.environ["FTP_HOST"]
    user = os.environ["FTP_USER"]
    password = os.environ["FTP_PASSWORD"]
    if not all((host, user, password)):
        raise RuntimeError("Missing FTPS environment values")
    ftp = ftplib.FTP_TLS(timeout=25)
    try:
        ftp.connect(host, 21)
        ftp.login(user, password)
        ftp.prot_p()
        core = fetch(ftp, LIVE_PLUGIN + "/badaround-core.php")
        if core is None or b"Plugin Name: BadAround Core" not in core:
            raise RuntimeError("Not the expected BadAround plugin; refusing inventory")
        rows = []
        sources = [(Path("badaround-core.php"), LOCAL_PLUGIN / "badaround-core.php", "release")]
        if not PROTOTYPE.is_dir():
            raise RuntimeError("Identity prototype checkout missing; refusing partial inventory")
        for local in sorted(PROTOTYPE.glob("*")):
            if local.suffix in (".php", ".js") and not local.name.startswith("test-"):
                sources.append((Path("identity-dormant") / local.name, local, "prototype"))
        if len(sources) < 45:
            raise RuntimeError("Identity prototype incomplete; refusing partial inventory")
        seen = set()
        for remote_relative, local, kind in sources:
            key = remote_relative.as_posix()
            if key in seen:
                raise RuntimeError("Repeated inventory path")
            seen.add(key)
            remote = fetch(ftp, LIVE_PLUGIN + "/" + key)
            local_bytes = local.read_bytes() if local.is_file() else None
            rows.append({
                "path": key,
                "source": kind,
                "local_exists": local_bytes is not None,
                "remote_exists": remote is not None,
                "local_sha256": digest(local_bytes) if local_bytes is not None else None,
                "remote_sha256": digest(remote) if remote is not None else None,
                "identical": local_bytes == remote if local_bytes is not None and remote is not None else False,
            })
        result = {"mode": "read-only", "scope": "plugin-entrypoint-and-identity",
                  "total": len(rows), "matches": sum(r["identical"] for r in rows),
                  "mismatches": [r for r in rows if not r["identical"]],
                  "verified": [r["path"] for r in rows if r["identical"]]}
        output = ROOT / "identity-reconciliation-inventory.json"
        output.write_text(json.dumps(result, ensure_ascii=False, indent=2) + "\n")
        print(f"READ_ONLY_INVENTORY=PASS total={result['total']} matches={result['matches']} "
              f"mismatch={len(result['mismatches'])}")
        for row in result["mismatches"]:
            print(f"MISMATCH {row['path']} local={row['local_exists']} remote={row['remote_exists']}")
        print("No files were uploaded, modified or deleted.")
    finally:
        try:
            ftp.quit()
        except Exception:
            ftp.close()

if __name__ == "__main__":
    main()
