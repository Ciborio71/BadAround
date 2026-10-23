# MAP-CONTROLS-01R — staging certification STOP

Candidate: `56853ec079cbd9bdd1f466104790e07f54bb60d1`
Source branch: `fix/map-controls-01a-classic-editor-reconcile`
Deployment support: `deploy/map-controls-01r-staging`

## Blocking evidence

The live Google fullscreen control on Home became aria-pressed=true, but the map remained embedded (822.28125 × 668.71875 px in the available 1363 × 936 browser viewport). The staging-only observer continued to render fullscreen=false. Repeated ordinary Google-control clicks, including a coordinate click and a semantic locator click, did not establish a certifiable entry/exit state. No application root cause is asserted: browser/platform limitation versus application behavior remains unresolved.

The mandatory live fullscreen gate was therefore NOT CERTIFIED. No application patch was attempted. The five-file batch was rolled back immediately through the guarded workflow. Remaining live checks were stopped and must not be interpreted as passed. NOT EXECUTED below means the gate was not reached, not an established application defect.

## DEPLOY

RUN: [39939688213](https://github.com/Ciborio71/BadAround/actions/runs/39939688213)

RESULT: FAIL — live certification gate

REMOTE HASHES: PASS — 5/5 candidate targets, 59/59 custody after upload

ROLLBACK: USED — byte-exact, all three pre-existing files restored; both previously absent assets removed. Final baseline custody 59/59 PASS.

Pre-upload custody/rollback artifact: 12495188092; SHA-256 4d57f5be782a2ec71a4f4d7dcb4837853c8cd37ae909ba6b4227b129e5d78d4a.
Post-upload artifact: 12495353605; SHA-256 d3b4d37d0ee0099baa76195366e3e264cb8d6253f4e44db6e60200209ae2eda1.
Final artifact: 12496291307; SHA-256 48b7bd6378fb04b5886a9c2f07aa17b4660d9ecb385af7e07b8d348df703276d.
All three downloaded and independently verified.

An initial support-only preflight (39939443197) stopped before any upload because its reconstructed Classic Editor block occupied the wrong byte position. The support tool was corrected to read approved commit 4622bec87e051f2000884abc0095b258fe2bfcb5 exactly; the second preflight passed. Application candidate was never changed.

## CLASSIC EDITOR

POST TYPE FILTER: PASS — exactly one, priority 100

POST FILTER: PASS — exactly one, priority 100

DUPLICATES: NONE

LIVE BEHAVIOR: NOT EXECUTED in admin editor. Candidate filters verified in deployed bytes and preserved in rollback. Isolated ba_evento/post/page hook behavior passed dedicated CI 39925438514.

## MAP INVENTORY

NATIVE: present on baseline; candidate live control QA NOT EXECUTED

HOME: PASS — one real Google map

PUBLIC MAP: NOT EXECUTED live; source inventory unchanged

DETAIL: NOT EXECUTED live; source inventory unchanged

MODERATION: NO MAP — unchanged certified source; read-only geographic context checked through snapshot

## CONTROLS — LIVE HOME

ZOOM +: PASS

ZOOM -: PASS

DRAG PAN: PASS

CUSTOM PAN: PASS — four directions; one panBy per activation; observed offsets [0,-167], [0,167], [-206,0], [206,0]

FULLSCREEN: FAIL certification / NOT CERTIFIED; no claim of an attributable application regression

KEYBOARD: PASS — Space activation and Tab traversal

FOCUS: PASS — :focus-visible, 3px solid rgb(7,93,105)

All four custom buttons measured 44 × 44 px, correct Italian aria labels. One pad and one Google bootstrap script observed. Stable public circle centers/radii remained unchanged through the tested Home viewport navigation.

## NATIVE

EXACT: NOT EXECUTED after upload

APPROX: NOT EXECUTED after upload

RADIUS IMMUTABLE: NOT EXECUTED live Native

MAP PAN DOES NOT MOVE REPORT POINT: NOT EXECUTED live Native

Before upload the ordinary Native v2 flow loaded and reached the location step without submission. These observations do not substitute for post-upload map certification.

## HOME

CLUSTERS: visible; interaction NOT EXECUTED before stop

LEGEND: visible, no collision observed at available desktop viewport; open/close interaction NOT EXECUTED

CONTROLS: FAIL overall due to fullscreen gate; zoom/drag/custom pan passed as detailed above

## PUBLIC MAP

CONTROLS: NOT EXECUTED

CLUSTERS: NOT EXECUTED

EXACT: NOT EXECUTED

APPROX: NOT EXECUTED

## DETAIL

EXACT: NOT EXECUTED

APPROX 100: NOT EXECUTED

APPROX 500: NOT EXECUTED

APPROX 1000: NOT EXECUTED

Protected fixture projections were checked read-only in backend snapshots: events 360/361/362 retain 100/500/1000 m; 363 retains exact_point radius 0.

## RESPONSIVE

320: NOT EXECUTED live

390: NOT EXECUTED live

768: NOT EXECUTED live

1024: NOT EXECUTED live

1440: NOT EXECUTED live

CONTROL OVERLAP: NONE OBSERVED at available desktop viewport; full responsive gate NOT EXECUTED

ATTRIBUTION OVERLAP: NONE OBSERVED at available desktop viewport; full responsive gate NOT EXECUTED

## PERFORMANCE

NEW HEAVY DEPENDENCY: NO — candidate/source

SECOND GOOGLE MAPS LOAD: NO — one bootstrap observed on Home

SECOND MAP INSTANCE: NO — one Home map observed; remaining surfaces NOT EXECUTED live

DUPLICATE CONTROLS: NONE on tested Home instance; reinitialization/back-forward NOT EXECUTED live

POLLING: NO in candidate navigation helper

## REGRESSION

GEO-PREC: PASS production-class quick regression / live full gate NOT EXECUTED

GEO-AREA: PASS production-class quick regression / live full gate NOT EXECUTED

ALL RADII: PASS existing dedicated candidate CI / live Native NOT EXECUTED

NATIVE: PASS pinned semantic/service regression; runtime snapshot remains badaround-report/v2

SMART ADDRESS: PASS pinned regression / post-upload live selection NOT EXECUTED

GEOLOCATION: NOT EXECUTED live; candidate initializer bytes outside navigation changes preserved

HOME: loads; partial live controls PASS; fullscreen gate not certified

DISCOVERY: PASS read-only fixture output and pinned public-consumer regression

DETAIL: PASS pinned consumer regression / live maps NOT EXECUTED

MODERATION: PASS read-only snapshot; no geographic input controls detected

CLASSIC EDITOR: filters preserved; admin live behavior NOT EXECUTED

C.1: PASS quick workflow tests

C.2: PASS quick workflow tests

DB: UNCHANGED — before/after schema and protected-state digests identical

TAXONOMY: UNCHANGED — terms/taxonomy/termmeta/relationships digests identical

PROTECTED FIXTURES: UNCHANGED — reports, events, meta, audit, fixture projections and content hashes identical

New reports/events: 0. No moderation, publication, merge, production operation or NF-FINAL-01 deployment.

## CLEANUP

Temporary staging-only expiring token-protected read-only observer removed.

FTPS absence: VERIFIED.

POST /mapcontrols01s-qa/v1/snapshot after removal: 404 rest_no_route.

Raw temporary token files removed. No persistent QA route or security change remains.

Staging returned to certified GEO-PREC + GEO-AREA + Classic Editor baseline. MAP-CONTROLS candidate is NOT active.

## FINAL VERDICT

MAP-CONTROLS-01 STOP — GLOBAL MAP NAVIGATION NOT CERTIFIED ON STAGING
