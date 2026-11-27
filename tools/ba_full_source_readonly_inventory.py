#!/usr/bin/env python3
"""BA-IDENTITY repository-vs-production full deployable source inventory.

Read-only FTPS. Never uploads, renames, deletes, changes flags, or reads uploads.
Compares tracked plugin/theme files using SHA256 and enumerates extra source paths.
No file bytes or credentials are included in logs/artifacts.
"""
from __future__ import annotations
import ftplib
import hashlib
import io
import json
import os
from pathlib import Path, PurePosixPath

ROOT = Path(__file__).resolve().parents[1]
SCOPES = {
    "wordpress/plugins/badaround-core": "/httpdocs/wp-content/plugins/badaround-core",
    "wordpress/themes/badaround-child": "/httpdocs/wp-content/themes/badaround-child",
}
INCLUDE_EXTENSIONS = {".php", ".js", ".css", ".json", ".html", ".txt", ".svg",
                      ".png", ".jpg", ".jpeg", ".webp", ".gif", ".ico", ".woff", ".woff2"}
MAX_FILE_BYTES = 12 * 1024 * 1024
MAX_FILES = 500
MAX_DEPTH = 12

def safe_relative(name: str) -> bool:
    if not name or name.startswith("/"):
        return False
    parts = PurePosixPath(name).parts
    return bool(parts) and all(p not in (".", "..") for p in parts) and "\\" not in name

def eligible(name: str) -> bool:
    return safe_relative(name) and PurePosixPath(name).suffix.lower() in INCLUDE_EXTENSIONS

def remote_read(ftp, path: str) -> bytes | None:
    buffer = io.BytesIO()
    def collect(chunk):
        if buffer.tell() + len(chunk) > MAX_FILE_BYTES:
            raise RuntimeError("Live source exceeds safe comparison size")
        buffer.write(chunk)
    try:
        ftp.retrbinary("RETR " + path, collect)
    except ftplib.error_perm as exc:
        if str(exc).startswith(("550", "450")):
            return None
        raise
    return buffer.getvalue()

def list_sources(ftp, remote_root: str) -> set[str]:
    pending = [("", 0)]
    found: set[str] = set()
    visited: set[str] = set()
    while pending:
        prefix, depth = pending.pop()
        if depth > MAX_DEPTH or prefix in visited:
            if depth > MAX_DEPTH:
                raise RuntimeError("Source directory tree too deep")
            continue
        visited.add(prefix)
        folder = remote_root + ("/" + prefix if prefix else "")
        entries = ftp.nlst(folder)
        if not isinstance(entries, list):
            raise RuntimeError("FTPS directory list unavailable")
        for entry in entries:
            if not isinstance(entry, str):
                raise RuntimeError("Invalid FTPS directory entry")
            # nlst may return absolute paths or only filenames; normalize safely
            if entry.startswith(folder.rstrip("/") + "/"):
                rel = entry[len(remote_root.rstrip("/")) + 1:]
            elif "/" not in entry:
                rel = prefix + ("/" if prefix else "") + entry
            else:
                raise RuntimeError("Unexpected FTPS listing path")
            if not safe_relative(rel) or rel in (prefix, prefix + "/.") or rel.endswith("/.."):
                continue
            if any(p.startswith(".") for p in PurePosixPath(rel).parts):
                continue
            full = remote_root + "/" + rel
            try:
                ftp.cwd(full)
            except ftplib.error_perm:
                if eligible(rel):
                    found.add(rel)
            else:
                pending.append((rel, depth + 1))
            if len(found) > MAX_FILES or len(pending) > MAX_FILES:
                raise RuntimeError("Source tree exceeds file count bound")
    return found

def main():
    env = {key: os.environ[key] for key in ("FTP_HOST", "FTP_USER", "FTP_PASSWORD")}
    if not all(env.values()):
        raise RuntimeError("FTPS configuration missing")
    ftp = ftplib.FTP_TLS(timeout=30)
    try:
        ftp.connect(env["FTP_HOST"], 21)
        ftp.login(env["FTP_USER"], env["FTP_PASSWORD"])
        ftp.prot_p()
        reports = []
        for repo_path, live_path in SCOPES.items():
            local_root = ROOT / repo_path
            if not local_root.is_dir():
                raise RuntimeError("Repository scope missing: " + repo_path)
            tracked = {str(p.relative_to(local_root)).replace(os.sep, "/"): p
                       for p in local_root.rglob("*") if p.is_file()
                       and ".git" not in p.parts and eligible(str(p.relative_to(local_root)))}
            if not tracked:
                raise RuntimeError("Empty source scope")
            deployed = list_sources(ftp, live_path)
            items = []
            for rel, local in sorted(tracked.items()):
                local_bytes = local.read_bytes()
                if len(local_bytes) > MAX_FILE_BYTES:
                    raise RuntimeError("Tracked source exceeds size limit")
                live_bytes = remote_read(ftp, live_path + "/" + rel) if rel in deployed else None
                items.append({"path": rel, "status": "missing" if live_bytes is None else
                              ("match" if live_bytes == local_bytes else "different"),
                              "repo_sha256": hashlib.sha256(local_bytes).hexdigest(),
                              "live_sha256": hashlib.sha256(live_bytes).hexdigest() if live_bytes is not None else None})
            report = {
                "scope": repo_path,
                "total_tracked": len(items),
                "matches": sum(v["status"] == "match" for v in items),
                "different": [v for v in items if v["status"] == "different"],
                "missing": [v["path"] for v in items if v["status"] == "missing"],
                "untracked_live_sources": sorted(deployed - set(tracked)),
            }
            reports.append(report)
            print("SCOPE=" + repo_path + " tracked=" + str(report["total_tracked"])
                  + " matches=" + str(report["matches"])
                  + " different=" + str(len(report["different"]))
                  + " missing=" + str(len(report["missing"]))
                  + " untracked=" + str(len(report["untracked_live_sources"])))
        output = {"mode": "readonly", "scopes": reports}
        (ROOT / "full-production-parity.json").write_text(
            json.dumps(output, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
        print("FULL_SOURCE_READONLY_INVENTORY=PASS; production changes=0")
    finally:
        try:
            ftp.quit()
        except Exception:
            ftp.close()

if __name__ == "__main__":
    main()
