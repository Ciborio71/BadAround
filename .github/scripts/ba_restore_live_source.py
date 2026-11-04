#!/usr/bin/env python3
"""Recover audited live sources from a short-lived GitHub Actions artifact.

Repository-only change. Does NOT connect to or modify WordPress.
Fails on unapproved paths, invalid hashes, unexpected archives or obvious secrets.
"""
from pathlib import Path
import hashlib
import io
import json
import re
import zipfile

ROOT=Path(__file__).resolve().parents[2]
PAYLOAD=ROOT/"custody"/"ba-live-source-recovery.zip"
MANIFEST=ROOT/"custody"/"ba-live-source-recovery-manifest.json"
SOURCE_PREFIX="wordpress/"
APPROVED = {
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
}
REQUIRED_HASHES={
 "plugins/badaround-core/includes/class-badaround-core.php":"1806f3b74786504957b8546a7e939e901af0cd73d1bde9413586526a9ec1f71b",
 "plugins/badaround-core/includes/class-badaround-publication-service.php":"abdeada1736f9a1516bc4c521c3cd9eca72a672b5fb567b0c9ccb81db6381c16",
 "themes/badaround-child/assets/css/home.css":"d0943a824c704abe5828766348a8ee4ea36f3d153353376fc3f81ea66d71e942",
 "themes/badaround-child/front-page.php":"5e46ef00c7b7b784b6f5bcb3d2d9809aab58bcc9f63d57d77e096001cf8bf7ab",
 "themes/badaround-child/page-radar-demo.php":"efe4747dab5d52d99ad3893992ece83f832cbe1ec1ad63e59cd9bac60267044a",
}
SECRETS = [
 re.compile(rb"-----BEGIN (?:OPENSSH|RSA|EC|DSA|PRIVATE) PRIVATE KEY-----",re.I),
 re.compile(rb"sk-(?:proj-)?[A-Za-z0-9_-]{20,}"),
 re.compile(rb"(?:password|api[_-]?key|secret)\s*[:=]\s*['\"][^'\"]{20,}['\"]",re.I),
]
def main():
 meta=json.loads(MANIFEST.read_text())
 rows=meta.get("files",[])
 if len(rows)!=len(APPROVED) or {r["path"] for r in rows}!=APPROVED:
  raise RuntimeError("Unexpected source manifest scope")
 with zipfile.ZipFile(PAYLOAD) as z:
  if set(z.namelist())!=APPROVED:
   raise RuntimeError("Unexpected archive entries")
  checked={}
  for row in rows:
   path=row["path"]
   source=z.read(path)
   if len(source)>2_000_000 or len(source)!=row["bytes"]:
    raise RuntimeError("Invalid source size")
   sha=hashlib.sha256(source).hexdigest()
   if sha!=row["sha256"] or (path in REQUIRED_HASHES and sha!=REQUIRED_HASHES[path]):
    raise RuntimeError("Live SHA256 differs from approved inventory")
   if any(p.search(source) for p in SECRETS):
    raise RuntimeError("Potential secret requires manual source review")
   if path.endswith(".php") and not source.startswith(b"<?php"):
    raise RuntimeError("Unexpected PHP header")
   dest=(ROOT/SOURCE_PREFIX/path).resolve()
   if not dest.is_relative_to((ROOT/"wordpress").resolve()):
    raise RuntimeError("Illegal path")
   checked[dest]=source
 for dest,source in checked.items():
  dest.parent.mkdir(parents=True,exist_ok=True)
  dest.write_bytes(source)
 print("EXACT_LIVE_SOURCE_IMPORT_VERIFIED="+str(len(checked)))
 print("REPOSITORY_ONLY_CHANGE=1; PRODUCTION_UNMODIFIED=1")
if __name__=="__main__":
 main()
