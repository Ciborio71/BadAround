import importlib.util
import json
import pathlib
import tempfile

root=pathlib.Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location("comparator",root/"tools/compare-live-manifests.py")
mod=importlib.util.module_from_spec(spec)
spec.loader.exec_module(mod)
sha="a"*64
def manifest(files):
    return {"contract":"badaround-live-file-manifest/v1",
            "theme":{"file_count":len(files),"files":files},
            "core":{"file_count":0,"files":{}}}
with tempfile.TemporaryDirectory() as tmp:
    a,b=(pathlib.Path(tmp)/n for n in ("a.json","b.json"))
    record={"front-page.php":{"bytes":12,"sha256":sha}}
    a.write_text(json.dumps(manifest(record)))
    b.write_text(json.dumps(manifest(record)))
    assert mod.compare(a,b)["match"]
    b.write_text(json.dumps(manifest({"front-page.php":{"bytes":12,"sha256":"b"*64}})))
    result=mod.compare(a,b)
    assert not result["match"] and result["different"]==["theme/front-page.php"]
    b.write_text(json.dumps(manifest({})))
    assert mod.compare(a,b)["live_only"]==["theme/front-page.php"]
    bad=manifest({"../escape.php":{"bytes":1,"sha256":sha}})
    b.write_text(json.dumps(bad))
    try: mod.read_manifest(b)
    except ValueError: pass
    else: raise AssertionError("Unsafe path accepted")
print("BASELINE-COMPARE: PASS 4 isolated checks")
