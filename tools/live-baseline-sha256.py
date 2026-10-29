#!/usr/bin/env python3
"""BASELINE-LIVE-02: local filesystem SHA256 manifest. Read-only. No secrets/content emitted.
Run separately on a trusted host copy and an exported repository checkout, then compare JSON files offline.
Usage: python3 tools/live-baseline-sha256.py /absolute/path/to/wp-content/themes/badaround-child /absolute/path/to/wp-content/plugins/badaround-core
"""
import hashlib, json, pathlib, sys
ALLOWED={'.php','.js','.css','.json','.html','.txt','.svg','.cjs'}
def snapshot(root):
    root=pathlib.Path(root).resolve(strict=True)
    if not root.is_dir(): raise ValueError('Expected directory: '+str(root))
    entries={}
    for item in sorted(root.rglob('*')):
        if item.is_symlink(): raise RuntimeError('Symlink encountered: '+str(item))
        if not item.is_file() or item.suffix.lower() not in ALLOWED: continue
        rel=item.relative_to(root).as_posix()
        digest=hashlib.sha256()
        with item.open('rb') as handle:
            for chunk in iter(lambda:handle.read(1024*1024),b''):digest.update(chunk)
        entries[rel]={'bytes':item.stat().st_size,'sha256':digest.hexdigest()}
    return {'file_count':len(entries),'files':entries}
if __name__=='__main__':
    if len(sys.argv)!=3: raise SystemExit('Supply theme and core absolute paths (read-only)')
    print(json.dumps({'contract':'badaround-live-file-manifest/v1',
       'theme':snapshot(sys.argv[1]),'core':snapshot(sys.argv[2])},
       indent=2,sort_keys=True))
