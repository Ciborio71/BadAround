#!/usr/bin/env python3
"""Preserve private key and exact peer policy; install bounded multi-peer QA admission."""
from pathlib import Path
import importlib.util,ftplib,ssl,os,json,re,io,tempfile,subprocess,hashlib
ROOT=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location('guard',ROOT/'tools/ba_event_seo_release.py')
guard=importlib.util.module_from_spec(spec);spec.loader.exec_module(guard)
FILES=['themes/badaround-child/functions.php'];guard.FILES=FILES
PRIVATE='/ba-identity-private-qa.php'
def candidate_private(original):
    pattern=rb"define\('BADAROUND_IDENTITY_ROOT_SECRET','([a-f0-9]{96})'\);"
    match=re.search(pattern,original)
    if not match:raise RuntimeError('Private configuration template not recognized')
    prior=(ROOT/'prior-source/tools/templates/identity-private-config.php.in').read_bytes()
    if prior.replace(b'__GENERATED_ROOT_SECRET__',match[1])!=original:raise RuntimeError('Protected configuration drift')
    result=(ROOT/'tools/templates/identity-private-config.php.in').read_bytes().replace(b'__GENERATED_ROOT_SECRET__',match[1])
    if re.search(pattern,result).group(1)!=match[1]:raise RuntimeError('Key preservation failed')
    with tempfile.TemporaryDirectory(prefix='ba-peer-lint-') as d:
        p=Path(d)/'private.php';p.write_bytes(result);p.chmod(0o600)
        r=subprocess.run(['php','-l',str(p)],capture_output=True,timeout=20)
        if r.returncode:raise RuntimeError('Private configuration lint failed')
    return result

def main():
    run=os.environ['GITHUB_RUN_ID']
    if not run.isdigit():raise RuntimeError('Invalid run context')
    prior={n:(ROOT/'prior-source/wordpress'/n).read_bytes() for n in FILES}
    candidate={n:(ROOT/'wordpress'/n).read_bytes() for n in FILES}
    ftp=ftplib.FTP_TLS(context=ssl.create_default_context(cafile=os.environ['FTP_TLS_CA_FILE']),timeout=45)
    installed=False;original=None;private=None
    backup='/wordpress-backups/ba-peer-'+run+'.prior';temp='/wordpress-backups/ba-peer-'+run+'.part'
    try:
        ftp.connect('46.231.24.137',21);ftp.host='hostmld17.aziendeitalia.com'
        ftp.login(os.environ['FTP_USER'],os.environ['FTP_PASSWORD']);ftp.prot_p()
        if hashlib.sha256(guard.read(ftp,'/httpdocs/wp-content/'+guard.CORE) or b'').hexdigest()!='18cb9ad21a63e08449eb85b7cb9233846bb02e2c472d05f118cd7d5764b7d175':raise RuntimeError('Core baseline drift')
        original=guard.read(ftp,PRIVATE)
        if original is None:raise RuntimeError('Private configuration unavailable')
        mode=re.search(r'(?i)unix\.mode=([0-7]{3,4});',ftp.sendcmd('MLST '+PRIVATE))
        if not mode or mode.group(1).lstrip('0')!='600':raise RuntimeError('Private permissions mismatch')
        private=candidate_private(original)
        for p in [backup,temp]:
            if guard.read(ftp,p) is not None:raise RuntimeError('Protected backup path occupied')
        for p,data in [(backup,original),(temp,private)]:
            ftp.storbinary('STOR '+p,io.BytesIO(data));ftp.sendcmd('SITE CHMOD 600 '+p)
            if guard.read(ftp,p)!=data:raise RuntimeError('Private backup/upload mismatch')
        if guard.read(ftp,PRIVATE)!=original:raise RuntimeError('Concurrent protected drift')
        ftp.rename(temp,PRIVATE);installed=True
        if guard.read(ftp,PRIVATE)!=private:raise RuntimeError('Private readback mismatch')
        evidence=guard.release(ftp,prior,candidate,run)
        evidence.update({'private_configuration_verified':True,'private_key_preserved':True,'permissions_0600_preserved':True,'option_allowlist_unchanged_by_deploy':True,'max_explicit_peers':16})
        print('IP_WHITELIST_RELEASE_PASS');print(json.dumps(evidence,sort_keys=True))
    except Exception:
        if installed:
            if guard.read(ftp,PRIVATE)!=private or guard.read(ftp,backup)!=original:raise RuntimeError('Concurrent drift prevents protected rollback') from None
            ftp.sendcmd('SITE CHMOD 600 '+backup);ftp.rename(backup,PRIVATE)
            if guard.read(ftp,PRIVATE)!=original:raise RuntimeError('Protected rollback mismatch') from None
        raise
    finally:
        try:ftp.quit()
        except ftplib.all_errors:ftp.close()
if __name__=='__main__':main()
