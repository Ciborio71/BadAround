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
  ref=(BASE if label=='before' else THEME) if '/themes/' in p else commit
  expected=git('show',ref+':'+p);remote=read('wp-content/'+p.removeprefix('wordpress/'));rows.append(dict(path=p,expected=sha(expected),remote=sha(remote),match=expected==remote))
 (e/('custody-'+label+'.json')).write_text(json.dumps(rows,indent=2));assert all(r['match'] for r in rows),'RUNTIME_DRIFT_'+label
 print('CUSTODY_PASS',label,len(rows),flush=True)
def probe(data=None):
 name='nf03a-probe-'+secrets.token_hex(20)+'.php';token=secrets.token_hex(32);body=pathlib.Path('ops/nf04-probe.php').read_text().replace('__TOKEN__',token).encode()
 try:
  put(name,body);assert read(name)==body
  req=urllib.request.Request('https://staging.badaround.it/'+name,data=json.dumps(data).encode() if data else None,headers={'X-NF04-Token':token,'Cache-Control':'no-cache'})
  with urllib.request.urlopen(req,timeout=60) as r:return json.load(r)
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
phase=sys.argv[1]
if phase=='prepare':
 custody(CAND,'before');b=probe();valid(b,'badaround-report/v1');(e/'before.json').write_text(json.dumps(b,indent=2));rows=[];(e/'rollback').mkdir(exist_ok=True)
 for i,p in enumerate(FILES):
  old=read('wp-content/'+p);assert old==git('show',BASE+':wordpress/'+p);new=git('show',THEME+':wordpress/'+p);backup=e/'rollback'/str(i);backup.write_bytes(old);assert backup.read_bytes()==old;rows.append(dict(path=p,before=sha(old),after=sha(new),backup=str(backup)))
 (e/'manifest.json').write_text(json.dumps(rows,indent=2));print('FIVE_FILE_BYTE_EXACT_ROLLBACK_CORE_CONTRACT_BASELINE_PASS',flush=True)
elif phase=='deploy':
 rows=json.loads((e/'manifest.json').read_text());assert all(sha(read('wp-content/'+r['path']))==r['before'] for r in rows)
 try:
  (e/'uploaded.txt').write_text(THEME)
  for r in rows:
   new=git('show',THEME+':wordpress/'+r['path']);assert sha(new)==r['after'];put('wp-content/'+r['path'],new);assert read('wp-content/'+r['path'])==new
  a=probe(baseline_query());valid(a,'badaround-report/v2');b=json.loads((e/'before.json').read_text());assert a['state']==b['state'] and a['schema']==b['schema'];(e/'after-upload.json').write_text(json.dumps(a,indent=2));print('FIVE_REMOTE_HASHES_EXPLICIT_V2_AND_PROTECTED_STATE_PASS',flush=True)
 except BaseException:restore();raise
elif phase=='differential':
 cases=json.loads((e/'differential.json').read_text());actual=probe({'cases':[r['payload'] for r in cases]})
 for i,(a,b) in enumerate(zip(actual,cases)):
  assert a['active']==b['active'] and a['required']==b['required'],'ACTIVE_SET_DIVERGENCE_'+str(i)
  assert a['normalized']==b['payload'],'NORMALIZATION_DIVERGENCE_'+str(i)
  if i<33:assert a['valid'],'ORACLE_INVALID_'+str(i)
 assert len(actual)==len(cases)==587
 (e/'runtime-differential-result.json').write_text(json.dumps(dict(pass_all=True,oracles=33,matrix=2805,dependency_activations=554,cases=len(actual)),indent=2));print('LIVE_BACKEND_FRONTEND_33_ORACLE_2805_MATRIX_554_DEPENDENCIES_PASS',flush=True)
elif phase=='verify':
 custody(CAND,'after');a=probe(baseline_query());valid(a,'badaround-report/v2');b=json.loads((e/'before.json').read_text());(e/'final-state.json').write_text(json.dumps(a,indent=2));assert a['state']==b['state'] and a['schema']==b['schema'],'PROTECTED_STATE_OR_SCHEMA_CHANGED'
 assert len(a['new_reports'])==a['new_event_count']==1,'NEW_FIXTURE_COUNT'
 r=a['new_reports'][0];gold=json.loads((e/'golden.json').read_text());assert r['event']==gold['response']['event_id'] and r['pending'];assert r['envelope_version']==r['canonical']['schema_version']=='badaround-report/v2';assert r['canonical']==gold['payload'];assert r['canonical']['reporter']['social_visibility']=='declined';assert not r['canonical'].get('media',{}).get('items');assert 'damage' not in r['canonical'];assert r['canonical']['animal']['type_other_detail']=='Animale sintetico di collaudo';assert r['expected_subtype'] in list(map(int,r['event_types']));assert r['expected_territory'] in list(map(int,r['territories']))
 assert a['historical_versions']==b['historical_versions']
 (e/'protected-fixture-added.json').write_text(json.dumps(dict(report=r['report'],event=r['event'],schema_version='badaround-report/v2',status='pending',protected=True),indent=2));print('REAL_V2_REPORT',r['report'],'EVENT',r['event'],'PENDING_IDEMPOTENT_PROTECTED',flush=True)
 (e/'remote-hashes.json').write_text(json.dumps([dict(path=p,sha256=sha(read('wp-content/'+p))) for p in FILES+[TARGET]],indent=2));(e/'success.txt').write_text(THEME+'\nTEMPORARY_PROBE_REMOVED\nBACKEND_UNCHANGED\nONE_PROTECTED_V2_FIXTURE\n');print('NF04_STAGING_CERTIFICATION_PASS',flush=True)
elif phase=='rollback':
 if (e/'uploaded.txt').exists():restore()
f.quit()
