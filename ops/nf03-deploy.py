import ftplib,ssl,os,pathlib,subprocess,json,hashlib,io,sys
ROOT='/staging.badaround.it/';BASE='0d1db0d7fab7f2facbe6b07ccff002b9e884cade';CAND='8077c291281ca35f8f26d6f78563de5d7fa87112'
FILES=['themes/badaround-child/'+p for p in ['inc/native-report.php','assets/js/native-report/wizard.js','assets/css/native-report.css']]
e=pathlib.Path('evidence');e.mkdir(exist_ok=True);(e/'rollback').mkdir(exist_ok=True)
def git(*a):return subprocess.check_output(['git',*a])
def sha(b):return hashlib.sha256(b).hexdigest()
f=ftplib.FTP_TLS(context=ssl.create_default_context(cafile='tls/curl-chain.pem'),timeout=60);f.connect(os.environ['FTP_HOST'],21);f.host=os.environ['FTP_TLS_NAME'];f.login(os.environ['FTP_USER'],os.environ['FTP_PASS']);f.prot_p()
def read(p):
 b=io.BytesIO();f.retrbinary('RETR '+ROOT+'wp-content/'+p,b.write);return b.getvalue()
def write(p,data):
 temp=ROOT+'wp-content/'+p+'.nf03-tmp';f.storbinary('STOR '+temp,io.BytesIO(data));f.rename(temp,ROOT+'wp-content/'+p)
def custody(label):
 files=git('diff','--name-only','08f96d6f4dbd8ce8294e219857002f975351acf4',BASE,'--','wordpress/plugins/badaround-core').decode().splitlines()
 extra=['badaround-core.php']+['includes/class-badaround-'+n+'.php' for n in ['core','report-schema','report-persistence-service','native-report-golden-path-service','native-media-config','native-media-ledger','territory-resolver','publication-service','public-geo-contract','public-geo-projection','geo-moderation-service']]
 files=sorted(set(files+['wordpress/plugins/badaround-core/'+p for p in extra]));rows=[]
 for p in files:
  data=read(p.removeprefix('wordpress/'));expected=git('show',BASE+':'+p);rows.append(dict(path=p,expected=sha(expected),remote=sha(data),match=data==expected))
 (e/('backend-'+label+'.json')).write_text(json.dumps(rows,indent=2));assert all(r['match'] for r in rows),'BACKEND_DRIFT_'+label
 print('BACKEND_HASH_PASS',label,len(rows),flush=True)
phase=sys.argv[1]
if phase=='prepare':
 custody('before');rows=[]
 for i,p in enumerate(FILES):
  old=read(p);expected=git('show',BASE+':wordpress/'+p);assert old==expected,'BASELINE_DRIFT '+p
  new=git('show',CAND+':wordpress/'+p);backup=e/'rollback'/str(i);backup.write_bytes(old);assert backup.read_bytes()==old
  rows.append(dict(path=p,before=sha(old),after=sha(new),backup=str(backup)))
 (e/'manifest.json').write_text(json.dumps(rows,indent=2));print('THREE_FILE_BYTE_EXACT_ROLLBACK_READY',flush=True)
elif phase=='deploy':
 rows=json.loads((e/'manifest.json').read_text());touched=[]
 try:
  for row in rows:assert sha(read(row['path']))==row['before'],'DRIFT_AFTER_BACKUP'
  for row in rows:
   p=row['path'];new=git('show',CAND+':wordpress/'+p);assert sha(new)==row['after'];touched.append(row);write(p,new);assert read(p)==new
  (e/'uploaded.txt').write_text(CAND+'\n');print('THREE_FILE_UPLOAD_HASH_PASS',flush=True)
 except BaseException:
  for row in reversed(touched):
   old=pathlib.Path(row['backup']).read_bytes();assert sha(old)==row['before'];write(row['path'],old);assert read(row['path'])==old
  (e/'rollback-executed.txt').write_text('BYTE_EXACT_BASELINE_RESTORED\n');raise
elif phase=='verify':
 rows=json.loads((e/'manifest.json').read_text());out=[]
 for row in rows:
  remote=sha(read(row['path']));assert remote==row['after'];out.append(dict(path=row['path'],remote=remote,match=True))
 (e/'runtime-after.json').write_text(json.dumps(out,indent=2));custody('after');(e/'certified.txt').write_text(CAND+'\nTHREE_RUNTIME_FILES_ONLY\nNO_SERVER_QA_HARNESS\n')
elif phase=='rollback':
 if (e/'uploaded.txt').exists():
  for row in json.loads((e/'manifest.json').read_text()):
   old=pathlib.Path(row['backup']).read_bytes();assert sha(old)==row['before'];write(row['path'],old);assert read(row['path'])==old
  (e/'rollback-executed.txt').write_text('BYTE_EXACT_BASELINE_RESTORED\n');print('ROLLBACK_VERIFIED',flush=True)
f.quit()
