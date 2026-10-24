import ftplib,ssl,os,io,hashlib,pathlib,subprocess,json
ROOT='/staging.badaround.it/wp-content/'
FILES=[
'themes/badaround-child/assets/css/components.css',
'themes/badaround-child/assets/css/legal.css',
'themes/badaround-child/assets/css/map-navigation.css',
'themes/badaround-child/assets/css/map.css',
'themes/badaround-child/assets/css/native-report.css',
'themes/badaround-child/assets/css/pages.css',
'themes/badaround-child/assets/css/sentinels.css',
'themes/badaround-child/assets/js/map.js',
'themes/badaround-child/assets/js/native-report/smart-address.js',
'themes/badaround-child/assets/js/native-report/wizard.js',
'themes/badaround-child/assets/js/sentinels.js',
'themes/badaround-child/functions.php',
'themes/badaround-child/header.php',
'themes/badaround-child/page-sentinelle.php']
def sha(b): return hashlib.sha256(b).hexdigest()
def git(p): return subprocess.check_output(['git','show','HEAD:wordpress/'+p])
ctx=ssl.create_default_context(cafile='tls/curl-chain.pem')
f=ftplib.FTP_TLS(context=ctx,timeout=60);f.connect(os.environ['FTP_HOST'],21);f.host=os.environ['FTP_TLS_NAME'];f.login(os.environ['FTP_USER'],os.environ['FTP_PASS']);f.prot_p()
rows=[]
try:
 for p in FILES:
  b=io.BytesIO();f.retrbinary('RETR '+ROOT+p,b.write);remote=b.getvalue();expected=git(p)
  rows.append({'path':p,'expected':sha(expected),'remote':sha(remote),'match':remote==expected})
finally:f.quit()
pathlib.Path('evidence').mkdir(exist_ok=True)
pathlib.Path('evidence/rc-runtime-custody.json').write_text(json.dumps(rows,indent=2))
assert all(r['match'] for r in rows), [r['path'] for r in rows if not r['match']]
print('RC_RUNTIME_CUSTODY_PASS',len(rows))
