import ftplib,ssl,os,pathlib,subprocess,json,hashlib,io,secrets,urllib.request,urllib.error,sys,time
ROOT='/staging.badaround.it/'
BASE='1f9e1cc56f120dec6f634fc2ac6233e803623b88';CAND='38c82e3472fc4adb0ab80f8fd1281373aa1ab1e0'
FILE='plugins/badaround-core/includes/class-badaround-report-repository.php'
e=pathlib.Path('evidence');e.mkdir(exist_ok=True)
def git(*a):return subprocess.check_output(['git',*a])
def sha(b):return hashlib.sha256(b).hexdigest()
f=ftplib.FTP_TLS(context=ssl.create_default_context(cafile='tls/curl-chain.pem'),timeout=60);f.connect(os.environ['FTP_HOST'],21);f.host=os.environ['FTP_TLS_NAME'];f.login(os.environ['FTP_USER'],os.environ['FTP_PASS']);f.prot_p()
def read(p):
 b=io.BytesIO();f.retrbinary('RETR '+ROOT+p,b.write);return b.getvalue()
def write(p,data):
 parent=ROOT+str(pathlib.PurePosixPath(p).parent)
 try:f.mkd(parent)
 except ftplib.error_perm as ex:
  if not str(ex).startswith('550'):raise
 tmp=ROOT+p+'.nf02c1-tmp';f.storbinary('STOR '+tmp,io.BytesIO(data));f.rename(tmp,ROOT+p)
def remove(p):f.delete(ROOT+p)
new=git('show',CAND+':wordpress/'+FILE)
if sys.argv[1]=='prepare':
 old=read('wp-content/'+FILE);assert old==git('show',BASE+':wordpress/'+FILE),'BASELINE_DRIFT_STOP'
 (e/'rollback-repository.php').write_bytes(old)
 (e/'manifest.json').write_text(json.dumps(dict(path=FILE,baseline=BASE,candidate=CAND,before=sha(old),after=sha(new)),indent=2))
 print('ONE_FILE_ROLLBACK_READY '+sha(old))
else:
 old=(e/'rollback-repository.php').read_bytes();m=json.loads((e/'manifest.json').read_text());assert sha(old)==m['before'];assert sha(new)==m['after']
 assert read('wp-content/'+FILE)==old,'DRIFT_AFTER_BACKUP_STOP'
 touched=False;harness=None;result_path=None
 try:
  touched=True;write('wp-content/'+FILE,new);assert read('wp-content/'+FILE)==new
  (e/'remote-sha256.txt').write_text(sha(new)+'  '+FILE+'\n')
  token=secrets.token_hex(32);harness='wp-content/mu-plugins/nf02c1-'+secrets.token_hex(16)+'.php'
  result_name='nf02c1-result-'+secrets.token_hex(24)+'.json';result_path='wp-content/mu-plugins/'+result_name
  body=pathlib.Path('ops/nf02c1-staging-qa.php').read_text().replace('__TOKEN__',token).replace('__RESULT_FILE__',result_name).encode();write(harness,body)
  print('ADMIN_HARNESS_READY',flush=True)
  deadline=time.monotonic()+300
  while True:
   try:raw=read(result_path);break
   except ftplib.error_perm as ex:
    if not str(ex).startswith('550') or time.monotonic()>deadline:raise
    time.sleep(5)
  (e/'staging-qa.json').write_bytes(raw);result=json.loads(raw);print(json.dumps(result));assert result.get('pass') is True,'STAGING_GATE_FAILED'
  remove(harness);harness=None;remove(result_path);result_path=None
  assert read('wp-content/'+FILE)==new
  (e/'final-remote-sha256.txt').write_text(sha(new)+'  '+FILE+'\n')
  (e/'success.txt').write_text(CAND+'\nTEMPORARY_HARNESS_REMOVED\n')
  print('NF02C1_STAGING_CERTIFICATION_PASS')
 except BaseException:
  cleanup=True
  for path in [harness,result_path]:
   if path:
    try:remove(path)
    except ftplib.error_perm as ex:
     if not str(ex).startswith('550'):cleanup=False
    except Exception:cleanup=False
  if not cleanup:print('HARNESS_CLEANUP_FAILED; RUNTIME_ROLLBACK_CONTINUES')
  if touched:write('wp-content/'+FILE,old);assert read('wp-content/'+FILE)==old
  (e/'rollback-executed.txt').write_text('BYTE_EXACT_BASELINE_RESTORED\nHARNESS_REMOVED='+str(cleanup)+'\n');raise
f.quit()
