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
f.quit()
