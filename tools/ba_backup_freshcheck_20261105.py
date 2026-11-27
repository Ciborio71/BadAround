#!/usr/bin/env python3
"""Read-only fresh backup inventory, dates and SQL backup candidates only."""
from __future__ import annotations
import ftplib,os,json,datetime
from pathlib import Path
def main():
 f=ftplib.FTP_TLS(timeout=45)
 try:
  f.connect(os.environ["FTP_HOST"],21);f.login(os.environ["FTP_USER"],os.environ["FTP_PASSWORD"]);f.prot_p()
  report=[]
  for root in ("/wordpress-backups","/httpdocs"):
   for name,info in f.mlsd(root):
    if info.get("type")!="file":continue
    if not name.lower().endswith((".zip",".sql",".sql.gz",".dump",".gz",".tar",".tar.gz",".tgz",".bak")):continue
    if len(report)>=60:break
    data={"directory":root,"bytes":int(info.get("size","0") or 0),"modified_utc":info.get("modify",""),"type":name.rsplit(".",1)[-1].lower(),"database_proven":False}
    report.append(data)
  report.sort(key=lambda x:x["modified_utc"],reverse=True)
  Path("ba-backup-fresh-metadata.json").write_text(json.dumps({"backup_candidates":report,"restore_tested":False,"read_only":True},indent=2))
  for r in report[:15]:print("BACKUP",r["directory"],r["bytes"],r["modified_utc"],r["type"])
 finally:
  try:f.quit()
  except ftplib.all_errors:f.close()
if __name__=="__main__":main()
