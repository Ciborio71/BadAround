import ftplib,ssl,os,pathlib,subprocess,json,hashlib,io,secrets,urllib.request,sys
ROOT='/staging.badaround.it/'
BASE='08f96d6f4dbd8ce8294e219857002f975351acf4'
CAND='1f9e1cc56f120dec6f634fc2ac6233e803623b88'
state=pathlib.Path('evidence'); state.mkdir(exist_ok=True)
def git(*args): return subprocess.check_output(['git',*args])
files=[p.removeprefix('wordpress/') for p in git('diff','--name-only',BASE,CAND,'--','wordpress/plugins/badaround-core').decode().splitlines()]
assert len(files)==16
ctx=ssl.create_default_context(cafile='tls/curl-chain.pem')
ftp=ftplib.FTP_TLS(context=ctx,timeout=60);ftp.connect(os.environ['FTP_HOST'],21);ftp.host=os.environ['FTP_TLS_NAME'];ftp.login(os.environ['FTP_USER'],os.environ['FTP_PASS']);ftp.prot_p()
def read(p):
 b=io.BytesIO();ftp.retrbinary('RETR '+ROOT+p,b.write);return b.getvalue()
def write(p,data):
 parent=ROOT+str(pathlib.PurePosixPath(p).parent)
 try:ftp.mkd(parent)
 except ftplib.error_perm as e:
  if not str(e).startswith('550'):raise
 tmp=ROOT+p+'.nf02b-tmp';ftp.storbinary('STOR '+tmp,io.BytesIO(data));ftp.rename(tmp,ROOT+p)
def delete(p):ftp.delete(ROOT+p)
def sha(b):return hashlib.sha256(b).hexdigest()
if sys.argv[1]=='recover':
 hashes={}
 for p in files:
  try:old=git('show',BASE+':wordpress/'+p)
  except subprocess.CalledProcessError:old=None
  if old is not None:
   write('wp-content/'+p,old);assert read('wp-content/'+p)==old;hashes[p]=sha(old)
  else:
   delete('wp-content/'+p)
 for p in ['nf02b-03c9f97be0beb9e7ffc18e20dfd3bb5f.php.nf02b-tmp']:
  delete(p)
 ftp.rmd(ROOT+'nf02b-03c9f97be0beb9e7ffc18e20dfd3bb5f.php')
 (state/'recovery-hashes.json').write_text(json.dumps(hashes,indent=2))
 (state/'rollback-executed.txt').write_text('RESTORED_AND_VERIFIED')
 print('BASELINE_BYTE_EXACT_RESTORED; TEMPORARY_HARNESS_ARTIFACTS_REMOVED')
 ftp.quit();sys.exit(0)
if sys.argv[1]=='prepare':
 entries=[]
 for p in files:
  try: old=git('show',BASE+':wordpress/'+p)
  except subprocess.CalledProcessError: old=None
  try: remote=read('wp-content/'+p)
  except ftplib.error_perm as e:
   if old is not None or not str(e).startswith('550'):raise
   remote=None
  assert remote==old,'remote baseline drift: '+p
  if remote is not None:
   dest=state/'rollback'/p;dest.parent.mkdir(parents=True,exist_ok=True);dest.write_bytes(remote)
  new=git('show',CAND+':wordpress/'+p)
  entries.append(dict(path=p,existed=remote is not None,before=sha(remote) if remote is not None else None,after=sha(new)))
 (state/'manifest.json').write_text(json.dumps(entries,indent=2))
 print('ROLLBACK_READY files='+str(len(entries)))
else:
 entries=json.loads((state/'manifest.json').read_text()); touched=[];harness=None
 try:
  # Dependencies first; unchanged v1 can continue using old entry points.
  entries.sort(key=lambda e:e['existed'])
  for e in entries:
   touched.append(e);p=e['path'];write('wp-content/'+p,git('show',CAND+':wordpress/'+p));assert sha(read('wp-content/'+p))==e['after']
  hashes={e['path']:sha(read('wp-content/'+e['path'])) for e in entries};(state/'remote-sha256.json').write_text(json.dumps(hashes,indent=2))
  token=secrets.token_hex(32);harness='wp-content/mu-plugins/nf02b-'+secrets.token_hex(16)+'.php'
  body=pathlib.Path('ops/nf02b-qa.php').read_text().replace('__TOKEN__',token).encode();write(harness,body)
  req=urllib.request.Request('https://staging.badaround.it/wp-json/nf02b-qa/v1/run',data=b'',headers={'X-NF-QA':token},method='POST')
  try:
   with urllib.request.urlopen(req,timeout=120) as r:raw=r.read()
  except urllib.error.HTTPError as e:raw=e.read()
  (state/'staging-qa.json').write_bytes(raw)
  try:result=json.loads(raw)
  except Exception:raise RuntimeError('QA returned non-JSON; evidence saved')
  print(json.dumps(result));assert result.get('pass') is True,'QA failed'
  delete(harness);harness=None
  (state/'deploy-success.txt').write_text(CAND+'\n');print('DEPLOY_AND_QA_PASS')
 except BaseException:
  if harness:
   try:delete(harness)
   except Exception:print('Harness cleanup failed; runtime rollback continues')
  for e in reversed(touched):
   p='wp-content/'+e['path']
   if e['existed']:
    data=(state/'rollback'/e['path']).read_bytes();write(p,data);assert read(p)==data
   else:
    try:delete(p)
    except ftplib.error_perm as ex:
     if not str(ex).startswith('550'):raise
  (state/'rollback-executed.txt').write_text('RESTORED_AND_VERIFIED\n');raise
ftp.quit()
