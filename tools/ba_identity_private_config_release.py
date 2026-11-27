#!/usr/bin/env python3
"""Install bounded, peer-restricted QA config. Never log or export key material."""
from pathlib import Path
import ftplib
import hashlib
import importlib.util
import io
import json
import os
import re
import secrets
import ssl
import subprocess
import tempfile

ROOT=Path(__file__).resolve().parents[1]
CONFIG='/httpdocs/wp-config.php'
PRIVATE='/ba-identity-private-qa.php'
MARKER='BA_IDENTITY_PRIVATE_QA_INCLUDE_V1'
INCLUDE=("// "+MARKER+" — restricted QA only; public runtime remains disabled.\n"
    "if (is_readable(dirname(__DIR__) . '/ba-identity-private-qa.php')) {\n"
    "    require_once dirname(__DIR__) . '/ba-identity-private-qa.php';\n}\n\n")
BOOT=re.compile(r"(?m)^[ \t]*require(?:_once)?\s*(?:\(\s*)?ABSPATH\s*\.\s*(['\"])wp-settings\.php\1\s*\)?\s*;")

def patch_config(original:bytes)->bytes:
    text=original.decode('utf-8')
    if MARKER in text or 'ba-identity-private-qa.php' in text:
        raise RuntimeError('QA configuration include already present')
    if re.search(r"define\s*\(\s*['\"]BADAROUND_IDENTITY_(?:RUNTIME_INTEGRATION_ENABLED|PRIVATE_QA_ENABLED|QA_IP_ALLOWLIST)['\"]",text):
        raise RuntimeError('Existing Identity activation configuration requires reconciliation')
    matches=list(BOOT.finditer(text))
    if len(matches)!=1 or not text.lstrip().startswith('<?php'):
        raise RuntimeError('Unrecognized WordPress bootstrap seam')
    i=matches[0].start()
    return (text[:i]+INCLUDE+text[i:]).encode('utf-8')

def private_source(template:str,key:str)->bytes:
    if not re.fullmatch('[a-f0-9]{96}',key) or template.count('__GENERATED_ROOT_SECRET__')!=1:
        raise RuntimeError('Invalid private key template')
    return template.replace('__GENERATED_ROOT_SECRET__',key).encode('utf-8')

def lint(source:bytes)->None:
    with tempfile.TemporaryDirectory(prefix='ba-private-config-') as directory:
        path=Path(directory)/'private.php';path.write_bytes(source);path.chmod(0o600)
        result=subprocess.run(['php','-l',str(path)],capture_output=True,timeout=20)
        if result.returncode:raise RuntimeError('Private PHP syntax validation failed')

