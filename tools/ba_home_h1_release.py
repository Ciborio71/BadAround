#!/usr/bin/env python3
"""Single-file guarded Home H1 release; no Identity, database or activation writes."""
from pathlib import Path
import importlib.util,ftplib,ssl,os,json
ROOT=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location('guard',ROOT/'tools/ba_event_seo_release.py')
guard=importlib.util.module_from_spec(spec);spec.loader.exec_module(guard)
FILE='themes/badaround-child/assets/css/home.css'
guard.FILES=[FILE]
def main():
    old=(ROOT/'prior-source/wordpress'/FILE).read_bytes()
    new=(ROOT/'wordpress'/FILE).read_bytes()
    expected="\n/* HOME-H1-02 — larger wordmark on wide desktops; existing tablet/mobile rules retained. */\n@media (min-width:1440px) {\n  .ba-home-hero__content h1 { font-size:108px; }\n  @supports (font-size:1cqi) {\n    .ba-home-hero__content { container-type:inline-size; }\n    .ba-home-hero__content h1 { font-size:clamp(3rem,20cqi,108px); }\n  }\n}\n".encode()
    if new!=old+expected:raise RuntimeError('Unexpected CSS scope')
    ftp=ftplib.FTP_TLS(context=ssl.create_default_context(cafile=os.environ['FTP_TLS_CA_FILE']),timeout=45)
    try:
        ftp.connect('46.231.24.137',21);ftp.host='hostmld17.aziendeitalia.com'
        ftp.login(os.environ['FTP_USER'],os.environ['FTP_PASSWORD']);ftp.prot_p()
        evidence=guard.release(ftp,{FILE:old},{FILE:new},os.environ['GITHUB_RUN_ID'])
        print('HOME_H1_RELEASE_PASS');print(json.dumps(evidence,sort_keys=True))
    finally:
        try:ftp.quit()
        except ftplib.all_errors:ftp.close()
if __name__=='__main__':main()
