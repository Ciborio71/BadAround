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
 route='probe-'+secrets.token_hex(20);name='wp-content/mu-plugins/nf04-'+route+'.php';token=secrets.token_hex(32);body=pathlib.Path('ops/nf05r-probe.php').read_text().replace('__TOKEN__',token).replace('__ROUTE__',route).encode()
 try:
  put(name,body);assert read(name)==body
  raw=subprocess.check_output(['node','ops/nf05r-probe-client.cjs'],input=json.dumps(dict(url='https://staging.badaround.it/wp-json/nf05-qa/v1/'+route,token=token,data=data)).encode(),timeout=120)
  return json.loads(raw)
 finally:
  f.delete(ROOT+name)
  try:read(name)
  except ftplib.error_perm as ex:
   if not str(ex).startswith('550'):raise
  else:raise RuntimeError('PROBE_NOT_REMOVED')
  with (e/'probe-cleanup.jsonl').open('a') as log:log.write(json.dumps(dict(removed=True,utc=__import__('datetime').datetime.now(__import__('datetime').timezone.utc).isoformat()))+'\n')
THEME='d0ac50bf4866b326943f3f4487b49fbe3d878d7b'
FILES=['themes/badaround-child/'+p for p in ['inc/native-report.php','assets/js/native-report/model.js','assets/js/native-report/wizard.js','assets/js/native-report/errors.js','assets/css/native-report.css']]
def valid(a,version):
 assert a['core_loaded'] and a['class_exists'] and a['matrix_count']==33
 assert a['json_hash']==a['constant']==HASH and a['native_version']==version
 assert a['v1_valid'],'V1_BACKEND_REGRESSION'
 assert not a['media_enabled'],'STAGING_MEDIA_NOT_FAIL_CLOSED'
def baseline_query():
 b=json.loads((e/'before.json').read_text());return dict(max_report=b['max_report'],max_post=b['max_post'])
phase=sys.argv[1]
if phase=='preflight':
 route='probe-'+secrets.token_hex(20);name='wp-content/mu-plugins/nf05r-'+route+'.php';token=secrets.token_hex(32)
 child=subprocess.Popen(['node','ops/nf05r-preflight.cjs'],stdin=subprocess.PIPE,stdout=subprocess.PIPE,text=True)
 uploaded=False
 try:
  ready=child.stdout.readline().strip()
  if ready!='READY_FOR_PROBE':
   child.wait(timeout=60);raise RuntimeError('ACCESS_PREFLIGHT_PUBLIC_FAILED')
  body=pathlib.Path('ops/nf05r-probe.php').read_text().replace('__TOKEN__',token).replace('__ROUTE__',route).encode();put(name,body);uploaded=True;assert read(name)==body
  child.stdin.write(json.dumps(dict(path='/wp-json/nf05-qa/v1/'+route,token=token))+'\n');child.stdin.flush();child.stdin.close()
  for line in child.stdout:print(line.strip(),flush=True)
  assert child.wait(timeout=120)==0,'ACCESS_PREFLIGHT_PROBE_FAILED'
 finally:
  if child.poll() is None:child.kill();child.wait()
  if uploaded:
   f.delete(ROOT+name)
   try:read(name)
   except ftplib.error_perm as ex:
    if not str(ex).startswith('550'):raise
   else:raise RuntimeError('PREFLIGHT_PROBE_NOT_REMOVED')
  (e/'preflight-cleanup.json').write_text(json.dumps(dict(removed=True,installed=uploaded)))
elif phase=='before':
 custody(CAND,'before');b=probe();valid(b,'badaround-report/v2');assert b['max_report']==68,'UNEXPECTED_BASELINE_REPORTS';(e/'before.json').write_text(json.dumps(b,indent=2));print('NF05_CUSTODY_BASELINE_68_PASS',flush=True)
elif phase=='differential':
 cases=json.loads((e/'differential.json').read_text());actual=probe({'cases':[r['payload'] for r in cases]})
 assert len(actual)==len(cases)==587
 for i,(a,b) in enumerate(zip(actual,cases)):
  assert a['active']==b['active'] and a['required']==b['required'],'ACTIVE_SET_DIVERGENCE_'+str(i)
  assert a['normalized']==b['payload'],'NORMALIZATION_DIVERGENCE_'+str(i)
  if i<33:assert a['valid'],'ORACLE_INVALID_'+str(i)
 (e/'runtime-differential-result.json').write_text(json.dumps(dict(pass_all=True,oracles=33,matrix=2805,dependency_activations=554,cases=len(actual)),indent=2));print('LIVE_PHP_DIFFERENTIAL_PASS',flush=True)
elif phase in ['after','verify']:
 custody(CAND,phase);a=probe(baseline_query());valid(a,'badaround-report/v2');b=json.loads((e/'before.json').read_text());(e/(phase+'-state.json')).write_text(json.dumps(a,indent=2));assert a['state']==b['state'] and a['schema']==b['schema'],'PROTECTED_STATE_OR_SCHEMA_CHANGED';assert a['historical_versions']==b['historical_versions']
 if phase=='after':assert not a['new_reports'] and a['new_event_count']==0
 else:
  golden=json.loads((e/'golden05.json').read_text());assert len(a['new_reports'])==a['new_event_count']==len(golden)==3
  for r,g in zip(a['new_reports'],golden):
   assert r['event']==g['response']['event_id'] and r['pending'];assert r['envelope_version']==r['canonical']['schema_version']=='badaround-report/v2';assert r['canonical']==g['payload'];assert r['canonical']['reporter']['social_visibility']=='declined';assert not r['canonical'].get('media',{}).get('items');assert r['expected_subtype'] in list(map(int,r['event_types']));assert r['expected_territory'] in list(map(int,r['territories']))
  (e/'protected-fixtures-added.json').write_text(json.dumps([dict(report=r['report'],event=r['event'],schema_version='badaround-report/v2',status='pending',protected=True) for r in a['new_reports']],indent=2));(e/'success.txt').write_text('APPLICATION_CODE_UNCHANGED\nTEMPORARY_PROBES_REMOVED\nTHREE_PROTECTED_PENDING_V2_FIXTURES\n');print('NF05_FINAL_CUSTODY_AND_PERSISTENCE_PASS',flush=True)
f.quit()
