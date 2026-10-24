import ftplib,ssl,os,pathlib,subprocess,json,hashlib,io,sys,time,urllib.request,urllib.error
CAND='0c2863b2a1bfbfb1e5c9641c78ba8d28bd21f21c';BRANCH='deploy/ui-admin-01s-staging'
ROOT='/staging.badaround.it/wp-content/'
FILES=['wordpress/plugins/badaround-core/assets/css/classic-editor-publish-box.css','wordpress/plugins/badaround-core/includes/class-badaround-moderation-admin.php']
E=pathlib.Path('evidence/ui-admin-01s');E.mkdir(parents=True,exist_ok=True)
S=json.loads(pathlib.Path('ops/ui-admin-01s-custody.json').read_text())
def sha(b):return hashlib.sha256(b).hexdigest() if b is not None else None
def git(p):return subprocess.check_output(['git','show',CAND+':'+p])
def rel(p):assert p.startswith('wordpress/');return p[10:]
def connect():
 f=ftplib.FTP_TLS(context=ssl.create_default_context(cafile='tls/curl-chain.pem'),timeout=45);f.connect(os.environ['FTP_HOST'],21);f.host=os.environ['FTP_TLS_NAME'];f.login(os.environ['FTP_USER'],os.environ['FTP_PASS']);f.prot_p();return f
def read(f,p):
 parent,name=p.rsplit('/',1)
 try:listing=f.nlst(ROOT+parent)
 except ftplib.error_perm:
  pp,d=parent.rsplit('/',1)
  if d not in [x.rstrip('/').rsplit('/',1)[-1] for x in f.nlst(ROOT+pp)]:return None
  raise
 if name not in [x.rstrip('/').rsplit('/',1)[-1] for x in listing]:return None
 b=io.BytesIO();f.retrbinary('RETR '+ROOT+p,b.write);return b.getvalue()
def put(f,p,b):
 parent=p.rsplit('/',1)[0]
 try:f.cwd(ROOT+parent)
 except ftplib.error_perm:
  # Only the new stylesheet's parent may need creation.
  assert parent=='plugins/badaround-core/assets/css'
  f.mkd(ROOT+parent)
 f.storbinary('STOR '+ROOT+p,io.BytesIO(b))
def custody(f,after):
 rows=[]
 for x in S:
  p=x['path'];b=read(f,rel(p));expected=sha(git(p)) if p in FILES and after else x['remote']
  rows.append({'path':p,'remote':sha(b),'expected':expected,'match':sha(b)==expected})
 (E/('custody-after.json' if after else 'custody-before.json')).write_text(json.dumps(rows,indent=2));assert all(x['match'] for x in rows),'UNEXPLAINED_RUNTIME_DRIFT';print('CUSTODY_PASS',len(rows),flush=True)
def command():
 u='https://api.github.com/repos/Ciborio71/BadAround/contents/ops/ui-admin-01s-verdict.json?ref='+BRANCH
 req=urllib.request.Request(u,headers={'Authorization':'Bearer '+os.environ['GH_TOKEN'],'Accept':'application/vnd.github.raw+json'})
 try:
  with urllib.request.urlopen(req,timeout=25) as r:d=json.load(r)
 except urllib.error.HTTPError as e:
  if e.code==404:return None
  raise
 assert d['candidate']==CAND and str(d['run'])==os.environ['GITHUB_RUN_ID'],'VERDICT_SCOPE_MISMATCH';return d
def rollback():
 if not (E/'upload-started.txt').exists() or (E/'rollback-used.txt').exists():return
 f=connect()
 try:
  for x in json.loads((E/'targets-before.json').read_text()):
   p=x['path'];old=(E/'rollback'/pathlib.Path(p).name).read_bytes() if x['exists'] else None
   current=read(f,rel(p));assert sha(current) in [x['before_sha256'],x['candidate_sha256']],'ROLLBACK_TARGET_DRIFT'
   if old is None:
    if current is not None:f.delete(ROOT+rel(p))
   else:put(f,rel(p),old)
   assert read(f,rel(p))==old
  custody(f,False)
 finally:f.quit()
 (E/'rollback-used.txt').write_text('BOTH_TARGETS_BYTES_AND_ABSENCE_RESTORED');print('ROLLBACK_PASS',flush=True)
phase=sys.argv[1]
if phase=='prepare':
 f=connect();(E/'rollback').mkdir(exist_ok=True);rows=[]
 try:
  # The requested first gate checks exactly the two authorized targets.
  for p in FILES:
   b=read(f,rel(p));rows.append({'path':p,'exists':b is not None,'before_sha256':sha(b),'candidate_sha256':sha(git(p)),'candidate_match':b==git(p)})
   if b is not None:(E/'rollback'/pathlib.Path(p).name).write_bytes(b)
  (E/'targets-before.json').write_text(json.dumps(rows,indent=2))
  already=all(x['candidate_match'] for x in rows)
  if not already:
   prior={x['path']:x['remote'] for x in S}
   assert all(x['before_sha256']==prior.get(x['path']) for x in rows),'UNEXPLAINED_TARGET_DRIFT'
  custody(f,already)
  (E/'state.json').write_text(json.dumps({'candidate':CAND,'already_present':already,'targets':rows}))
 finally:f.quit()
 print('ALREADY_PRESENT_NOT_DUPLICATED' if already else 'DEPLOY_REQUIRED_ROLLBACK_READY',flush=True)
elif phase=='deploy':
 state=json.loads((E/'state.json').read_text())
 if state['already_present']:print('DEPLOY_ALREADY_PRESENT_NOT_DUPLICATED',flush=True);sys.exit(0)
 deadline=time.time()+2700
 while time.time()<deadline:
  c=command()
  if c and c['phase']=='FAIL':raise RuntimeError('PREUPLOAD_GATE_FAILED')
  if c and c['phase']=='UPLOAD':break
  time.sleep(10)
 else:raise RuntimeError('PREUPLOAD_GATE_TIMEOUT')
 f=connect()
 try:
  custody(f,False)
  for x in state['targets']:assert sha(read(f,rel(x['path'])))==x['before_sha256']
  (E/'upload-started.txt').write_text(CAND)
  for p in FILES:put(f,rel(p),git(p));assert read(f,rel(p))==git(p)
  custody(f,True)
  (E/'uploaded.json').write_text(json.dumps({'candidate':CAND,'remote_hashes':{p:sha(read(f,rel(p))) for p in FILES}}))
 finally:f.quit()
 print('REMOTE_HASHES_2_OF_2_PASS',flush=True)
elif phase=='watch':
 try:
  deadline=time.time()+3600
  while time.time()<deadline:
   c=command()
   if c and c['phase'] in ['PASS','FAIL']:
    assert c['phase']=='PASS','LIVE_QA_FAILURE'
    f=connect()
    try:
     assert all(read(f,rel(p))==git(p) for p in FILES);custody(f,True)
    finally:f.quit()
    (E/'certified.json').write_text(json.dumps(c,indent=2));print('LIVE_ADMIN_QA_PASS',flush=True);break
   time.sleep(10)
  else:raise RuntimeError('LIVE_QA_TIMEOUT')
 except BaseException:rollback();raise
elif phase=='rollback':rollback()
else:raise RuntimeError('Unknown phase')
