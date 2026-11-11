#!/usr/bin/env python3
"""Guarded navbar session rendering; contextual location and public editorial projection."""
from pathlib import Path
import importlib.util,ftplib,ssl,os,json
ROOT=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location('guard',ROOT/'tools/ba_event_seo_release.py')
guard=importlib.util.module_from_spec(spec);spec.loader.exec_module(guard)
FILES=['themes/badaround-child/assets/event-placeholder-animali.svg', 'themes/badaround-child/assets/event-placeholder-case-e-attivita.svg', 'themes/badaround-child/assets/event-placeholder-oggetti-e-documenti.svg', 'themes/badaround-child/assets/event-placeholder-pericoli.svg', 'themes/badaround-child/assets/event-placeholder-spazi-pubblici.svg', 'themes/badaround-child/assets/event-placeholder-veicoli.svg', 'themes/badaround-child/assets/event-placeholder.svg', 'themes/badaround-child/assets/pin-animali.svg', 'themes/badaround-child/assets/pin-case-e-attivita.svg', 'themes/badaround-child/assets/pin-designs.json', 'themes/badaround-child/assets/pin-neutral.svg', 'themes/badaround-child/assets/pin-oggetti-e-documenti.svg', 'themes/badaround-child/assets/pin-pericoli.svg', 'themes/badaround-child/assets/pin-resolved.svg', 'themes/badaround-child/assets/pin-spazi-pubblici.svg', 'themes/badaround-child/assets/pin-veicoli.svg', 'themes/badaround-child/inc/pins.php', 'themes/badaround-child/inc/native-report.php', 'themes/badaround-child/functions.php', 'themes/badaround-child/assets/css/components.css', 'themes/badaround-child/assets/css/home.css', 'themes/badaround-child/assets/css/how-it-works.css', 'themes/badaround-child/assets/css/map.css', 'themes/badaround-child/assets/css/native-report.css', 'themes/badaround-child/assets/css/sentinels.css', 'themes/badaround-child/assets/js/map.js', 'themes/badaround-child/assets/js/native-report/location-map.js', 'themes/badaround-child/assets/js/native-report/wizard.js', 'themes/badaround-child/front-page.php', 'themes/badaround-child/page-come-funziona.php', 'themes/badaround-child/page-mappa.php', 'themes/badaround-child/page-radar-demo.php', 'themes/badaround-child/page-segnala-evento.php', 'themes/badaround-child/page-sentinelle.php', 'themes/badaround-child/template-parts/components/event-card.php', 'themes/badaround-child/template-parts/native-report/step.php', 'themes/badaround-child/template-parts/single-event-layout.php']
guard.FILES=FILES
def main():
    prior={n:(ROOT/'prior-source/wordpress'/n).read_bytes() if (ROOT/'prior-source/wordpress'/n).exists() else None for n in FILES}
    candidate={n:(ROOT/'wordpress'/n).read_bytes() for n in FILES}
    ftp=ftplib.FTP_TLS(context=ssl.create_default_context(cafile=os.environ['FTP_TLS_CA_FILE']),timeout=45)
    try:
        ftp.connect('46.231.24.137',21);ftp.host='hostmld17.aziendeitalia.com'
        ftp.login(os.environ['FTP_USER'],os.environ['FTP_PASSWORD']);ftp.prot_p()
        evidence=guard.release(ftp,prior,candidate,os.environ['GITHUB_RUN_ID'])
        print('PIN_SYSTEM_RELEASE_PASS');print(json.dumps(evidence,sort_keys=True))
    finally:
        try:ftp.quit()
        except ftplib.all_errors:ftp.close()
if __name__=='__main__':main()

# Retry after aligning existing regression assertions with approved runtime overlays.
# Fixture syntax corrected before any live upload.
# Render offline fixture under its expected origin for browser geometry QA.
