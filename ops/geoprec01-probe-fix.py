import ftplib,ssl,os,pathlib,io,hashlib
root='/staging.badaround.it/wp-content/mu-plugins/geoprec01-48bab222-qa.php'
f=ftplib.FTP_TLS(context=ssl.create_default_context(cafile='tls/curl-chain.pem'),timeout=45);f.connect(os.environ['FTP_HOST'],21);f.host=os.environ['FTP_TLS_NAME'];f.login(os.environ['FTP_USER'],os.environ['FTP_PASS']);f.prot_p()
def read():
 b=io.BytesIO();f.retrbinary('RETR '+root,b.write);return b.getvalue()
try:
 old=read();assert hashlib.sha256(old).hexdigest()=='cf3b729835e06f4820d3b27cb923e4705b1b539998e9b2aaa23367a87346a0de','UNEXPECTED_QA_HARNESS_DRIFT'
 new=pathlib.Path('ops/geoprec01-probe-fixed.php').read_bytes()
 f.storbinary('STOR '+root+'.qa-fix-tmp',io.BytesIO(new));f.rename(root+'.qa-fix-tmp',root);assert read()==new
 print('TEMPORARY_QA_HARNESS_ONLY_UPDATED; ZERO_APPLICATION_FILES_MODIFIED')
finally:f.quit()
