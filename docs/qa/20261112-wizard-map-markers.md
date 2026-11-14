# Wizard map presentation — 2026-11-12

Scope: address typography and editable wizard map presentation only.

- Address and label: 17.6px, equal line-height; address colour #056bd2.
- Exact marker: existing category SVG enlarged from 64×80 to 80×100 CSS pixels.
- Approximate marker: 80×80 circular category ring, existing white centre and black category symbol. Unique gradient IDs retained.
- Marker anchors: exact bottom-centre; approximate centre, using documented Google AdvancedMarkerElement anchors.
- Approximation circle border and translucent fill follow the selected category and update when category changes. Radius and canonical coordinates are unchanged.
- No GEO projection, consent, publication, SEO or wizard question contract changes.

Validation: JavaScript syntax checked locally. CI runs existing functional/PHP/DOM regression checks plus Chromium presentation checks at 1440/1024/768/390/320px, category colours across all six categories, exact/approximate transitions, supported 100m/250m radius updates and equal address font size/colour. Google Maps is mocked in browser fixtures; this is not a live Google Maps visual certification.

Release: guarded FTPS deployment of location-map.js and native-report.css only, baseline f7e233cbe7c6a0f694ca5b9d5a1dcc3be902460f. Runtime candidate 8cbf9e7e1d51c182cf51a32f90cce1b9a0915dbf; workflow run 43318446179. Result: PASS, including guarded upload and final file hash readback. WPVibe live source readback confirmed the new marker switching/category circle colours and 17.6px/#056bd2 address styling.

