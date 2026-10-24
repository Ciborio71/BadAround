import ftplib,ssl,os,pathlib,subprocess,json,hashlib,io,sys,time,urllib.request,urllib.error
CAND='0c2863b2a1bfbfb1e5c9641c78ba8d28bd21f21c';BRANCH='deploy/ui-admin-01t-staging'
ROOT='/staging.badaround.it/wp-content/'
FILES=['wordpress/plugins/badaround-core/assets/css/classic-editor-publish-box.css','wordpress/plugins/badaround-core/includes/class-badaround-moderation-admin.php']
E=pathlib.Path('evidence/nf-final-01');E.mkdir(parents=True,exist_ok=True)

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

files=json.loads(pathlib.Path('ops/nf-final-01-expected-runtime.json').read_text())
f=connect();rows=[]
try:
 for x in files:
  b=read(f,rel(x['path']));rows.append(dict(x,remote=sha(b),match=sha(b)==x['expected']))
finally:f.quit()
(E/'runtime-custody.json').write_text(json.dumps(rows,indent=2))
assert all(x['match'] for x in rows),'UNEXPLAINED_RUNTIME_DRIFT'
print('READ_ONLY_CUSTODY_PASS',len(rows))
