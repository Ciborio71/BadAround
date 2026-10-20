# GEO-PUBLIC-06 — Staging certification

Date: 2026-10-20 UTC. Scope: public rendering only, https://staging.badaround.it.

GEO-PUBLIC-06 COMPLETE — MAP, DETAIL & DISCOVERY RENDERING CERTIFIED

## EXACT_POINT #303

| Check | Result |
|---|---|
| HOME MAP | PASS |
| MAIN MAP | PASS |
| DETAIL | PASS |
| DISCOVERY | PASS |
| PIN TIP ANCHOR | PASS |
| GROUND SHADOW | PASS |
| CATEGORY COLOR | PASS |
| ICON | PASS |
| NO RADIUS | PASS |

## APPROXIMATE_AREA #304

| Check | Result |
|---|---|
| HOME MAP | PASS |
| MAIN MAP | PASS |
| DETAIL | PASS |
| DISCOVERY | PASS |
| GEOGRAPHIC CIRCLE | PASS |
| RADIUS 1000 M | PASS |
| CENTER BADGE | PASS |
| NO DROP PIN | PASS |
| PUBLIC CENTER STABLE | PASS |

## TERRITORY_ONLY #269

| Check | Result |
|---|---|
| NO MARKER | PASS |
| NO AREA | PASS |
| NO CENTROID FALLBACK | PASS |
| NO ERROR | PASS |

## LEGACY #290

| Check | Result |
|---|---|
| PUBLIC PROJECTION UNCHANGED | PASS |
| LEGACY AREA SEMANTICS | PASS |

## PRIVACY

| Check | Result |
|---|---|
| PUBLIC GEO ONLY | PASS |
| PRIVATE COORDINATES ABSENT FROM PUBLIC DOM/API | PASS |
| PRIVATE ADDRESS ABSENT | PASS |

## REGRESSION

| Check | Result |
|---|---|
| GEO-PUBLIC-05 | PASS |
| GEO-PUBLIC-04 | PASS |
| GEO-PUBLIC-03 | PASS |
| GEO-PRIV-03A | PASS |
| SMART ADDRESS | PASS |
| SOCIAL | PASS |
| CLUSTERING | PASS |
| FILTERS | PASS |
| RESPONSIVE | PASS |

## Implementation

BRANCH: `fix/geo-public-06-public-rendering`

CANDIDATE SHA: `0073e5925b7af65b81e3a26cc1e853487a7dcd46`

DEPLOY BRANCH: `deploy/geo-public-06-staging`

DEPLOY COMMIT: `81138a1d592b8ad4630820bf7ce459b2d3480db1`

