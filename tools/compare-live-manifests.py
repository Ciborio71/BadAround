#!/usr/bin/env python3
"""Compare trusted live and repository SHA-256 manifest files without reading app source.
Usage: python3 tools/compare-live-manifests.py live.json repository.json
Exit 0 only when every file is identical and the inventories have the same paths.
"""
import json
import pathlib
import sys

def read_manifest(path):
    data=json.loads(pathlib.Path(path).read_text(encoding="utf-8"))
    if data.get("contract") != "badaround-live-file-manifest/v1":
        raise ValueError("Unsupported manifest contract")
    result={}
    for area in ("theme","core"):
        section=data[area]
        files=section["files"]
        if not isinstance(files,dict) or section["file_count"]!=len(files):
            raise ValueError("Invalid file count: "+area)
        for name,record in files.items():
            if (not isinstance(name,str) or not name or name.startswith("/")
                or ".." in pathlib.PurePosixPath(name).parts):
                raise ValueError("Invalid relative path")
            sha=record["sha256"]
            if (not isinstance(sha,str) or len(sha)!=64
                or any(c not in "0123456789abcdef" for c in sha)
                or not isinstance(record["bytes"],int) or record["bytes"]<0):
                raise ValueError("Invalid file digest")
            result[area+"/"+name]=(record["bytes"],sha)
    return result

def compare(a,b):
    left,right=read_manifest(a),read_manifest(b)
    missing=sorted(set(left)-set(right))
    extra=sorted(set(right)-set(left))
    modified=sorted(k for k in set(left)&set(right) if left[k]!=right[k])
    return {"match":not (missing or extra or modified),
            "live_only":missing,"repo_only":extra,"different":modified,
            "live_count":len(left),"repo_count":len(right)}

if __name__=="__main__":
    if len(sys.argv)!=3: raise SystemExit("Usage: compare-live-manifests.py live.json repository.json")
    report=compare(sys.argv[1],sys.argv[2])
    print(json.dumps(report,indent=2,sort_keys=True))
    raise SystemExit(0 if report["match"] else 2)
