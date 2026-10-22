import ftplib,ssl,os,pathlib,subprocess,json,hashlib,io,sys,secrets,urllib.request
ROOT='/staging.badaround.it/';BASE='8077c291281ca35f8f26d6f78563de5d7fa87112';CAND='ba8586b2dbc0315fc251b0ec5fcc96c7a2426124';TARGET='plugins/badaround-core/badaround-core.php';HASH='dc282153243ffb80f3bd4f2a222ea0f4c4f42806fba6f3a0bde0a76855bd8e3c'
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
 paths += ['wordpress/'+p for p in FILES]
 rows=[]
 for p in paths:
  ref=THEME if '/themes/' in p else commit
  expected=git('show',ref+':'+p);remote=read('wp-content/'+p.removeprefix('wordpress/'));rows.append(dict(path=p,expected=sha(expected),remote=sha(remote),match=expected==remote))
 (e/('custody-'+label+'.json')).write_text(json.dumps(rows,indent=2));assert all(r['match'] for r in rows),'RUNTIME_DRIFT_'+label
 print('CUSTODY_PASS',label,len(rows),flush=True)
def probe(data=None):
 route='probe-'+secrets.token_hex(20);name='wp-content/mu-plugins/nf04-'+route+'.php';token=secrets.token_hex(32);body=pathlib.Path('ops/nf05-probe.php').read_text().replace('__TOKEN__',token).replace('__ROUTE__',route).encode()
 try:
  put(name,body);assert read(name)==body
  raw=subprocess.check_output(['node','ops/nf04-probe-client.cjs'],input=json.dumps(dict(url='https://staging.badaround.it/wp-json/nf05-qa/v1/'+route,token=token,data=data)).encode(),timeout=120)
  return json.loads(raw)
 finally:
  f.delete(ROOT+name)
  try:read(name)
  except ftplib.error_perm as ex:
   if not str(ex).startswith('550'):raise
  else:raise RuntimeError('PROBE_NOT_REMOVED')
THEME='d0ac50bf4866b326943f3f4487b49fbe3d878d7b'
FILES=['themes/badaround-child/'+p for p in ['inc/native-report.php','assets/js/native-report/model.js','assets/js/native-report/wizard.js','assets/js/native-report/errors.js','assets/css/native-report.css']]
def valid(a,version):
 assert a['core_loaded'] and a['class_exists'] and a['matrix_count']==33
 assert a['json_hash']==a['constant']==HASH and a['native_version']==version
 assert a['v1_valid'],'V1_BACKEND_REGRESSION'
 assert not a['media_enabled'],'STAGING_MEDIA_NOT_FAIL_CLOSED'
def baseline_query():
 b=json.loads((e/'before.json').read_text());return dict(max_report=b['max_report'],max_post=b['max_post'])
def restore():
 for r in json.loads((e/'manifest.json').read_text()):
  old=pathlib.Path(r['backup']).read_bytes();assert sha(old)==r['before'];put('wp-content/'+r['path'],old);assert read('wp-content/'+r['path'])==old
 assert read('wp-content/'+TARGET)==git('show',CAND+':wordpress/'+TARGET)
 (e/'rollback-executed.txt').write_text('FIVE_THEME_FILES_BYTE_EXACT_VERIFIED; CORE_AND_BACKEND_UNCHANGED')
 # Preserve and document any v2 report already accepted, even if UI must roll back.
 a=probe(baseline_query());(e/'state-after-rollback.json').write_text(json.dumps(a,indent=2));print('ROLLBACK_BYTE_EXACT_VERIFIED',flush=True)
custody(CAND,'before')
names=f.nlst(ROOT+'wp-content/mu-plugins')
remaining=[p for p in names if pathlib.PurePosixPath(p).name.startswith(('nf04-probe-','nf05-probe-'))]
(e/'temporary-harness-cleanup.json').write_text(json.dumps(dict(removed=not remaining,remaining=remaining,application_code_changed=False,real_submissions=0),indent=2))
assert not remaining,'TEMPORARY_PROBE_REMAINS'
print('TEMPORARY_HARNESS_REMOVED_YES; REAL_SUBMISSIONS_ZERO; REMOTE_RUNTIME_UNCHANGED',flush=True)
f.quit()
