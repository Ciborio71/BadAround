#!/usr/bin/env python3
"""HOME-HEADINGS-02: resize section headings and relocate Sentinel heading.

Default dry run downloads and validates live content but never writes.
Deploy mode requires an explicit GitHub Actions confirmation.
"""
import datetime
import ftplib
import io
import os
import re
import sys

BASE = "/httpdocs/wp-content/themes/badaround-child"
FILES = ("front-page.php", "assets/css/home.css")

def replace_once(value, old, new, label):
    count = value.count(old)
    if count != 1:
        raise RuntimeError(f"Safety stop: {label} expected once, found {count}")
    return value.replace(old, new, 1)

def patch_php(source):
    heading = '<h2 class="ba-home-display-heading"><span>Diventa una </span><span class="ba-home-display-heading__accent">Sentinella</span></h2>'
    if source.count(heading) != 1:
        raise RuntimeError("Safety stop: existing Sentinel heading not found exactly once")

    # Move the heading outside the colored Sentinel section, before the lower layout.
    source = replace_once(
        source,
        '\t\t\t<div class="ba-home-lower">',
        '\t\t\t<h2 class="ba-home-display-heading ba-home-display-heading--sentinel">' +
        '<span>Diventa una </span><span class="ba-home-display-heading__accent">Sentinella</span></h2>\\n' +
        '\t\t\t<div class="ba-home-lower">',
        "outside Sentinel box",
    )
    source = replace_once(source, heading + '\\n', '', "remove heading within Sentinel box")
    if source.count('ba-home-display-heading--sentinel') != 1 or source.count(heading) != 0:
        raise RuntimeError("Safety stop: heading relocation invariant failed")
    return source

OLD_STYLE = """font-size:clamp(3rem,5vw,5rem);"""
NEW_STYLE = """font-size:clamp(2rem,5vw,63px);"""

def patch_css(source):
    if source.count("/* HOME-HEADINGS-01 — section headings aligned with the homepage wordmark. */") != 1:
        raise RuntimeError("Safety stop: previously deployed section CSS missing")
    # Strictly target the section heading rule; do not touch the H1 wordmark 80px.
    match = re.search(r"(\\.ba-home \\.ba-home-display-heading \\{)(.*?)(\\n\\})", source, re.S)
    if not match:
        raise RuntimeError("Safety stop: heading style declaration missing")
    block = match.group(2)
    if block.count(OLD_STYLE) != 1:
        raise RuntimeError("Safety stop: expected old section heading size missing")
    updated_block = block.replace(OLD_STYLE, NEW_STYLE, 1)
    source = source[:match.start(2)] + updated_block + source[match.end(2):]
    # Sentinel heading now sits outside the panel; give it the same spacing as categories.
    if ".ba-home .ba-home-display-heading--sentinel" in source:
        raise RuntimeError("Safety stop: Sentinel layout adjustment already present")
    source += "\\n.ba-home .ba-home-display-heading--sentinel { margin:0 0 22px; }\\n"
    return source

def retrieve(ftp, path):
    output = io.BytesIO()
    ftp.retrbinary("RETR " + path, output.write)
    raw = output.getvalue()
    if len(raw) < 1000 or len(raw) > 250000:
        raise RuntimeError("Safety stop: unexpected live file size")
    return raw

def upload(ftp, path, data):
    ftp.storbinary("STOR " + path, io.BytesIO(data))

def main():
    deploy = os.getenv("DEPLOY_MODE", "dry-run") == "deploy"
    if deploy and os.getenv("DEPLOY_CONFIRMATION") != "DEPLOY":
        raise RuntimeError("Deployment requires confirmation")
    user = os.environ["FTP_USER"]
    password = os.environ["FTP_PASSWORD"]
    host = os.environ["FTP_HOST"]
    stamp = datetime.datetime.now(datetime.timezone.utc).strftime("%Y%m%dT%H%M%SZ")
    ftp = ftplib.FTP_TLS(timeout=25)
    backups = {}
    deployed = []
    try:
        ftp.connect(host, 21)
        ftp.login(user, password)
        ftp.prot_p()
        originals = {}
        patched = {}
        for relative in FILES:
            remote = BASE + "/" + relative
            data = retrieve(ftp, remote)
            text = data.decode("utf-8")
            modified = patch_php(text) if relative.endswith(".php") else patch_css(text)
            originals[remote] = data
            patched[remote] = modified.encode("utf-8")
            print(f"VALIDATED={relative} live_bytes={len(data)} updated_bytes={len(patched[remote])}")
        if not deploy:
            print("DRY_RUN=PASS; remote files unchanged")
            return
        for remote in originals:
            backup = remote + ".ba-home-headings-" + stamp + ".bak"
            temp = remote + ".ba-home-headings-" + stamp + ".tmp"
            upload(ftp, backup, originals[remote])
            if retrieve(ftp, backup) != originals[remote]:
                raise RuntimeError("Backup verification failed")
            backups[remote] = backup
            print("BACKUP_VERIFIED=" + remote.rsplit("/", 1)[-1])
            upload(ftp, temp, patched[remote])
            if retrieve(ftp, temp) != patched[remote]:
                raise RuntimeError("Temporary file verification failed")
            # Never replace a file that changed after the initial read.
            if retrieve(ftp, remote) != originals[remote]:
                raise RuntimeError("Live file changed during deployment")
            ftp.rename(temp, remote)
            deployed.append(remote)
            if retrieve(ftp, remote) != patched[remote]:
                raise RuntimeError("Remote verification failed")
            print("DEPLOYED_VERIFIED=" + remote.rsplit("/", 1)[-1])
        print("DEPLOY=PASS; BACKUPS_PRESERVED=2")
    except Exception:
        for remote in reversed(deployed):
            try:
                current_backup = backups[remote]
                upload(ftp, remote, retrieve(ftp, current_backup))
                print("ROLLBACK_RESTORED=" + remote.rsplit("/", 1)[-1])
            except Exception:
                print("ROLLBACK_FAILED=" + remote.rsplit("/", 1)[-1], file=sys.stderr)
        raise
    finally:
        try:
            ftp.quit()
        except Exception:
            ftp.close()

if __name__ == "__main__":
    main()
