import ftplib,ssl,os,pathlib,subprocess,json,hashlib,io,sys,secrets,urllib.request
ROOT='/staging.badaround.it/';BASE='0d1db0d7fab7f2facbe6b07ccff002b9e884cade';CAND='ba8586b2dbc0315fc251b0ec5fcc96c7a2426124';TARGET='plugins/badaround-core/badaround-core.php';HASH='dc282153243ffb80f3bd4f2a222ea0f4c4f42806fba6f3a0bde0a76855bd8e3c'
e=pathlib.Path('evidence');e.mkdir(exist_ok=True)
def git(*a):return subprocess.check_output(['git',*a])
def sha(b):return hashlib.sha256(b).hexdigest()
f=ftplib.FTP_TLS(context=ssl.create_default_context(cafile='tls/curl-chain.pem'),timeout=60);f.connect(os.environ['FTP_HOST'],21);f.host=os.environ['FTP_TLS_NAME'];f.login(os.environ['FTP_USER'],os.environ['FTP_PASS']);f.prot_p()
def read(p):
 b=io.BytesIO();f.retrbinary('RETR '+ROOT+p,b.write);return b.getvalue()
def put(p,b):
 tmp=ROOT+p+'.nf03a-tmp';f.storbinary('STOR '+tmp,io.BytesIO(b));f.rename(tmp,ROOT+p)
def custody(commit,label):
 paths=git('diff','--name-only','08f96d6f4dbd8ce8294e219857002f975351acf4',commit,'--','wordpress/plugins/badaround-core').decode().splitlines()
 paths=sorted(set(paths+['wordpress/plugins/badaround-core/'+p for p in ['badaround-core.php']+['includes/class-badaround-'+n+'.php' for n in ['core','report-schema','report-persistence-service','native-report-golden-path-service','native-media-config','native-media-ledger','territory-resolver','publication-service','public-geo-contract','public-geo-projection','geo-moderation-service']]]))
 paths += ['wordpress/themes/badaround-child/'+p for p in ['inc/native-report.php','assets/js/native-report/wizard.js','assets/css/native-report.css']]
 rows=[]
 for p in paths:
  ref=BASE if '/themes/' in p else commit
  expected=git('show',ref+':'+p);remote=read('wp-content/'+p.removeprefix('wordpress/'));rows.append(dict(path=p,expected=sha(expected),remote=sha(remote),match=expected==remote))
 (e/('custody-'+label+'.json')).write_text(json.dumps(rows,indent=2));assert all(r['match'] for r in rows),'RUNTIME_DRIFT_'+label
 print('CUSTODY_PASS',label,len(rows),flush=True)
def probe():
 name='nf03a-probe-'+secrets.token_hex(20)+'.php';token=secrets.token_hex(32);body=pathlib.Path('ops/nf03a-probe.php').read_text().replace('__TOKEN__',token).encode()
 try:
  put(name,body);assert read(name)==body
  req=urllib.request.Request('https://staging.badaround.it/'+name,headers={'X-NF03A-Token':token,'Cache-Control':'no-cache'})
  with urllib.request.urlopen(req,timeout=60) as r:return json.load(r)
 finally:
  f.delete(ROOT+name)
  try:read(name)
  except ftplib.error_perm as ex:
   if not str(ex).startswith('550'):raise
  else:raise RuntimeError('PROBE_NOT_REMOVED')
phase=sys.argv[1];new=git('show',CAND+':wordpress/'+TARGET)
if phase=='prepare':
 custody(BASE,'before');old=read('wp-content/'+TARGET);assert old==git('show',BASE+':wordpress/'+TARGET)
 (e/'rollback-core.php').write_bytes(old);assert (e/'rollback-core.php').read_bytes()==old
 (e/'manifest.json').write_text(json.dumps(dict(candidate=CAND,path=TARGET,before=sha(old),after=sha(new)),indent=2))
 b=probe();assert b['core_loaded'] and b['native_version']=='badaround-report/v1' and b['json_hash']==HASH
 (e/'bootstrap-before.json').write_text(json.dumps(b,indent=2));print('BYTE_EXACT_ROLLBACK_AND_BASELINE_PASS',flush=True)
elif phase=='deploy':
 old=(e/'rollback-core.php').read_bytes();m=json.loads((e/'manifest.json').read_text());assert sha(old)==m['before'] and sha(new)==m['after'];assert read('wp-content/'+TARGET)==old
 try:
  put('wp-content/'+TARGET,new);assert read('wp-content/'+TARGET)==new
  (e/'uploaded.txt').write_text(CAND)
  a=probe();b=json.loads((e/'bootstrap-before.json').read_text());(e/'bootstrap-after.json').write_text(json.dumps(a,indent=2))
  assert a['core_loaded'] and a['class_exists'] and a['matrix_count']==33 and a['automatic_count']==9
  assert a['json_hash']==a['contract_constant']==HASH and a['native_version']=='badaround-report/v1' and not a['v2_implicitly_loaded']
  assert a['v1_components']==['BadAround_Report_Normalizer','BadAround_Report_Validator']
  assert a['state']==b['state'] and a['schema']==b['schema'],'DB_OR_TAXONOMY_DRIFT'
  print('REAL_BOOTSTRAP_FROZEN_CONTRACT_V1_AND_PROTECTED_STATE_PASS',flush=True)
 except BaseException:
  put('wp-content/'+TARGET,old);assert read('wp-content/'+TARGET)==old;(e/'rollback-executed.txt').write_text('BYTE_EXACT_VERIFIED');raise
elif phase=='verify':
 custody(CAND,'after');a=probe();b=json.loads((e/'bootstrap-before.json').read_text());assert a['state']==b['state'] and a['schema']==b['schema'];assert a['class_exists'] and a['json_hash']==HASH
 (e/'final-verification.json').write_text(json.dumps(a,indent=2));(e/'remote-sha256.txt').write_text(sha(read('wp-content/'+TARGET))+'  '+TARGET+'\n');(e/'success.txt').write_text(CAND+'\nPROBE_REMOVED\nONE_RUNTIME_FILE_ONLY\nNO_REPORT_OR_EVENT_CREATED\n');print('NF03A_STAGING_PASS',flush=True)
elif phase=='rollback':
 if (e/'uploaded.txt').exists():
  old=(e/'rollback-core.php').read_bytes();put('wp-content/'+TARGET,old);assert read('wp-content/'+TARGET)==old;(e/'rollback-executed.txt').write_text('BYTE_EXACT_VERIFIED');print('ROLLBACK_BYTE_EXACT_VERIFIED',flush=True)
f.quit()
