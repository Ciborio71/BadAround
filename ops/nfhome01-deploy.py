import ftplib,ssl,os,pathlib,subprocess,json,hashlib,io,sys,time,urllib.request,urllib.error
ROOT='/staging.badaround.it/wp-content/themes/badaround-child/'
CAND='a4ef16aa067afc94a96cacd55a46a62d1248ed93'
BASE='d0ac50bf4866b326943f3f4487b49fbe3d878d7b'
FILES=['front-page.php','assets/css/home.css']
e=pathlib.Path('evidence');e.mkdir(exist_ok=True)
def git(ref,p):return subprocess.check_output(['git','show',ref+':wordpress/themes/badaround-child/'+p])
def sha(b):return hashlib.sha256(b).hexdigest()
def connect():
 f=ftplib.FTP_TLS(context=ssl.create_default_context(cafile='tls/curl-chain.pem'),timeout=60);f.connect(os.environ['FTP_HOST'],21);f.host=os.environ['FTP_TLS_NAME'];f.login(os.environ['FTP_USER'],os.environ['FTP_PASS']);f.prot_p();return f
def read(f,p):
 b=io.BytesIO();f.retrbinary('RETR '+ROOT+p,b.write);return b.getvalue()
def put(f,p,b):
 temp=ROOT+p+'.nfhome01-tmp';f.storbinary('STOR '+temp,io.BytesIO(b));f.rename(temp,ROOT+p)
def restore():
 f=connect()
 try:
  for i,p in enumerate(FILES):
   b=(e/'rollback'/str(i)).read_bytes();assert b==git(BASE,p);put(f,p,b);assert read(f,p)==b
  (e/'rollback-used.txt').write_text('TWO FILES BYTE EXACT VERIFIED')
  print('ROLLBACK_BYTE_EXACT_VERIFIED',flush=True)
 finally:f.quit()
phase=sys.argv[1]
if phase=='prepare':
 f=connect();(e/'rollback').mkdir(exist_ok=True);rows=[]
 try:
  for i,p in enumerate(FILES):
   b=read(f,p);assert b==git(BASE,p),'BASELINE_DRIFT_'+p
   (e/'rollback'/str(i)).write_bytes(b);assert (e/'rollback'/str(i)).read_bytes()==b
   rows.append(dict(path=p,before=sha(b),candidate=sha(git(CAND,p))))
  (e/'manifest.json').write_text(json.dumps(rows,indent=2))
 finally:f.quit()
 print('ROLLBACK_READY_TWO_FILES',flush=True)
elif phase=='deploy':
 try:
  f=connect()
  try:
   for i,p in enumerate(FILES):assert read(f,p)==(e/'rollback'/str(i)).read_bytes()
   (e/'upload-started.txt').write_text(CAND)
   rows=[]
   for p in FILES:
    b=git(CAND,p);put(f,p,b);r=read(f,p);assert r==b
    rows.append(dict(path=p,remote_sha256=sha(r),candidate_sha256=sha(b),match=True))
   (e/'remote-hashes.json').write_text(json.dumps(rows,indent=2))
  finally:f.quit()
 except BaseException:
  restore();raise
 print('DEPLOY_TWO_REMOTE_HASHES_PASS',flush=True)
elif phase=='watch':
 try:
  deadline=time.time()+1800
  while time.time()<deadline:
   req=urllib.request.Request('https://api.github.com/repos/Ciborio71/BadAround/contents/ops/nfhome01-staging-verdict.json?ref=deploy/nf-home-01-staging',headers={'Authorization':'Bearer '+os.environ['GH_TOKEN'],'Accept':'application/vnd.github.raw+json'})
   try:
    with urllib.request.urlopen(req,timeout=25) as r:d=json.load(r)
   except urllib.error.HTTPError as ex:
    if ex.code!=404:raise
   else:
    assert d['candidate']==CAND and d['run']==os.environ['GITHUB_RUN_ID']
    if d['verdict']!='PASS':raise RuntimeError('BROWSER_QA_FAILURE')
    f=connect()
    try:
     for p in FILES:assert read(f,p)==git(CAND,p)
    finally:f.quit()
    (e/'certified.txt').write_text(json.dumps(d));print('STAGING_QA_ACK_AND_FINAL_HASHES_PASS',flush=True);break
   time.sleep(15)
  else:raise RuntimeError('QA_ACK_TIMEOUT')
 except BaseException:
  restore();raise
elif phase=='rollback':
 if (e/'upload-started.txt').exists() and not (e/'rollback-used.txt').exists():restore()
