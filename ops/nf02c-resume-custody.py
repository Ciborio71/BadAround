import ftplib,ssl,os,io,subprocess,json,hashlib,pathlib
C='38c82e3472fc4adb0ab80f8fd1281373aa1ab1e0';B='08f96d6f4dbd8ce8294e219857002f975351acf4'
def git(*a):return subprocess.check_output(['git',*a])
files=git('diff','--name-only',B,C,'--','wordpress/plugins/badaround-core').decode().splitlines()
extra=['badaround-core.php']+['includes/class-badaround-'+n+'.php' for n in ['core','report-schema','report-persistence-service','native-report-golden-path-service','native-media-config','native-media-ledger','territory-resolver','publication-service','public-geo-contract','public-geo-projection','geo-moderation-service']]
files=sorted(set(files+['wordpress/plugins/badaround-core/'+p for p in extra]))
e=pathlib.Path('evidence');e.mkdir(exist_ok=True)
f=ftplib.FTP_TLS(context=ssl.create_default_context(cafile='tls/curl-chain.pem'),timeout=60);f.connect(os.environ['FTP_HOST'],21);f.host=os.environ['FTP_TLS_NAME'];f.login(os.environ['FTP_USER'],os.environ['FTP_PASS']);f.prot_p()
rows=[]
for p in files:
 b=io.BytesIO();f.retrbinary('RETR /staging.badaround.it/wp-content/'+p.removeprefix('wordpress/'),b.write)
 actual=hashlib.sha256(b.getvalue()).hexdigest();expected=hashlib.sha256(git('show',C+':'+p)).hexdigest();rows.append(dict(path=p,expected=expected,remote=actual,match=actual==expected))
(e/'runtime-sha256.json').write_text(json.dumps(rows,indent=2));assert all(r['match'] for r in rows),'RUNTIME_DRIFT_STOP'
print('RUNTIME_CUSTODY_PASS files='+str(len(rows)));f.quit()