def main():
    spec=importlib.util.spec_from_file_location('guarded',ROOT/'tools/ba_identity_creator_grant_release.py')
    guarded=importlib.util.module_from_spec(spec);spec.loader.exec_module(guarded)
    run=os.environ['GITHUB_RUN_ID']
    if not run.isdigit():raise RuntimeError('Invalid run context')
    ftp=ftplib.FTP_TLS(context=ssl.create_default_context(cafile=os.environ['FTP_TLS_CA_FILE']),timeout=45)
    backup_ready=False
    backup='/wordpress-backups/ba-identity-config-'+run+'.prior'
    temporary='/wordpress-backups/ba-identity-config-'+run+'.part'
    private_temp='/wordpress-backups/ba-identity-private-'+run+'.part'
    probes=['/wordpress-backups/ba-identity-rename-'+run+'-'+n+'.probe' for n in ['a','b']]
    original=None;candidate=None;original_mode=None
    try:
        ftp.connect(os.environ['FTP_HOST'],21);ftp.host='hostmld17.aziendeitalia.com'
        ftp.login(os.environ['FTP_USER'],os.environ['FTP_PASSWORD']);ftp.prot_p()
        if hashlib.sha256(guarded.read(ftp,guarded.CORE) or b'').hexdigest()!=guarded.CORE_SHA:
            raise RuntimeError('Core baseline changed')
        original=guarded.read(ftp,CONFIG)
        if original is None:raise RuntimeError('WordPress configuration not found')
        facts=ftp.sendcmd('MLST '+CONFIG)
        mode_match=re.search(r'(?i)unix\.mode=([0-7]{3,4});',facts)
        if mode_match is None:raise RuntimeError('Cannot verify original configuration permissions')
        original_mode=mode_match.group(1)
        candidate=patch_config(original)
        for path in [PRIVATE,backup,temporary,private_temp,*probes]:
            if guarded.read(ftp,path) is not None:raise RuntimeError('Protected configuration destination occupied')
        # Verify atomic replacement support away from the live configuration.
        # A refused rename leaves wp-config untouched, rather than creating a
        # window in which WordPress cannot find its configuration file.
        ftp.storbinary('STOR '+probes[0],io.BytesIO(b'qa-probe-a'))
        ftp.storbinary('STOR '+probes[1],io.BytesIO(b'qa-probe-b'))
        ftp.rename(probes[0],probes[1])
        if guarded.read(ftp,probes[1])!=b'qa-probe-a':raise RuntimeError('Atomic replacement probe failed')
        ftp.delete(probes[1])
        private=private_source((ROOT/'tools/templates/identity-private-config.php.in').read_text(),secrets.token_hex(48))
        lint(original);lint(candidate);lint(private)
        ftp.storbinary('STOR '+private_temp,io.BytesIO(private));ftp.sendcmd('SITE CHMOD 600 '+private_temp)
        if guarded.read(ftp,private_temp)!=private:raise RuntimeError('Private configuration verification failed')
        if guarded.read(ftp,PRIVATE) is not None:raise RuntimeError('Private configuration appeared during release')
        ftp.rename(private_temp,PRIVATE)
        ftp.storbinary('STOR '+backup,io.BytesIO(original));ftp.sendcmd('SITE CHMOD 600 '+backup)
        if guarded.read(ftp,backup)!=original:raise RuntimeError('Configuration backup verification failed')
        backup_ready=True
        ftp.storbinary('STOR '+temporary,io.BytesIO(candidate));ftp.sendcmd('SITE CHMOD '+original_mode+' '+temporary)
        if guarded.read(ftp,temporary)!=candidate or guarded.read(ftp,CONFIG)!=original:
            raise RuntimeError('Configuration changed before installation')
        ftp.rename(temporary,CONFIG)
        if guarded.read(ftp,CONFIG)!=candidate or guarded.read(ftp,backup)!=original:
            raise RuntimeError('Configuration or backup integrity failure')
        evidence={'configured':True,'private_config_outside_webroot':True,'private_permissions':'0600',
            'wp_config_permissions_preserved':True,
            'original_configuration_sha256':hashlib.sha256(original).hexdigest(),
            'configuration_sha256':hashlib.sha256(candidate).hexdigest(),
            'peer_allowlist_from_private_option':True,'public_activation':False,
            'keys_logged_or_exported':False,'existing_keys_preserved':True}
        (ROOT/'private-config-evidence.json').write_text(json.dumps(evidence,indent=2)+'\n')
        print('PRIVATE_QA_CONFIG_INSTALLED_PASS')
    except Exception as failure:
        if backup_ready:
            try:
                current=guarded.read(ftp,CONFIG)
                if current not in (original,candidate) or guarded.read(ftp,backup)!=original:
                    raise RuntimeError('Configuration changed during rollback')
                if current==candidate:
                    ftp.sendcmd('SITE CHMOD '+original_mode+' '+backup)
                    ftp.rename(backup,CONFIG)
                if guarded.read(ftp,CONFIG)!=original:raise RuntimeError('Rollback verification failed')
            except Exception:
                raise RuntimeError('Configuration rollback requires intervention') from failure
        # An unused private file is preserved outside the webroot on failure.
        # It has no execution path once the original wp-config is restored.
        raise
    finally:
        try:ftp.quit()
        except ftplib.all_errors:ftp.close()

if __name__=='__main__':main()
