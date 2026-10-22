# GEO-PREC-01 — pre-deploy certification

Branch: `feature/geo-prec-01-user-authoritative`  
Candidate: `48bab2229c00fb28f01d54117dd1278074f10d69`  
Base: `a4ef16aa067afc94a96cacd55a46a62d1248ed93`

## Implementation boundary

Seven runtime files, all in `wordpress/plugins/badaround-core/includes/`:

- `class-badaround-audit-log.php`
- `class-badaround-geo-moderation-service.php`
- `class-badaround-moderation-admin.php`
- `class-badaround-public-geo-contract.php`
- `class-badaround-public-geo-projection.php`
- `class-badaround-publication-service.php`
- `class-badaround-report-repository.php`

No Native/Home, bootstrap, schema installer, canonical taxonomy, conditional JSON,
map, Social, SEO or media implementation file changed. No staging access/write,
real report/event, probe, deployment, production action or merge occurred.
NF-FINAL-01 remains unchanged at `b4aca00fe7acea5b80aa8b45d423ab915fa071e3`.

## Cutover

Server-only top-level envelope signal:
`content_original.public_geo_governance = "user-public-location/v1"`.
Canonical report/Native payload has no new field. No database column/migration.

New v2 records are marked in the atomic initial canonical reservation; new v1
records at first canonical persistence into a bare reservation. Existing nonempty
snapshots, including interrupted v2 reservations, preserve their bytes on retry.
Absent marker means legacy. Unknown/null marker fails closed. Neither schema
version, event ID nor date is used to infer governance. New marked records require
a valid explicit persisted user request; no mode is inferred at publication.

## Moderation and publication

| Gate | Result |
|---|---|
| Reporter choice read-only; moderator mode/radius/coordinate controls removed for new reports | PASS |
| Whole-report approval/rejection and mandatory rejection reason | PASS |
| Crafted POST: exact to approximate, approximate to exact, same mode, unsupported mode | BLOCKED |
| Persisted request cannot be mutated; ordinary saves preserve geometry/lifecycle | PASS |
| User exact -> public exact | PASS |
| User approximate -> public approximate, unchanged system radius | PASS |
| Rejected exact/approximate reports remain non-public | PASS |
| Injected decision/proof, missing request, unknown policy, source mismatch | FAIL-CLOSED |
| Publication transaction/audit failure and retry | PASS |
| No new moderator geographic decision or decision audit | PASS |
| Private source segregated; consumers read approved sealed public projection only | PASS |
| Historical records, moderator decisions, audit and published projections | PRESERVED |

## Regression

| Gate | Result |
|---|---|
| Native v2 and 33 oracle paths | PASS |
| 2,805 matrix, 554 dependencies, frontend/backend differential | PASS |
| Damage 2/14/17, Altro 8/8, stale clearing, summary/payload, emergency | PASS |
| V1 validator/normalizer/HTTP/Golden Path compatibility | PASS |
| C.1 immutability/persistence | PASS |
| C.2 event recovery | PASS |
| Smart Address, private GEO, territory-only and public rendering | PASS |
| Social projection boundary and SEO privacy | PASS; consumer files unchanged |
| No-media | PASS; 324 differential scenarios |
| Media provenance/fail-closed/binding compatibility | PASS; 169+74 integration assertions on MariaDB |
| DB schema, taxonomy | UNCHANGED |
| Protected staging fixtures #35–#71, residual #57 and historical GEO fixtures | UNCHANGED; no staging writes |

## CI evidence

[Run 39871909912](https://github.com/Ciborio71/BadAround/actions/runs/39871909912):
**SUCCESS**, job `120148099699`. PHP lint and all gate steps PASS. Node: 134/134,
zero failures. Actual MariaDB context query, new v1/v2 persistence and historical
oracles PASS. Frozen contract SHA:
`dc282153243ffb80f3bd4f2a222ea0f4c4f42806fba6f3a0bde0a76855bd8e3c`.

[Artifact 12467320366](https://github.com/Ciborio71/BadAround/actions/runs/39871909912/artifacts/12467320366):
30 evidence logs, downloaded and SHA-256 verified:
`1c8bd971a622908e004f741366385b16bf57e8aca629923acacfb943768f3ef9`.
Runtime file SHA-256 values are in `GEO-PREC-01-predeploy-evidence.json` alongside
this report.

The separate general PHP Syntax run `39871909861` still fails only in legacy
“Test recovery golden-state invariants”: missing `assets/js/report.js`. Dedicated
candidate lint passes. That workflow was not modified.

## Test adjustments

The two frozen v1 differential suites explicitly assert and then exclude only the
new server-envelope governance marker when comparing historical behavior. All
canonical fields, HTTP results, other stored fields, event counts/identity and
retry/conflict expectations remain exact. The SQLite adapter recognizes the added
JSON presence path; CI also executes actual MariaDB SQL. Historical publication
fixtures now load the real moderation class; their original assertions remain.

## Verdict

**GEO-PREC-01 READY FOR STAGING DEPLOY — USER GEO CHOICE AUTHORITATIVE**

STOP. No deploy authorized here. No GEO-AREA-01, map controls or NF-FINAL-01 resumed.
