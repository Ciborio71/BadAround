#!/usr/bin/env python3
"""Guarded event interaction UX release."""
from pathlib import Path
import importlib.util,ftplib,ssl,os,json
ROOT=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location('guard',ROOT/'tools/ba_event_seo_release.py')
guard=importlib.util.module_from_spec(spec);spec.loader.exec_module(guard)
FILES=['plugins/badaround-core/assets/css/contributions.css', 'plugins/badaround-core/assets/js/contributions.js', 'plugins/badaround-core/includes/class-badaround-contribution-service.php', 'themes/badaround-child/assets/css/pages.css', 'themes/badaround-child/assets/js/map.js']
guard.FILES=FILES
def main():
    prior={n:(ROOT/'prior-source/wordpress'/n).read_bytes() if (ROOT/'prior-source/wordpress'/n).exists() else None for n in FILES}
    candidate={n:(ROOT/'wordpress'/n).read_bytes() for n in FILES}
    ftp=ftplib.FTP_TLS(context=ssl.create_default_context(cafile=os.environ['FTP_TLS_CA_FILE']),timeout=45)
    try:
        ftp.connect('46.231.24.137',21);ftp.host='hostmld17.aziendeitalia.com'
        ftp.login(os.environ['FTP_USER'],os.environ['FTP_PASSWORD']);ftp.prot_p()
        evidence=guard.release(ftp,prior,candidate,os.environ['GITHUB_RUN_ID'])
        print('EVENT_INTERACTION_RELEASE_PASS');print(json.dumps(evidence,sort_keys=True))
    finally:
        try:ftp.quit()
        except ftplib.all_errors:ftp.close()
if __name__=='__main__':main()
