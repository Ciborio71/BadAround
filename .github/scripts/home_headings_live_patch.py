#!/usr/bin/env python3
"""Patch ONLY approved Home headings onto current production files via FTPS.

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
    if "ba-home-display-heading" in source:
        raise RuntimeError("Safety stop: headings already present or partially deployed")
    source = replace_once(
        source,
        '<nav class="ba-home-categories" aria-label="Categorie principali">',
        '<h2 class="ba-home-display-heading ba-home-display-heading--categories"><span>Cosa </span><span class="ba-home-display-heading__accent">Segnalare</span></h2>\n\t\t\t<nav class="ba-home-categories" aria-label="Categorie principali">',
        "categories insertion",
    )
    source = replace_once(
        source,
        '<h2>Segnalazioni vicino a te</h2>',
        '<h2 class="ba-home-display-heading"><span>Ultime </span><span class="ba-home-display-heading__accent">Segnalazioni</span></h2>',
        "recent heading",
    )
    source = replace_once(
        source,
        '<h2>Vuoi aiutare a tenere d’occhio una zona?</h2>',
        '<h2 class="ba-home-display-heading"><span>Diventa una </span><span class="ba-home-display-heading__accent">Sentinella</span></h2>\n\t\t\t\t<p class="ba-home-sentinel__intro">Vuoi aiutare a tenere d’occhio una zona?</p>',
        "sentinel heading",
    )
    return source

STYLE = """
/* HOME-HEADINGS-01 — section headings aligned with the homepage wordmark. */
.ba-home .ba-home-display-heading {
  margin:0;
  color:var(--ba-navy-950);
  font-size:clamp(3rem,5vw,5rem);
  font-weight:800;
  line-height:.98;
  letter-spacing:-.055em;
  overflow-wrap:break-word;
}
.ba-home .ba-home-display-heading__accent { color:var(--ba-teal-600); }
.ba-home .ba-home-display-heading--categories { margin:0 0 22px; }
.ba-home .ba-home-sentinel__intro { font-weight:700; }
@media (max-width:900px) {
  .ba-home .ba-home-display-heading { font-size:clamp(2.25rem,6vw,4rem); }
  .ba-home .ba-home-sectionhead { flex-wrap:wrap; align-items:flex-start; }
}
@media (max-width:560px) {
  .ba-home .ba-home-display-heading { font-size:clamp(2rem,8vw,3rem); }
}
"""

def patch_css(source):
    if "HOME-HEADINGS-01" in source or ".ba-home-display-heading" in source:
        raise RuntimeError("Safety stop: heading stylesheet already present")
    if ".ba-home-hero__content h1" not in source:
        raise RuntimeError("Safety stop: expected live homepage CSS not found")
    return source.rstrip() + "\n" + STYLE

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
