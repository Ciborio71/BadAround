# MAP-CONTROLS-01E — live staging certification

Application candidate: `6cf3d94e77b9932a4761db0d1b19f08b80fdc2c4` (five-file state including reconciled `56853ec079cbd9bdd1f466104790e07f54bb60d1`).

Deployment run: https://github.com/Ciborio71/BadAround/actions/runs/39961660779

Staging only. No merge, production deployment, new report/event, moderation/publication, GEO/radius/schema/taxonomy/Social/SEO/media changes. NF-FINAL-01 remains on hold.

## Custody and deployment

- Strict verified FTPS TLS; rollback captured before upload: three original files plus two certified absences.
- 59/59 pre-upload runtime hashes match certified GEO-PREC/GEO-AREA/Classic Editor/Home/Native baseline.
- Exactly five authorized application files uploaded; 5/5 remote candidate hashes and 59/59 post-upload custody PASS.
- Before/after schema, taxonomy, report, event, metadata, relationships, audit, canonical-content and public-projection digests match, and match the preceding certified protected state.
- Classic Editor filters each present exactly once at priority 100; actual WordPress bootstrap reports block editor disabled for ba_evento, post and page.
- Native remains badaround-report/v2; frozen contract SHA remains dc282153243ffb80f3bd4f2a222ea0f4c4f42806fba6f3a0bde0a76855bd8e3c.

## Real browser evidence

Authorized cloud Chromium, actual deployed staging pages/assets and actual Google Maps SDK. Responsive wrapper changes iframe width only; no substitute DOM/provider. Browser exposes no touch hardware (maxTouchPoints=0); responsive geometry, mouse drag, keyboard, cooperative gesture policy and ordinary page scrolling were checked. Physical touch-device testing is not claimed.

| Surface | 320 | 390 | 768 | 1024 | 1440 |
|---|---|---|---|---|---|
| native | PASS | PASS | PASS | PASS | PASS |
| home | PASS | PASS | PASS | PASS | PASS |
| map | PASS | PASS | PASS | PASS | PASS |
| event360 | PASS | PASS | PASS | PASS | PASS |
| event361 | PASS | PASS | PASS | PASS | PASS |
| event362 | PASS | PASS | PASS | PASS | PASS |
| event363 | PASS | PASS | PASS | PASS | PASS |

35/35 scenarios PASS. Each rendered map has six required controls, one custom pan pad and one Maps bootstrap/instance. Custom targets are 44x44 px; Google native zoom controls retain their native 40 px dimensions. No horizontal document overflow or clipping. Visual checks and pre-deploy overlap suite confirm separation from Google attribution, BadAround legend and CTA.

### Critical Native geometry (live, before control focus)

| Nominal width | Effective client width | Map left–right | Map width | Zoom left–right | North/west left–right | South/east left–right | Zoom inset | Clipping |
|---|---:|---|---:|---|---|---|---:|---|
| 320 | 305 | 16–289 | 273 | 238–278 | 185–229 | 231–275 | 11 | NONE |
| 390 | 375 | 16–359 | 343 | 308–348 | 255–299 | 301–345 | 11 | NONE |

Pan buttons retain 44x44 targets and 14 px right inset; focus ring is 3 px inset and is not clipped. Actual Tab traversal and Enter activation were tested; a visible keyboard focus image is included. Programmatically focusing an offscreen target without scrolling was not used as visual evidence; final focus capture starts from a map in view.

### Geometry immutability

- Native exact marker and approximate circles: PASS.
- Live Google circles at 50, 100, 250, 500 and 1000 m: PASS. Four pan actions recorded per radius; serialized location (source coordinates, territory, user mode/radius) and circle center/radius remain identical.
- Actual mouse drag tested separately for Native, Home, public Mappa and Detail: viewport center changes; source/report or approved public geometry does not.
- Home/public clusters expand; legend closes/reopens; control pad remains single and available. After cluster expansion live public circles include 100, 500 and 1000 m, alongside exact markers.
- Protected detail fixtures 77/360, 78/361, 79/362, 80/363 read-only: radii 100/500/1000 preserved; exact has no area circle.
- Google detail rendering can complete only after the map is scrolled into view; tests inspect controls after rendering completes, without application changes.
- Fullscreen control is present. Native activation did not establish fullscreen in this QA environment; transition UNKNOWN, optional/non-blocking. No root-cause investigation or workaround was attempted.

## Regression evidence

Pinned deployment pipeline C.1/C.2, GEO-PREC governance, GEO-AREA publication/consumers/moderation, 33-oracle/2805 matrix Native quick suite, Smart Address, Social/privacy and public geometry regression steps PASS. Full dedicated candidate CI: https://github.com/Ciborio71/BadAround/actions/runs/39951545675 . Device geolocation regression uses existing deterministic browser evidence; no new real location/report is persisted.

Actual WordPress readback confirms moderator mode/radius remain read-only, protected public projections and audit unchanged, Native v2 and frozen contract intact. No new library, Maps load, map instance, polling or duplicate pad.

## QA transport and preparatory runs

Runs 39960128821 and 39960765442 were deliberately held before any application upload; temporary components were removed with FTPS absence verified. A URL-token navigation was rejected by automatic approval review; it was not executed. Final QA reused the existing WordPress administrator account through secure browserAuth, same-origin credentials and X-WP-Nonce. No token was placed in the final QA URLs, no new account/credential or broad security exception was created. The temporary read-only component is staging-bound and expires automatically, in addition to explicit cleanup.

## Cleanup / final deployment

Deployment run 39961660779: SUCCESS. Final custody 59/59 PASS. Rollback READY, NOT USED.

Probe removed through FTPS, absence verified. The retained browser QA action returns HTTP 404 WordPress rest_no_route for the same POST route after removal. Browser restored to the ordinary Home. Local temporary token removed; no raw token in evidence or repository.

Verified artifact SHA-256:

- Before upload, 12504379539: d6dc46f5a0d489719384f025dc35d844b74fded6c0db28c35a06a3c83a9c90fc
- After upload, 12505970845: 9619de9a0617216f1080763b9beddb72c6d3795d47e386c39151540182502146
- Final, 12507552723: 1b6b4ebf5c492ea9861392e4943859942254ccf8ce191c7ff4464627bf760799

Final verdict: **MAP-CONTROLS-01 COMPLETE — GLOBAL MAP ZOOM AND PAN CONTROLS CERTIFIED ON STAGING**

## Evidence

- MAP-CONTROLS-01E/before.json and after.json: protected state and public fixture snapshots.
- MAP-CONTROLS-01E/live-evidence.json: measured geometry, controls, five Native radii, cluster/legend and drag results.
- Native mobile/focus, Home desktop, Mappa mobile and exact/approximate detail JPEGs in the same folder.

