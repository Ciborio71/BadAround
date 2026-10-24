# NF-FINAL-01 COMPLETE — Native functional certification

Date: 2026-10-24. Environment: staging.badaround.it only.

## Baseline and scope

Runtime baseline: `0c2863b2a1bfbfb1e5c9641c78ba8d28bd21f21c`, the current composite containing MAP-CONTROLS-01 and UI-ADMIN-01. The earlier NF-FINAL UX candidate `b4aca00fe7acea5b80aa8b45d423ab915fa071e3` was not applied.

All 60 files in `deterministic/runtime-custody.json` matched current staging SHA-256 values. This includes Native config/templates/model/wizard/API/errors/Smart Address/location map/media, frozen conditional contract, schema/normalizers/validators, REST/intake/Golden Path/repository/persistence, geo/publication, map navigation, Home/detail, Classic Editor functions and the two UI-ADMIN files.

Application/runtime files changed: **NONE**. Deployment: **NONE**. Rollback: **NOT REQUIRED**, because no application bytes were uploaded. The audit branch `qa/nf-final-01-functional-certification` contains only QA automation and evidence.

Inspected source files are enumerated by the custody manifest. Closely reviewed: `inc/native-report.php`, `template-parts/native-report/{shell,step}.php`, Native `{model,wizard,api,errors,smart-address,location-map,media,media-view}.js`, core `class-badaround-{report-schema,report-v2-validator,report-v2-normalizer,report-repository,report-persistence-service,native-report-rest-controller,native-report-intake-service,native-report-golden-path-service,publication-service,native-media-config,moderation-admin}.php`, and theme `functions.php`.

The current certified implementation has **six screens**: event, location/time, details, media, contact, review. All seven requested functional areas were audited; location and time share a screen. No step restructuring was performed.

## Deterministic evidence

- Run **40093239346**: 60/60 read-only remote custody PASS; **126/126 current Native tests PASS**. Includes 33 subtype payloads, frozen 2,805 pairs, conditional activation/requiredness/stale clearing, REST/SQL persistence and idempotent retry, summary, Smart Address, media error/retry constraints, geo radius/modes, privacy boundary and social preferences.
- The same run passed backend F1.3/F1.4/F1.5, radius publication and GEO-PREC publication/lifecycle checks before reaching an obsolete test double.
- Run **40093519802 — SUCCESS**: remaining current backend/consumer/moderation/privacy/admin checks, current territory hierarchy oracle, **82 PHP files lint PASS**.
- Historic harness limitations were recorded, not treated as application regressions: old F1.6 expectations predate Native v2/GEO-AREA; the pre-cutover no-media differential expects a string where the approved request is now an object; F1.15A.3 FakeRepo lacks the later `native_payload_matches_report()` method. Frozen fixtures/tests were not edited. Current Native v2 REST/persistence, radius/no-media/retry and territory tests plus real staging persistence cover the applicable behavior.
- No responsive matrix rerun/redesign was needed. Existing certified MAP/Native/admin responsive evidence was retained.

CI run 40093239346 is **not claimed globally green**: its current Native/custody steps passed, then the obsolete FakeRepo harness failed. Run 40093519802 completed the applicable remaining checks.

## Live matrix — available viewport 1363 × 936

