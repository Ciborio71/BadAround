import ftplib,ssl,os,pathlib,subprocess,json,hashlib,io,sys,time,urllib.request,urllib.error
BASE='a4ef16aa067afc94a96cacd55a46a62d1248ed93';CAND='29a202fd47d6ba6b7a8109b6a596a71f7508109f'
ROOT='/staging.badaround.it/wp-content/'
PROBE='mu-plugins/geoprec01r-29a202fd-qa.php'
SCOPE=json.loads(pathlib.Path('ops/geoprec01r-scope.json').read_text());TARGETS=SCOPE['targets'];CUSTODY=SCOPE['custody']
ORDER=['class-badaround-public-geo-contract.php','class-badaround-public-geo-projection.php','class-badaround-publication-service.php','class-badaround-geo-moderation-service.php','class-badaround-moderation-admin.php','class-badaround-audit-log.php','class-badaround-report-repository.php']
FILES=['wordpress/plugins/badaround-core/includes/'+p for p in ORDER[:-1]]+['wordpress/plugins/badaround-core/includes/class-badaround-discovery-query.php','wordpress/themes/badaround-child/template-parts/single-event-layout.php']+['wordpress/plugins/badaround-core/includes/'+ORDER[-1]];assert set(FILES)==set(TARGETS) and len(FILES)==9
E=pathlib.Path('evidence');E.mkdir(exist_ok=True)
def git(ref,p):return subprocess.check_output(['git','show',ref+':'+p])
def sha(b):return hashlib.sha256(b).hexdigest()
def rel(p):assert p.startswith('wordpress/');return p.removeprefix('wordpress/')
def connect():
 f=ftplib.FTP_TLS(context=ssl.create_default_context(cafile='tls/curl-chain.pem'),timeout=45);f.connect(os.environ['FTP_HOST'],21);f.host=os.environ['FTP_TLS_NAME'];f.login(os.environ['FTP_USER'],os.environ['FTP_PASS']);f.prot_p();return f
def read(f,p):
 b=io.BytesIO();f.retrbinary('RETR '+ROOT+p,b.write);return b.getvalue()
def put(f,p,b):
 tmp=ROOT+p+'.geoprec01r-tmp';f.storbinary('STOR '+tmp,io.BytesIO(b));f.rename(tmp,ROOT+p)
def custody(after=False):
 f=connect();rows=[]
 try:
  for p in CUSTODY:
   expected=git(CAND if after and p in TARGETS else BASE,p);remote=read(f,rel(p));rows.append({'path':p,'expected':sha(expected),'remote':sha(remote),'match':expected==remote})
 finally:f.quit()
 (E/('custody-after.json' if after else 'custody-before.json')).write_text(json.dumps(rows,indent=2))
 assert all(r['match'] for r in rows),'UNEXPLAINED_RUNTIME_DRIFT';print('CUSTODY_PASS',len(rows),flush=True)
def rollback():
 if not (E/'upload-started.txt').exists() or (E/'rollback-used.txt').exists():return
 f=connect()
 try:
  for p in reversed(FILES):
   b=(E/'rollback'/pathlib.Path(p).name).read_bytes();assert b==git(BASE,p);put(f,rel(p),b);assert read(f,rel(p))==b
 finally:f.quit()
 (E/'rollback-used.txt').write_text('NINE_FILES_BYTE_EXACT_VERIFIED');custody(False);print('ROLLBACK_BYTE_EXACT_VERIFIED',flush=True)
def command():
 u='https://api.github.com/repos/Ciborio71/BadAround/contents/ops/geoprec01r-staging-verdict.json?ref=deploy/geo-prec-01r-composite'
 req=urllib.request.Request(u,headers={'Authorization':'Bearer '+os.environ['GH_TOKEN'],'Accept':'application/vnd.github.raw+json'})
 try:
  with urllib.request.urlopen(req,timeout=25) as r:j=json.load(r)
 except urllib.error.HTTPError as ex:
  if ex.code==404:return None
  raise
 assert j['candidate']==CAND and str(j['run'])==os.environ['GITHUB_RUN_ID'],'VERDICT_SCOPE_MISMATCH'
 return j
phase=sys.argv[1]
if phase=='prepare':
 custody(False);f=connect();(E/'rollback').mkdir(exist_ok=True);rows=[]
 try:
  for p in FILES:
   b=read(f,rel(p));assert b==git(BASE,p);backup=E/'rollback'/pathlib.Path(p).name;backup.write_bytes(b);assert backup.read_bytes()==b;rows.append({'path':p,'before_sha256':sha(b),'candidate_sha256':sha(git(CAND,p))})
 finally:f.quit()
 (E/'rollback-manifest.json').write_text(json.dumps(rows,indent=2));print('ROLLBACK_READY_NINE_FILES',flush=True)
elif phase=='install-probe':
 f=connect()
 try:
  try:read(f,PROBE)
  except ftplib.error_perm as x:assert str(x).startswith('550')
  else:raise RuntimeError('QA_PROBE_ALREADY_EXISTS')
  b=pathlib.Path('ops/geoprec01r-probe.php').read_bytes();(E/'probe-installed.txt').write_text(PROBE);put(f,PROBE,b);assert read(f,PROBE)==b
 finally:f.quit()
 print('TEMPORARY_ADMIN_TOKEN_PROBE_READY',flush=True)
elif phase=='deploy':
 deadline=time.time()+1800
 while time.time()<deadline:
  c=command()
  if c:
   if c['phase']=='FAIL':raise RuntimeError('PREUPLOAD_GATE_FAILED')
   if c['phase']=='UPLOAD':break
  time.sleep(12)
 else:raise RuntimeError('PREUPLOAD_ACK_TIMEOUT')
 custody(False);f=connect()
 try:
  assert all(read(f,rel(p))==(E/'rollback'/pathlib.Path(p).name).read_bytes() for p in FILES)
  (E/'upload-started.txt').write_text(CAND)
  for p in FILES:
   new=git(CAND,p);put(f,rel(p),new);assert read(f,rel(p))==new,'REMOTE_HASH_MISMATCH'
 finally:f.quit()
 custody(True);(E/'uploaded.txt').write_text(CAND);print('NINE_REMOTE_HASHES_PASS_REPOSITORY_CUTOVER_LAST',flush=True)
elif phase=='watch':
 deadline=time.time()+2400
 try:
  while time.time()<deadline:
   c=command()
   if c and c['phase'] in ['PASS','FAIL']:
    assert c['phase']=='PASS','QA_FAILURE';custody(True);(E/'certified.json').write_text(json.dumps(c,indent=2));print('STAGING_QA_PASS_FINAL_HASHES_PASS',flush=True);break
   time.sleep(12)
  else:raise RuntimeError('QA_TIMEOUT')
 except BaseException:rollback();raise
elif phase=='rollback':rollback()
elif phase=='cleanup':
 f=connect()
 try:
  if (E/'probe-installed.txt').exists():
   try:f.delete(ROOT+PROBE)
   except ftplib.error_perm as x:assert str(x).startswith('550')
   try:read(f,PROBE)
   except ftplib.error_perm as x:assert str(x).startswith('550')
   else:raise RuntimeError('PROBE_NOT_REMOVED')
  (E/'temporary-probe-removed.txt').write_text('ABSENCE_VERIFIED')
 finally:f.quit()
 print('TEMPORARY_QA_PROBE_REMOVED_ABSENCE_VERIFIED',flush=True)
