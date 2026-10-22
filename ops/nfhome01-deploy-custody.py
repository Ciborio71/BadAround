import ftplib,ssl,os,pathlib,subprocess,json,hashlib,io,sys,secrets,urllib.request
ROOT='/staging.badaround.it/';BASE='8077c291281ca35f8f26d6f78563de5d7fa87112';CAND='ba8586b2dbc0315fc251b0ec5fcc96c7a2426124';TARGET='plugins/badaround-core/badaround-core.php';HASH='dc282153243ffb80f3bd4f2a222ea0f4c4f42806fba6f3a0bde0a76855bd8e3c'
e=pathlib.Path('evidence');e.mkdir(exist_ok=True)
def git(*a):return subprocess.check_output(['git',*a])
def sha(b):return hashlib.sha256(b).hexdigest()
f=ftplib.FTP_TLS(context=ssl.create_default_context(cafile='tls/curl-chain.pem'),timeout=60);f.connect(os.environ['FTP_HOST'],21);f.host=os.environ['FTP_TLS_NAME'];f.login(os.environ['FTP_USER'],os.environ['FTP_PASS']);f.prot_p()
def read(p):
 b=io.BytesIO();f.retrbinary('RETR '+ROOT+p,b.write);return b.getvalue()
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
THEME='d0ac50bf4866b326943f3f4487b49fbe3d878d7b'
FILES=['themes/badaround-child/'+p for p in ['inc/native-report.php','assets/js/native-report/model.js','assets/js/native-report/wizard.js','assets/js/native-report/errors.js','assets/css/native-report.css']]
custody(CAND,'before')
extra=['header.php','footer.php','page-segnala-evento.php','template-parts/native-report/shell.php','template-parts/native-report/step.php','assets/css/base.css','assets/css/components.css','assets/css/shell.css','assets/css/report.css','assets/js/navigation.js']+['assets/js/native-report/'+n+'.js' for n in ['api','media','media-view','location-map','smart-address']]
rows=[]
for item in extra:
 path='wordpress/themes/badaround-child/'+item
 expected=git('show',THEME+':'+path);remote=read('wp-content/themes/badaround-child/'+item)
 rows.append(dict(path=path,expected=sha(expected),remote=sha(remote),match=expected==remote))
(e/'render-dependency-custody.json').write_text(json.dumps(rows,indent=2))
assert all(r['match'] for r in rows),'RENDER_DEPENDENCY_DRIFT'
astra=read('wp-content/themes/astra/assets/css/minified/main.min.css')
(e/'astra-main.css').write_bytes(astra)
(e/'astra-source.json').write_text(json.dumps(dict(path='wp-content/themes/astra/assets/css/minified/main.min.css',sha256=sha(astra))))
print('RENDER_DEPENDENCIES_PASS',len(rows),flush=True)
astra_js=read('wp-content/themes/astra/assets/js/minified/frontend.min.js')
(e/'astra-frontend.js').write_bytes(astra_js)
(e/'astra-js-source.json').write_text(json.dumps(dict(path='wp-content/themes/astra/assets/js/minified/frontend.min.js',sha256=sha(astra_js))))
home_rows=[]
HOME_REF=os.environ.get('HOME_REF',THEME)
for item in ['front-page.php','assets/css/home.css','assets/css/map.css','assets/js/map.js']:
 path='wordpress/themes/badaround-child/'+item
 expected=git('show',(HOME_REF if item in ['front-page.php','assets/css/home.css'] else THEME)+':'+path);remote=read('wp-content/themes/badaround-child/'+item)
 home_rows.append(dict(path=path,expected=sha(expected),remote=sha(remote),match=expected==remote))
(e/'home-staging-baseline.json').write_text(json.dumps(home_rows,indent=2))
assert all(r['match'] for r in home_rows),'HOME_BASELINE_DRIFT'
print('HOME_BASELINE_PASS',len(home_rows),flush=True)
f.quit()
