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
  ref=(BASE if label=='before' else THEME) if '/themes/' in p else commit
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
THEME='8077c291281ca35f8f26d6f78563de5d7fa87112'
FILES=['themes/badaround-child/'+p for p in ['inc/native-report.php','assets/js/native-report/wizard.js','assets/css/native-report.css']]
def valid(a):
 assert a['core_loaded'] and a['class_exists'] and a['matrix_count']==33 and a['automatic_count']==9
 assert a['json_hash']==a['contract_constant']==HASH and a['native_version']=='badaround-report/v1' and not a['v2_implicitly_loaded']
 assert a['v1_components']==['BadAround_Report_Normalizer','BadAround_Report_Validator']
def restore():
 for row in json.loads((e/'manifest.json').read_text()):
  old=pathlib.Path(row['backup']).read_bytes();assert sha(old)==row['before'];put('wp-content/'+row['path'],old);assert read('wp-content/'+row['path'])==old
 assert read('wp-content/'+TARGET)==git('show',CAND+':wordpress/'+TARGET)
 (e/'rollback-executed.txt').write_text('THREE_THEME_FILES_BYTE_EXACT_VERIFIED; CORE_UNCHANGED')
 print('ROLLBACK_BYTE_EXACT_VERIFIED',flush=True)
phase=sys.argv[1]
if phase=='prepare':
 custody(CAND,'before');b=probe();valid(b);(e/'bootstrap-before.json').write_text(json.dumps(b,indent=2))
 rows=[];(e/'rollback').mkdir(exist_ok=True)
 for i,p in enumerate(FILES):
  old=read('wp-content/'+p);assert old==git('show',BASE+':wordpress/'+p)
  new=git('show',THEME+':wordpress/'+p);backup=e/'rollback'/str(i);backup.write_bytes(old);assert backup.read_bytes()==old
  rows.append(dict(path=p,before=sha(old),after=sha(new),backup=str(backup)))
 (e/'manifest.json').write_text(json.dumps(rows,indent=2));print('THREE_FILE_BYTE_EXACT_ROLLBACK_READY_CORE_NF03A_PASS',flush=True)
elif phase=='deploy':
 rows=json.loads((e/'manifest.json').read_text())
 for row in rows:assert sha(read('wp-content/'+row['path']))==row['before']
 assert read('wp-content/'+TARGET)==git('show',CAND+':wordpress/'+TARGET)
 try:
  (e/'uploaded.txt').write_text(THEME)
  for row in rows:
   new=git('show',THEME+':wordpress/'+row['path']);assert sha(new)==row['after'];put('wp-content/'+row['path'],new);assert read('wp-content/'+row['path'])==new
  a=probe();valid(a);b=json.loads((e/'bootstrap-before.json').read_text());assert a['state']==b['state'] and a['schema']==b['schema']
  (e/'bootstrap-after.json').write_text(json.dumps(a,indent=2));print('THREE_THEME_HASHES_AND_REAL_BOOTSTRAP_PASS',flush=True)
 except BaseException:restore();raise
elif phase=='verify':
 custody(CAND,'after');a=probe();valid(a);b=json.loads((e/'bootstrap-before.json').read_text());assert a['state']==b['state'] and a['schema']==b['schema']
 (e/'final-verification.json').write_text(json.dumps(a,indent=2))
 hashes=[dict(path=p,sha256=sha(read('wp-content/'+p))) for p in FILES+[TARGET]]
 (e/'remote-hashes.json').write_text(json.dumps(hashes,indent=2))
 (e/'success.txt').write_text(THEME+'\nCORE_NF03A_UNCHANGED\nPROBE_REMOVED\nNO_REPORT_OR_EVENT_CREATED\n');print('NF03_STAGING_CERTIFICATION_PASS',flush=True)
elif phase=='rollback':
 if (e/'uploaded.txt').exists():restore()
f.quit()
