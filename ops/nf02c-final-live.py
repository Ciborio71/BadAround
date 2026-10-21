import ftplib,ssl,os,pathlib,subprocess,json,hashlib,io,secrets,time,shutil
ROOT='/staging.badaround.it/';CAND='0d1db0d7fab7f2facbe6b07ccff002b9e884cade'
e=pathlib.Path('evidence-live');e.mkdir(exist_ok=True)
f=ftplib.FTP_TLS(context=ssl.create_default_context(cafile='tls/curl-chain.pem'),timeout=60);f.connect(os.environ['FTP_HOST'],21);f.host=os.environ['FTP_TLS_NAME'];f.login(os.environ['FTP_USER'],os.environ['FTP_PASS']);f.prot_p()
def read(p):
 b=io.BytesIO();f.retrbinary('RETR '+ROOT+p,b.write);return b.getvalue()
def sha(b):return hashlib.sha256(b).hexdigest()
def git(*a):return subprocess.check_output(['git',*a])
def custody(label):
 files=git('diff','--name-only','08f96d6f4dbd8ce8294e219857002f975351acf4',CAND,'--','wordpress/plugins/badaround-core').decode().splitlines()
 extra=['badaround-core.php']+['includes/class-badaround-'+n+'.php' for n in ['core','report-schema','report-persistence-service','native-report-golden-path-service','native-media-config','native-media-ledger','territory-resolver','publication-service','public-geo-contract','public-geo-projection','geo-moderation-service']]
 files=sorted(set(files+['wordpress/plugins/badaround-core/'+p for p in extra]));rows=[]
 for p in files:
  data=read('wp-content/'+p.removeprefix('wordpress/'));expected=git('show',CAND+':'+p);rows.append(dict(path=p,expected=sha(expected),remote=sha(data),match=data==expected))
 (e/('runtime-'+label+'.json')).write_text(json.dumps(rows,indent=2));assert all(r['match'] for r in rows),'RUNTIME_DRIFT_'+label
 print('RUNTIME_CUSTODY_PASS '+label+' files='+str(len(rows)),flush=True)
def put(p,b):f.storbinary('STOR '+ROOT+p,io.BytesIO(b))
work='nf-final-'+secrets.token_hex(16);harness='wp-content/mu-plugins/'+work+'.php';workpath='wp-content/mu-plugins/'+work;result='wp-content/mu-plugins/'+work+'-result.json';installed=False
try:
 custody('before');body=pathlib.Path('ops/nf02c-final-live.php').read_text().replace('__WORKDIR__',work).replace('__RESULT_FILE__',work+'-result.json').encode();put(harness,body);installed=True;assert read(harness)==body
 print('FINAL_LIVE_HARNESS_READY; admin-only /nf-final-qa/v1/start',flush=True)
 deadline=time.monotonic()+1050
 while True:
  try:raw=read(result);break
  except ftplib.error_perm as ex:
   if not str(ex).startswith('550') or time.monotonic()>deadline:raise
   time.sleep(5)
 (e/'staging-qa.json').write_bytes(raw);r=json.loads(raw);print('LIVE_RESULT pass='+str(r.get('pass')),flush=True);assert r.get('pass') is True,'STAGING_RESIDUAL_FAILURE'
finally:
 # Only temporary harness/config/results; never remove QA reports/events or runtime files.
 cleanup=True
 try:
  for base,dirs in list(f.mlsd(ROOT+workpath)):
   if base in ['.','..']:continue
   if dirs.get('type')=='dir':f.rmd(ROOT+workpath+'/'+base)
   else:f.delete(ROOT+workpath+'/'+base)
  f.rmd(ROOT+workpath)
 except ftplib.error_perm as ex:
  if not str(ex).startswith('550'):cleanup=False
 for p in [harness,result]:
  try:f.delete(ROOT+p)
  except ftplib.error_perm as ex:
   if not str(ex).startswith('550'):cleanup=False
 assert cleanup,'TEMPORARY_HARNESS_CLEANUP_FAILED'
 (e/'cleanup.txt').write_text('TEMPORARY_HARNESS_REMOVED=YES\nNO_APPLICATION_UPLOAD\n')
 custody('after');f.quit()
