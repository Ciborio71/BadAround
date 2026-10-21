import ftplib,ssl,os,pathlib,subprocess,json,hashlib,io,secrets,urllib.request,urllib.error,sys,time
ROOT='/staging.badaround.it/'
BASE='38c82e3472fc4adb0ab80f8fd1281373aa1ab1e0';CAND='0d1db0d7fab7f2facbe6b07ccff002b9e884cade'
FILE='plugins/badaround-core/includes/class-badaround-report-persistence-service.php'
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
 tmp=ROOT+p+'.nf02c2-tmp';f.storbinary('STOR '+tmp,io.BytesIO(data));f.rename(tmp,ROOT+p)
def remove(p):f.delete(ROOT+p)
def custody(commit,label):
 files=git('diff','--name-only','08f96d6f4dbd8ce8294e219857002f975351acf4',commit,'--','wordpress/plugins/badaround-core').decode().splitlines()
 extra=['badaround-core.php']+['includes/class-badaround-'+n+'.php' for n in ['core','report-schema','report-persistence-service','native-report-golden-path-service','native-media-config','native-media-ledger','territory-resolver','publication-service','public-geo-contract','public-geo-projection','geo-moderation-service']]
 files=sorted(set(files+['wordpress/plugins/badaround-core/'+p for p in extra]));rows=[]
 for p in files:
  data=read('wp-content/'+p.removeprefix('wordpress/'));expected=git('show',commit+':'+p);rows.append(dict(path=p,expected=sha(expected),remote=sha(data),match=data==expected))
 (e/('runtime-'+label+'.json')).write_text(json.dumps(rows,indent=2));assert all(r['match'] for r in rows),'RUNTIME_DRIFT_'+label
 print('RUNTIME_CUSTODY_PASS '+label+' files='+str(len(rows)),flush=True)
new=git('show',CAND+':wordpress/'+FILE)
if sys.argv[1]=='prepare':
 custody(BASE,'before')
 old=read('wp-content/'+FILE);assert old==git('show',BASE+':wordpress/'+FILE),'BASELINE_DRIFT_STOP'
 (e/'rollback-persistence-service.php').write_bytes(old)
 (e/'manifest.json').write_text(json.dumps(dict(path=FILE,baseline=BASE,candidate=CAND,before=sha(old),after=sha(new)),indent=2))
 print('ONE_FILE_ROLLBACK_READY '+sha(old))
else:
 old=(e/'rollback-persistence-service.php').read_bytes();m=json.loads((e/'manifest.json').read_text());assert sha(old)==m['before'];assert sha(new)==m['after']
 assert read('wp-content/'+FILE)==old,'DRIFT_AFTER_BACKUP_STOP'
 touched=False;harness=None;result_path=None
 try:
  touched=True;write('wp-content/'+FILE,new);assert read('wp-content/'+FILE)==new
  (e/'remote-sha256.txt').write_text(sha(new)+'  '+FILE+'\n')
  token=secrets.token_hex(32);harness='wp-content/mu-plugins/nf02c2-'+secrets.token_hex(16)+'.php'
  result_name='nf02c2-result-'+secrets.token_hex(24)+'.json';result_path='wp-content/mu-plugins/'+result_name
  body=pathlib.Path('ops/nf02c2-staging-qa.php').read_text().replace('__TOKEN__',token).replace('__RESULT_FILE__',result_name).encode();write(harness,body)
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
  custody(CAND,'after')
  (e/'final-remote-sha256.txt').write_text(sha(new)+'  '+FILE+'\n')
  (e/'success.txt').write_text(CAND+'\nTEMPORARY_HARNESS_REMOVED\n')
  print('NF02C2_STAGING_CERTIFICATION_PASS')
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