CI: [39502545713 — SUCCESS](https://github.com/Ciborio71/BadAround/actions/runs/39502545713).
PHP contract/lifecycle/privacy/publication/audit tests; 8 renderer tests; 111 native, moderation, GPS, Smart Address and Social Node tests; Chromium/WebKit native location browser regression at 320/390/768/1440 px.

DEPLOY: [39502835656 — SUCCESS](https://github.com/Ciborio71/BadAround/actions/runs/39502835656).
Only the two runtime files below were uploaded. Prior remote bytes matched certified baseline 67b95849155cec49086cbab7292718ba762e4ec1. Candidate CI and exact runtime diff enforced before upload. Strict FTPS identity verification. Rollback uploaded as artifact before runtime upload. Remote bytes downloaded and compared byte for byte after upload.

FILES CHANGED (runtime):

- `wordpress/themes/badaround-child/assets/js/map.js`
- `wordpress/themes/badaround-child/template-parts/single-event-layout.php`

Candidate tests/workflows:

- `tests/geo-public-06-rendering.cjs`
- `.github/workflows/geo-public-06-rendering-qa.yml`

Deployment/QA harness files, committed separately from runtime candidate:

- `.github/workflows/geo-public-06-staging-deploy.yml`
- `.github/workflows/geo-public-06-real-staging-qa.yml`
- `tests/geo-public-06-real-staging.cjs`

This report and the fixture digest snapshot are documentation only.

ROLLBACK: [artifact 12303045907](https://github.com/Ciborio71/BadAround/actions/runs/39502835656/artifacts/12303045907), `geo-public-06-byte-exact-rollback`, captured before upload, available; not used. Both original runtime files retained byte for byte.

REMOTE HASHES (SHA-256):

| Runtime file | Verified remote SHA-256 |
|---|---|
| map.js | `4126dca40efe5ef3bbd1163ef4c6b88ec3f7e14ac4b03d3a656fb6c2e6800a5f` |
| single-event-layout.php | `5ae431b916bb144dbb3f1a91522575243edb68e67bb6c7cddcf121a64a2fc8c1` |

Hash manifest: [artifact 12301342525](https://github.com/Ciborio71/BadAround/actions/runs/39502835656/artifacts/12301342525).

## Real staging evidence

Chromium: [job 118904756795 — SUCCESS](https://github.com/Ciborio71/BadAround/actions/runs/39502963646/job/118904756795). 16 responsive map/detail combinations, Discovery, territory-only, legacy, privacy and stable center assertions passed. 26 screenshots plus results and public Discovery snapshot: [artifact 12303196380](https://github.com/Ciborio71/BadAround/actions/runs/39502963646/artifacts/12303196380).

Chromium harness source: commit `81138a1d592b8ad4630820bf7ce459b2d3480db1`, `tests/geo-public-06-real-staging.cjs`. Real Google Maps constructors were observed inside the QA browser to assert coordinates, icon geometry, meter radius and projection layers. Server script bytes were verified against the candidate before observation; no server data or assets were changed by the harness.

WebKit, normal uninstrumented pages: [39504126469 — SUCCESS](https://github.com/Ciborio71/BadAround/actions/runs/39504126469), job 118908649985. All 16 combinations (Home, Mappa, exact detail, approximate detail × 320/390/768/1440 px) passed without JavaScript errors. Exact pin/shadow and approximate badge SVG were verified in the actual DOM. 16 screenshots and results: [artifact 12303202764](https://github.com/Ciborio71/BadAround/actions/runs/39504126469/artifacts/12303202764).

Normal WebKit harness source: commit `ec72bf86bf9e55115cacb77b57e25e98681ac1b9`. No response interception, constructor observation, API mocking or server writes.

Earlier diagnostic WebKit runs encountered Google Maps `controls` loading errors under observation; one separate runner received HTTP 403 from staging. Those attempts are retained in Actions history and are not claimed as passing evidence. The final normal WebKit check passed every requested width with zero page errors. No Google configuration or site access protection was changed.

Representative Chromium screenshots, present in artifact 12303196380:

- `main-1440-exact.png`, `home-1440-exact.png`
- `detail-303-1440.png`
- `main-1440-approximate.png`, `home-1440-approximate.png`
- `detail-304-1440.png`
- `territory-269.png`
- `legacy-290.png`
- `home-320-exact.png`, `main-320-approximate.png`
- `detail-303-390.png`, `detail-304-390.png`

Artifacts retained for 30 days by workflow configuration.

## Rendering and privacy contract

- Exactness is selected only by explicit `exact_point`, never by radius, decimals or coordinate equality. Exact icon tip is (28,64), matching its Google Maps anchor in every state. Separate ellipse shadow has center/anchor (12,6) at the same coordinate. Exact mode never creates a radius circle.
- Explicit `approximate_area` uses a Google Maps Circle with radius in meters and a circular badge anchored at its center. #304 remains 1000 m. Both circle and badge select the same event.
- Existing category colors and icon registry are reused. Selected, hover and resolved states retain their anchors. Projection layers hide and return together during clustering. Repeated cluster redraw does not duplicate circles or shadows.
- Home and Mappa call the same projection renderer. Detail calls that renderer with approved public projection fields. Detail renders once, including a fit to approximate circle bounds. Public category/status fallback comes from the public event template, so rendering does not depend on being within the first Discovery page.
- #269 has null Discovery geometry and no detail map element, marker, circle or fabricated centroid. Safe territorial text remains.
- Legacy point/street/area/municipality use the existing generalized renderer. #290 retains its existing public center and 1000 m radius. No semantic or data migration.
- Public JS reads `item.public_geo` only; detail exposes approved public fields. There is no private coordinate/address fallback. Public API/DOM assertions found no private field keys or private address. Approved exact coordinates for #303 are intentionally public under the certified projection; absence of private data does not mean suppressing that authorized point.
- Repeated public Discovery reads preserve #304 geometry. The public category filter returns [304,303,294] for Spazi pubblici and preserves their modes.
- Renderer tests exercise invalid/absent geometry, explicit exact/area selection, no radius on exact, no pin body on approximate, territory-only, legacy modes, shared Home/Mappa behavior, single detail map, category exclusions, selected state, resolved registry, and cluster layer lifecycle.
- GEO-PUBLIC-05, GEO-PUBLIC-04, GEO-PUBLIC-03, GEO-PRIV-03A, Smart Address, native location and Social automated regressions passed without altering their implementations.

## Protected fixtures

Post content/title/status/slug digest and full-value meta digests were compared before deployment and after all real browser checks. All 11 event snapshots match. `_edit_lock` and `_edit_last` are excluded; no fixture writes were performed.

| Fixture | Result |
|---|---|
| #252 | UNCHANGED |
| #260 | UNCHANGED |
| #269 | UNCHANGED |
| #290 | UNCHANGED |
| #294 | UNCHANGED |
| #298 | UNCHANGED |
| #300 | UNCHANGED |
| #301 | UNCHANGED |
| #302 | UNCHANGED |
| #303 | UNCHANGED |
| #304 | UNCHANGED |

The adjacent `geo-public-06-fixture-snapshot.json` records the before/after digest values. No private coordinate or address values are included.

## Mutations

| Item | Result |
|---|---|
| PUBLIC PROJECTIONS CHANGED | NO |
| DB SCHEMA | UNCHANGED |
| MERGE | NO |
| PRODUCTION | UNCHANGED |
| SOCIAL PUBLICATION | NO |
| SEO CHANGE | NO |
| PRIVATE GEO CONTRACT | UNCHANGED |
| F1.2 | UNCHANGED |
| LEGACY FIXTURE MIGRATION | NO |

No event moderation, republication or projection regeneration was performed. GEO-PUBLIC-07 was not started.

## Final verdict

GEO-PUBLIC-06 COMPLETE — MAP, DETAIL & DISCOVERY RENDERING CERTIFIED
