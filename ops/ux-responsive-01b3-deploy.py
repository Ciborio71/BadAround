import ftplib,ssl,os,subprocess,hashlib,io,pathlib,json,sys
ROOT='/staging.badaround.it/wp-content/'
BASE='e7a6b191c60e49ba5f83e6f3a30008f821a05704'
CAND='86d12960b041cedc8b2272c6b18d6a714b0fe8c2'
FILES=[
'themes/badaround-child/assets/css/components.css',
'themes/badaround-child/assets/css/legal.css',
'themes/badaround-child/assets/css/sentinels.css',
'themes/badaround-child/assets/js/sentinels.js',
'themes/badaround-child/functions.php',
'themes/badaround-child/page-sentinelle.php']
E=pathlib.Path('evidence');E.mkdir(exist_ok=True);(E/'rollback').mkdir(exist_ok=True)
def git(ref,p):
 r=subprocess.run(['git','show',ref+':wordpress/'+p],capture_output=True)
 return r.stdout if r.returncode==0 else None
def sha(b):return hashlib.sha256(b).hexdigest() if b is not None else None
def connect():
 f=ftplib.FTP_TLS(context=ssl.create_default_context(cafile='tls/curl-chain.pem'),timeout=60)
 f.connect(os.environ['FTP_HOST'],21);f.host=os.environ['FTP_TLS_NAME'];f.login(os.environ['FTP_USER'],os.environ['FTP_PASS']);f.prot_p();return f
def read(f,p):
 try:
  b=io.BytesIO();f.retrbinary('RETR '+ROOT+p,b.write);return b.getvalue()
 except ftplib.error_perm as e:
  if str(e).startswith('550'):return None
  raise
def put(f,p,b):
 tmp=ROOT+p+'.ux01b3tmp';f.storbinary('STOR '+tmp,io.BytesIO(b));f.rename(tmp,ROOT+p)
def prepare():
 f=connect();rows=[]
 try:
  for p in FILES:
   old=git(BASE,p);remote=read(f,p)
   assert remote==old, 'REMOTE_BASELINE_DRIFT '+p
   if remote is not None:
    dest=E/'rollback'/p;dest.parent.mkdir(parents=True,exist_ok=True);dest.write_bytes(remote)
   rows.append({'path':p,'exists':remote is not None,'before':sha(remote),'candidate':sha(git(CAND,p))})
 finally:f.quit()
 (E/'manifest.json').write_text(json.dumps(rows,indent=2));print('PREPARE_PASS',len(rows),flush=True)
def deploy():
 rows=json.loads((E/'manifest.json').read_text());f=connect()
 try:
  for p in FILES:
   put(f,p,git(CAND,p));assert read(f,p)==git(CAND,p), 'REMOTE_VERIFY '+p
 finally:f.quit()
 (E/'uploaded.txt').write_text(CAND);print('DEPLOY_PASS',len(FILES),flush=True)
def verify():
 f=connect();rows=[]
 try:
  for p in FILES:
   remote=read(f,p);expected=git(CAND,p);rows.append({'path':p,'remote':sha(remote),'expected':sha(expected),'match':remote==expected});assert remote==expected,p
 finally:f.quit()
 (E/'verified.json').write_text(json.dumps(rows,indent=2));print('VERIFY_PASS',len(rows),flush=True)
def rollback():
 if not (E/'manifest.json').exists() or not (E/'uploaded.txt').exists():return
 rows=json.loads((E/'manifest.json').read_text());f=connect()
 try:
  for row in reversed(rows):
   p=row['path']
   if row['exists']:
    old=(E/'rollback'/p).read_bytes();put(f,p,old);assert read(f,p)==old
   else:
    if read(f,p) is not None:f.delete(ROOT+p)
    assert read(f,p) is None
 finally:f.quit()
 print('ROLLBACK_PASS',flush=True)
phase=sys.argv[1]
{'prepare':prepare,'deploy':deploy,'verify':verify,'rollback':rollback}[phase]()
