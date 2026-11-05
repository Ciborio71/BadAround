#!/usr/bin/env python3
"""Guarded dormant ownership release. No flags, schema, Core or theme changes."""
from pathlib import Path
import ftplib
import hashlib
import io
import json
import os
import ssl

CORE = '/httpdocs/wp-content/plugins/badaround-core/badaround-core.php'
BASE = '/httpdocs/wp-content/plugins/badaround-core/identity-dormant/'
PRIVATE = '/wordpress-backups/'
CORE_SHA = '18cb9ad21a63e08449eb85b7cb9233846bb02e2c472d05f118cd7d5764b7d175'
FILES = ['mysql-report-creator-grant.php', 'wordpress-pre-intake-recovery-composition.php',
         'wordpress-qa-report-composition.php']
ROOT = Path(__file__).resolve().parents[1]
MODULES = Path('wordpress/plugins/badaround-core/identity-dormant')

def read(ftp, path):
    stream = io.BytesIO()
    def chunk(data):
        if stream.tell() + len(data) > 200000:
            raise RuntimeError('Remote size limit exceeded')
        stream.write(data)
    try:
        ftp.retrbinary('RETR ' + path, chunk)
    except ftplib.error_perm as error:
        if str(error).startswith('550'):
            return None
        raise
    return stream.getvalue()

def release(ftp, prior, candidate, run, deploy):
    if not run.isdigit() or set(candidate) != set(FILES):
        raise RuntimeError('Invalid release inputs')
    if hashlib.sha256(read(ftp, CORE) or b'').hexdigest() != CORE_SHA:
        raise RuntimeError('Live Core baseline mismatch')
    names = {Path(name).name for name in ftp.nlst(BASE)
             if Path(name).suffix in ('.php', '.js')}
    if names != set(prior):
        raise RuntimeError('Unexpected live module inventory')
    for name, original in prior.items():
        if read(ftp, BASE + name) != original:
            raise RuntimeError('Live module differs from pinned baseline: ' + name)
    if read(ftp, BASE + FILES[0]) is not None:
        raise RuntimeError('New module destination occupied')
    for name in FILES:
        for suffix in ('.prior', '.part'):
            if read(ftp, PRIVATE + 'ba-creator-' + run + '-' + name + suffix) is not None:
                raise RuntimeError('Protected release destination occupied')
    if not deploy:
        return {'preflight': True, 'prior_modules': len(prior), 'modified': []}
    modified = []
    moved = None
    try:
        for name in FILES:
            original = prior.get(name)
            live = BASE + name
            backup = PRIVATE + 'ba-creator-' + run + '-' + name + '.prior'
            temporary = PRIVATE + 'ba-creator-' + run + '-' + name + '.part'
            if read(ftp, live) != original:
                raise RuntimeError('Module changed before upload')
            ftp.storbinary('STOR ' + temporary, io.BytesIO(candidate[name]))
            if read(ftp, temporary) != candidate[name] or read(ftp, live) != original:
                raise RuntimeError('Upload or baseline verification failed')
            if original is not None:
                ftp.rename(live, backup)
                moved = (name, live, backup)
            ftp.rename(temporary, live)
            modified.append(name)
            moved = None
            if read(ftp, live) != candidate[name]:
                raise RuntimeError('Installed module verification failed')
        for name, original in prior.items():
            if read(ftp, BASE + name) != candidate.get(name, original):
                raise RuntimeError('Post-release module integrity failure: ' + name)
        if hashlib.sha256(read(ftp, CORE) or b'').hexdigest() != CORE_SHA:
            raise RuntimeError('Core changed during release')
        return {'preflight': True, 'prior_modules': len(prior), 'modified': modified,
                'core_modified': False, 'flags_modified': False, 'database_modified': False,
                'public_identity_enabled': False}
    except Exception as failure:
        errors = []
        rollback = list(reversed(modified))
        if moved is not None:
            rollback.insert(0, moved[0])
        for name in rollback:
            try:
                live = BASE + name
                current = read(ftp, live)
                if current not in (None, candidate[name]):
                    raise RuntimeError('Unexpected changes prevent rollback')
                original = prior.get(name)
                if original is not None:
                    backup = PRIVATE + 'ba-creator-' + run + '-' + name + '.prior'
                    if read(ftp, backup) != original:
                        raise RuntimeError('Backup verification failed')
                if current is not None:
                    ftp.delete(live)
                if original is not None:
                    ftp.rename(backup, live)
                if read(ftp, live) != original:
                    raise RuntimeError('Rollback verification failed')
            except Exception:
                errors.append(name)
        if errors:
            raise RuntimeError('Rollback needs intervention for: ' + ', '.join(errors)) from failure
        raise

def main():
    prior = {p.name: p.read_bytes() for p in (ROOT / 'prior-source' / MODULES).iterdir()
             if p.suffix in ('.php', '.js')}
    candidate = {name: (ROOT / MODULES / name).read_bytes() for name in FILES}
    if len(prior) != 84 or any(not b.startswith(b'<?php') for b in candidate.values()):
        raise RuntimeError('Invalid source inventory')
    mode = os.environ.get('BA_CREATOR_RELEASE_MODE', 'preflight')
    if mode not in ('preflight', 'deploy'):
        raise RuntimeError('Invalid release mode')
    context = ssl.create_default_context(cafile=os.environ['FTP_TLS_CA_FILE'])
    ftp = ftplib.FTP_TLS(context=context, timeout=45)
    try:
        ftp.connect(os.environ['FTP_HOST'], 21)
        ftp.host = 'hostmld17.aziendeitalia.com'
        ftp.login(os.environ['FTP_USER'], os.environ['FTP_PASSWORD'])
        ftp.prot_p()
        evidence = release(ftp, prior, candidate, os.environ['GITHUB_RUN_ID'], mode == 'deploy')
        (ROOT / 'creator-grant-release-evidence.json').write_text(json.dumps(evidence, indent=2) + '\n')
        print('CREATOR_GRANT_' + mode.upper() + '_PASS')
    finally:
        try:
            ftp.quit()
        except ftplib.all_errors:
            ftp.close()

if __name__ == '__main__':
    main()