| Area | Evidence | Result |
|---|---|---|
| Category/subtype | vehicle → vehicle_stolen; six categories and 33 canonical subtype keys served | PASS |
| Initial validation | Continue with empty selection blocked and error summary displayed | PASS |
| Smart Address | Real Google suggestion selected by keyboard: Piazza Indipendenza, Pomezia; Lazio → Roma → Pomezia reconciled | PASS |
| Device location | Explicit button requests location; browser permission denied; localized recovery and address search remain usable | PASS for denial/recovery |
| Exact/approximate | exact_point default; explicit approximate_area defaults to 500 m; changed to 250 m | PASS |
| Map navigation | Actual zoom and directional pan; private coordinate pair and requested radius unchanged | PASS |
| Time | exact date 2026-10-23, exact time 10:15; Europe/Rome guidance; conditional fields | PASS |
| Conditional details | Vehicle type, full-plate branch, private plate notice and active-field summary | PASS |
| Navigation | Back/next preserves address, coordinates, radius, date and time | PASS |
| Media | Real no-media path; constraints and errors covered deterministically; no live file upload claimed | PASS |
| Identity/contact | Synthetic names/email; anonymous public preference; no optional contact updates | PASS |
| Declarations/social | Missing declaration blocks; all five canonical declarations set by approved bundle; independent social requested flag | PASS |
| Review | Selected values, private address/plate warnings, 250 m and exact time agree with persistence | PASS |
| Keyboard/focus | Tab to final submit; visible outline/box-shadow, 50 px button, no covered focus after native scroll settles | PASS |
| Submission/duplicate guard | Actual single submit; busy state disables Back/Submit; success focuses received heading; exactly one report/event created | PASS |
| Error/retry/idempotency | Deterministic current API/DOM/REST/SQL tests; no unnecessary second live submission | PASS |
| Moderation admin | Actual #369 Classic Editor; UI-ADMIN CSS loaded; requested 250 m shown read-only; approval/rejection not invoked | PASS |
| Public smoke | Home, Mappa, detail render; no admin CSS on frontend; Mappa remains 19 public results, new QA absent | PASS |

Live successful GPS acquisition was not available because this browser denies permission. Success/drag/provider behavior retains existing certified deterministic MAP/Native evidence; no real-device success claim is made.

## New dedicated QA object — do not publish

- Report **#81**, event **#369**. No reuse of #303/#304/#308.
- Marker: `[QA NF-FINAL-01 2026-10-24]`; description explicitly synthetic, no real vehicle/person/crime, **NON PUBBLICARE**.
- Report source `native`, status `validated`; event `ba_evento`, WordPress `pending`, moderation `new`, event state `open`.
- Frozen schema `badaround-report/v2`; governance `user-public-location/v1`.
- Category `vehicle`, subtype `vehicle_stolen`; existing taxonomy terms 6/11, territory Pomezia term 4 under Roma 3.
- Canonical private point 41.6695343 / 12.5017 and selected address stored privately.
- Requested public geo: `{"mode":"approximate_area","radius_m":250}`, version `public-location-request-v2`.
- Time `{"mode":"exact","date":"2026-10-23","knowledge":"exact","exact_time":"10:15"}`.
- Full synthetic plate stored only in report; no full plate in event metadata.
- Reporter identity/contact only in private report. Public identity mode `anonymous`, contact preference `none`.
- Social `requested`, version `social-preference-v1`, stored privately; no publication triggered.
- All five declaration booleans true; version `native-f16-v1`; privacy/publication columns 1, optional contact 0.
- Event content/excerpt empty; **no public geo projection, proof, lat/lng/radius metadata** generated prematurely.
- Detailed synthetic contract evidence: `persisted-qa-contract.json`.

## Regression and safety

GEO-PUBLIC/GEO-PRIV, MAP-CONTROLS, UI-ADMIN, Classic Editor/rejection lifecycle, publication, taxonomy/territorial hierarchy, Home/Mappa/detail, social and SEO source contracts preserved by runtime custody and targeted tests/smoke. UI-ADMIN was not modified or reopened.

Database comparison: **80/80 existing reports unchanged; 93/93 existing contents unchanged; 329/329 protected metadata rows unchanged**. Exactly one new report and one pending event. All existing taxonomy relationships preserved; only three relationships added for #369. All **8,069 term definitions and hierarchy entries** unchanged (aggregate checksums); ordinary taxonomy counts reflect the new pending fixture. No schema change, legacy migration, protected-fixture mutation or cleanup.

## Final verdict

**NF-FINAL-01 COMPLETE**

NO PRODUCTION. NO MERGE. UX-RESPONSIVE-01 NOT STARTED.
STOP before approval/publication. No further task started.

