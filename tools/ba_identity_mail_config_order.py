#!/usr/bin/env python3
"""Move existing mail definitions before WP boot; never export configuration."""
from pathlib import Path
import re,io,os,ssl,ftplib,subprocess,tempfile,importlib.util
ROOT=Path(__file__).resolve().parents[1]
NAMES=('BADAROUND_TURBOSMTP_CONSUMER_KEY','BADAROUND_TURBOSMTP_CONSUMER_SECRET','BADAROUND_TURBOSMTP_FROM_EMAIL')
BOOT=re.compile(r"""(?m)^[ \t]*require(?:_once)?\s*(?:\(\s*)?ABSPATH\s*\.\s*(['"])wp-settings\.php\1\s*\)?\s*;[ \t]*(?:\r?\n|$)""")
MARKER='BA_IDENTITY_MAIL_CONFIG_ORDER_V1'
def patch(original):
    text=original.decode('utf-8')
    if MARKER in text:raise RuntimeError('Configuration order patch already present')
    boots=list(BOOT.finditer(text))
    if len(boots)!=1 or not text.lstrip().startswith('<?php'):raise RuntimeError('Unrecognized bootstrap seam')
    boot=boots[0]
    matches=[]
    for name in NAMES:
        pattern=re.compile(r"""(?m)^[ \t]*define\s*\(\s*(['"])"""+name+r"""\1\s*,[^\r\n]+\)\s*;[ \t]*(?:\r?\n|$)""")
        found=list(pattern.finditer(text))
        if len(found)!=1 or found[0].start()<boot.end():raise RuntimeError('Unrecognized mail definition order')
        match=found[0]
        if match.group().count('define')!=1 or any(other in match.group() for other in NAMES if other!=name):
            raise RuntimeError('Ambiguous mail definition')
        if text.count(name)!=1:raise RuntimeError('Mail definition has other references')
        matches.append(match)
    definitions=''.join(m.group().rstrip('\r\n')+'\n' for m in matches)
    candidate=text
    for match in sorted(matches,key=lambda m:m.start(),reverse=True):
        candidate=candidate[:match.start()]+candidate[match.end():]
    seam=BOOT.search(candidate).start()
    candidate=candidate[:seam]+'// '+MARKER+': existing values available during plugin boot.\n'+definitions+'\n'+candidate[seam:]
    if sorted(text.splitlines())!=sorted(line for line in candidate.splitlines() if not line.startswith('// '+MARKER) and line!=''):
        # Compare nonempty lines so only the new comment and spacing may differ.
        before=sorted(line for line in text.splitlines() if line!='')
        after=sorted(line for line in candidate.splitlines() if line!='' and not line.startswith('// '+MARKER))
        if before!=after:raise RuntimeError('Unexpected configuration alteration')
    return candidate.encode('utf-8')
def lint(data):
    with tempfile.TemporaryDirectory() as folder:
        file=Path(folder)/'config.php';file.write_bytes(data);file.chmod(0o600)
        result=subprocess.run(['php','-l',str(file)],capture_output=True,timeout=20)
        if result.returncode:raise RuntimeError('Configuration syntax validation failed')
def main():
    spec=importlib.util.spec_from_file_location('guard',ROOT/'tools/ba_identity_creator_grant_release.py')
    guard=importlib.util.module_from_spec(spec);spec.loader.exec_module(guard)
    run=os.environ['GITHUB_RUN_ID']
    if not run.isdigit():raise RuntimeError('Invalid execution identifier')
    ftp=ftplib.FTP_TLS(context=ssl.create_default_context(cafile=os.environ['FTP_TLS_CA_FILE']),timeout=40)
    backup='/wordpress-backups/ba-mail-order-'+run+'.prior'
    temporary='/wordpress-backups/ba-mail-order-'+run+'.part'
    target='/httpdocs/wp-config.php'
    installed=False;original=None;candidate=None;mode=None
    try:
        ftp.connect('46.231.24.137',21);ftp.host='hostmld17.aziendeitalia.com'
        ftp.login(os.environ['FTP_USER'],os.environ['FTP_PASSWORD']);ftp.prot_p()
        original=guard.read(ftp,target)
        if original is None:raise RuntimeError('Missing configuration')
        candidate=patch(original)
        lint(original);lint(candidate)
        facts=ftp.sendcmd('MLST '+target)
        match=re.search(r'(?i)unix\.mode=([0-7]{3,4});',facts)
        if match is None:raise RuntimeError('Cannot verify configuration mode')
        mode=match.group(1)
        if guard.read(ftp,backup) is not None or guard.read(ftp,temporary) is not None:
            raise RuntimeError('Protected destination occupied')
        ftp.storbinary('STOR '+backup,io.BytesIO(original));ftp.sendcmd('SITE CHMOD 600 '+backup)
        if guard.read(ftp,backup)!=original:raise RuntimeError('Backup verification failed')
        ftp.storbinary('STOR '+temporary,io.BytesIO(candidate));ftp.sendcmd('SITE CHMOD '+mode+' '+temporary)
        if guard.read(ftp,temporary)!=candidate or guard.read(ftp,target)!=original:
            raise RuntimeError('Configuration changed before atomic replacement')
        ftp.rename(temporary,target);installed=True
        if guard.read(ftp,target)!=candidate or guard.read(ftp,backup)!=original:
            raise RuntimeError('Installed configuration differs')
        print('MAIL_CONFIG_ORDER_DEPLOY_PASS: existing values preserved; permissions preserved; backup verified')
    except Exception:
        if installed:
            if guard.read(ftp,target)!=candidate or guard.read(ftp,backup)!=original:
                raise RuntimeError('Configuration drift prevents rollback') from None
            ftp.sendcmd('SITE CHMOD '+mode+' '+backup);ftp.rename(backup,target)
            if guard.read(ftp,target)!=original:raise RuntimeError('Rollback verification failed') from None
        raise
    finally:
        try:ftp.quit()
        except ftplib.all_errors:ftp.close()
if __name__=='__main__':main()
