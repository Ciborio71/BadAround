"""Only the authorized EXT-INTAKE-01 Core delta. Strict TLS, custody, atomic files, rollback."""
import ftplib, hashlib, io, json, os, pathlib, ssl, subprocess, sys
BASE = "84ae8fc6d172c5409e4b3a93f522ca2e41c32074"
ROOT = "/httpdocs/wp-content/plugins/badaround-core/"
PREFIX = "wordpress/plugins/badaround-core/"
# New definitions before loader; activate hooks LAST. Rollback in reverse order.
TARGETS = ["includes/class-badaround-external-report-intake.php", "includes/class-badaround-publication-service.php", "badaround-core.php", "includes/class-badaround-core.php"]
PASSIVE = ["includes/class-badaround-report-schema.php", "includes/class-badaround-report-repository.php", "includes/class-badaround-report-persistence-service.php", "includes/class-badaround-moderation-admin.php", "includes/class-badaround-moderation-service.php", "includes/class-badaround-public-geo-contract.php", "includes/class-badaround-public-geo-projection.php", "includes/class-badaround-installer.php"]
E=pathlib.Path("evidence"); E.mkdir(exist_ok=True)
def baseline(p):
    r=subprocess.run(["git","show",BASE+":"+PREFIX+p],capture_output=True)
    return r.stdout if r.returncode==0 else None
def digest(b): return hashlib.sha256(b).hexdigest() if b is not None else None
def connect():
    f=ftplib.FTP_TLS(context=ssl.create_default_context(cafile="tls/curl-chain.pem"),timeout=45)
    f.connect(os.environ["FTP_HOST"],21); f.host=os.environ["FTP_TLS_NAME"]
    f.login(os.environ["FTP_USER"],os.environ["FTP_PASS"]); f.prot_p()
    return f
def read(f,p):
    b=io.BytesIO()
    try: f.retrbinary("RETR "+ROOT+p,b.write)
    except ftplib.error_perm as ex:
        if str(ex).startswith("550"): return None
        raise
    return b.getvalue()
def put(f,p,b):
    tmp=ROOT+p+".ext-intake-01-tmp"
    f.storbinary("STOR "+tmp,io.BytesIO(b)); f.rename(tmp,ROOT+p)
def preflight(f,after=False):
    rows=[]
    for p in TARGETS+PASSIVE:
        expected=(pathlib.Path(PREFIX+p).read_bytes() if after and p in TARGETS else baseline(p))
        actual=read(f,p)
        rows.append(dict(path=p,expected_sha256=digest(expected),remote_sha256=digest(actual),match=actual==expected))
    (E/("custody-after.json" if after else "custody-before.json")).write_text(json.dumps(rows,indent=2))
    assert all(x["match"] for x in rows), "RUNTIME_DRIFT_STOP_NO_OVERWRITE"
    print("CUSTODY_PASS",len(rows))
def prepare():
    f=connect()
    try:
        preflight(f)
        (E/"rollback").mkdir(exist_ok=True)
        manifest=[]
        for p in TARGETS:
            old=read(f,p)
            if old is not None:
                dest=E/"rollback"/p;dest.parent.mkdir(parents=True,exist_ok=True);dest.write_bytes(old)
            manifest.append(dict(path=p,existed=old is not None,before_sha256=digest(old),candidate_sha256=digest(pathlib.Path(PREFIX+p).read_bytes())))
        (E/"rollback-manifest.json").write_text(json.dumps(manifest,indent=2))
    finally:f.quit()
def deploy():
    manifest=json.loads((E/"rollback-manifest.json").read_text())
    f=connect();changed=[]
    try:
        preflight(f) # Repeat immediately before first mutation.
        for p in TARGETS:
            changed.append(p)
            b=pathlib.Path(PREFIX+p).read_bytes();put(f,p,b)
            assert read(f,p)==b, "UPLOAD_VERIFY_FAILED"
        preflight(f,True)
        (E/"deploy-result.json").write_text(json.dumps(dict(status="verified",files=TARGETS,head=os.environ["GITHUB_SHA"]),indent=2))
    except BaseException:
        for p in reversed(changed):
            original=next(x for x in manifest if x["path"]==p)
            if original["existed"]:
                b=(E/"rollback"/p).read_bytes();assert digest(b)==original["before_sha256"];put(f,p,b)
            elif read(f,p) is not None:f.delete(ROOT+p)
        preflight(f)
        (E/"rollback-used.txt").write_text("BYTE_EXACT_ROLLBACK_VERIFIED")
        raise
    finally:f.quit()
if __name__=="__main__":
    {"prepare":prepare,"deploy":deploy}[sys.argv[1]]()

