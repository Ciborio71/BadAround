import ftplib,ssl,os,json,pathlib
ctx=ssl._create_unverified_context()
f=ftplib.FTP_TLS(context=ctx,timeout=60);f.connect(os.environ['FTP_HOST'],21);f.login(os.environ['FTP_USER'],os.environ['FTP_PASS']);f.prot_p()
rows=[]
try:
 for path in ['/wordpress-backups','/staging.badaround.it']:
  try:
   for name,facts in f.mlsd(path):
    if name in ('.','..'): continue
    if facts.get('type')=='file':
     rows.append({'path':path+'/'+name,'size':int(facts.get('size','0') or 0),'modify':facts.get('modify','')})
  except Exception as e: rows.append({'path':path,'error':str(e)})
finally:f.quit()
rows=sorted(rows,key=lambda x:x.get('modify',''),reverse=True)[:100]
pathlib.Path('evidence').mkdir(exist_ok=True)
pathlib.Path('evidence/backup-inventory.json').write_text(json.dumps(rows,indent=2))
print(json.dumps(rows))
