import ftplib,ssl,os,pathlib,subprocess,json,hashlib,io,sys,time,urllib.request,urllib.error
BASE='879f55853fbb99c20ad85bc522e03f19f93bc3c6';CAND='443bb4cb90782a45d058ce03717762c3648f3d6c'
ROOT='/staging.badaround.it/wp-content/';PROBE='mu-plugins/mapcontrols01s-443bb4cb-qa.php'
S=json.loads(pathlib.Path('ops/mapcontrols01s-scope.json').read_text());FILES=S['targets'];assert len(FILES)==5
E=pathlib.Path('evidence');E.mkdir(exist_ok=True)
def git(ref,p):
 r=subprocess.run(['git','show',ref+':'+p],capture_output=True);return r.stdout if r.returncode==0 else None
def sha(b):return hashlib.sha256(b).hexdigest() if b is not None else None
def rel(p):assert p.startswith('wordpress/');return p[len('wordpress/'):]
def connect():
 f=ftplib.FTP_TLS(context=ssl.create_default_context(cafile='tls/curl-chain.pem'),timeout=45);f.connect(os.environ['FTP_HOST'],21);f.host=os.environ['FTP_TLS_NAME'];f.login(os.environ['FTP_USER'],os.environ['FTP_PASS']);f.prot_p();return f
def read(f,p):
 parent,name=p.rsplit('/',1);listing=f.nlst(ROOT+parent);names=[x.rstrip('/').rsplit('/',1)[-1] for x in listing]
 if name not in names:return None
 b=io.BytesIO();f.retrbinary('RETR '+ROOT+p,b.write);return b.getvalue()
def put(f,p,b):
 tmp=ROOT+p+'.mapcontrols01s-tmp';f.storbinary('STOR '+tmp,io.BytesIO(b));f.rename(tmp,ROOT+p)
def custody(after=False):
 f=connect();rows=[]
 try:
  for p in S['custody']:
   expected=git(CAND if after and p in FILES else BASE,p);remote=read(f,rel(p));rows.append({'path':p,'expected':sha(expected),'remote':sha(remote),'exists':remote is not None,'match':expected==remote})
 finally:f.quit()
 (E/('custody-after.json' if after else 'custody-before.json')).write_text(json.dumps(rows,indent=2));assert all(x['match'] for x in rows),'UNEXPLAINED_RUNTIME_DRIFT';print('CUSTODY_PASS',len(rows),flush=True)
def rollback():
 if not (E/'upload-started.txt').exists() or (E/'rollback-used.txt').exists():return
 f=connect()
 try:
  for x in reversed(json.loads((E/'rollback-manifest.json').read_text())):
   p=x['path'];old=(E/'rollback'/pathlib.Path(p).name).read_bytes() if x['exists'] else None
   assert old==git(BASE,p)
   if old is None:
    current=read(f,rel(p));assert current is None or current==git(CAND,p),'UNEXPECTED_TARGET_DRIFT_DURING_ROLLBACK'
    if current is not None:f.delete(ROOT+rel(p))
   else:put(f,rel(p),old)
   assert read(f,rel(p))==old
 finally:f.quit()
 (E/'rollback-used.txt').write_text('FIVE_TARGETS_BYTES_AND_ABSENCE_VERIFIED');custody(False);print('ROLLBACK_BYTE_EXACT_VERIFIED',flush=True)
def command():
 req=urllib.request.Request('https://api.github.com/repos/Ciborio71/BadAround/contents/ops/mapcontrols01s-verdict.json?ref=deploy/map-controls-01s-staging',headers={'Authorization':'Bearer '+os.environ['GH_TOKEN'],'Accept':'application/vnd.github.raw+json'})
 try:
  with urllib.request.urlopen(req,timeout=25) as r:j=json.load(r)
 except urllib.error.HTTPError as e:
  if e.code==404:return None
  raise
 assert j['candidate']==CAND and str(j['run'])==os.environ['GITHUB_RUN_ID'],'VERDICT_SCOPE_MISMATCH';return j
phase=sys.argv[1]
if phase=='prepare':
 custody();f=connect();(E/'rollback').mkdir(exist_ok=True);rows=[]
 try:
  for p in FILES:
   b=read(f,rel(p));assert b==git(BASE,p)
   if b is not None:(E/'rollback'/pathlib.Path(p).name).write_bytes(b)
   rows.append({'path':p,'exists':b is not None,'before_sha256':sha(b),'candidate_sha256':sha(git(CAND,p))})
 finally:f.quit()
 (E/'rollback-manifest.json').write_text(json.dumps(rows,indent=2));print('ROLLBACK_READY_THREE_BYTES_TWO_ABSENCES',flush=True)
elif phase=='install-probe':
 f=connect()
 try:
  assert read(f,PROBE) is None,'QA_PROBE_ALREADY_EXISTS';b=pathlib.Path('ops/mapcontrols01s-probe.php').read_bytes();(E/'probe-installed.txt').write_text(PROBE);put(f,PROBE,b);assert read(f,PROBE)==b
 finally:f.quit()
 print('TEMPORARY_READ_ONLY_PROBE_READY',flush=True)
elif phase=='deploy':
 deadline=time.time()+1800
 while time.time()<deadline:
  c=command()
  if c and c['phase']=='FAIL':raise RuntimeError('PREUPLOAD_GATE_FAILED')
  if c and c['phase']=='UPLOAD':break
  time.sleep(12)
 else:raise RuntimeError('PREUPLOAD_ACK_TIMEOUT')
 custody();f=connect()
 try:
  assert all(read(f,rel(p))==git(BASE,p) for p in FILES);(E/'upload-started.txt').write_text(CAND)
  for p in FILES:put(f,rel(p),git(CAND,p));assert read(f,rel(p))==git(CAND,p),'REMOTE_HASH_MISMATCH'
 finally:f.quit()
 custody(True);(E/'uploaded.txt').write_text(CAND);print('FIVE_REMOTE_HASHES_PASS',flush=True)
elif phase=='watch':
 try:
  deadline=time.time()+3600
  while time.time()<deadline:
   c=command()
   if c and c['phase'] in ['PASS','FAIL']:
    assert c['phase']=='PASS','LIVE_QA_FAILURE';custody(True);(E/'certified.json').write_text(json.dumps(c,indent=2));print('REAL_STAGING_QA_PASS',flush=True);break
   time.sleep(12)
  else:raise RuntimeError('LIVE_QA_TIMEOUT')
 except BaseException:rollback();raise
elif phase=='rollback':rollback()
elif phase=='cleanup':
 f=connect()
 try:
  if (E/'probe-installed.txt').exists() and read(f,PROBE) is not None:f.delete(ROOT+PROBE)
  assert read(f,PROBE) is None,'QA_PROBE_NOT_REMOVED';(E/'temporary-probe-removed.txt').write_text('FTPS_ABSENCE_VERIFIED')
 finally:f.quit()
 print('QA_PROBE_REMOVED_ABSENCE_VERIFIED',flush=True)
