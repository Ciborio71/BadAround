# NF-UX-05 — Final certification stopped at HTTP access gate

Date: 22 October 2026 (Europe/Rome). Environment: staging only.

**NF-UX-05 STOP — 33-SUBTYPE NATIVE V2 REGRESSION NOT CERTIFIED**

The certification cannot be issued. GitHub-hosted browser runners received HTTP 403 from the Native page and from the temporary access-controlled, read-only REST probe. No subtype semantic discrepancy has been established. The actual 33-subtype sweep, runtime PHP differential and real submissions were not executed. No application runtime patch, deployment, cache purge, hosting/security change, merge or production action was performed.

## Certified baseline custody

Application candidate: `d0ac50bf4866b326943f3f4487b49fbe3d878d7b`.

- 33/33 remote files byte-match the expected certified candidate/Core baseline: PASS.
- Five Native runtime theme files: unchanged, PASS.
- Core NF-UX-03A and backend dual-stack/C.1/C.2 runtime files: unchanged, PASS.
- Frozen JSON remote SHA-256: `dc282153243ffb80f3bd4f2a222ea0f4c4f42806fba6f3a0bde0a76855bd8e3c`.
- A fresh reload in the separate available cloud browser serves `schemaVersion=badaround-report/v2`, conditional metadata and the emergency notice: PASS for presence/version only.
- The initial cloud-browser tab contained an older previously loaded v1 document. A reload replaced it with the current v2 document. This observation is not classified as a current runtime defect or file drift.

## Blocking reproduction

1. On a GitHub-hosted runner, launch official Playwright with Chrome and navigate to `https://staging.badaround.it/segnala-un-evento/`.
2. Response: HTTP 403; title `403 Forbidden`; body: `Forbidden — You don't have permission to access this resource. Additionally, a 403 Forbidden error was encountered while trying to use an ErrorDocument to handle the request.`
3. A further standard attempt using the official bundled Chromium completed strict TLS verification and all 33 remote custody comparisons, then received HTTP 403 from the temporary protected REST probe. Its `finally` cleanup removed and checked absence of the probe.
4. The actual subtype sweep, matrix differential and all synthetic submissions remained gated/skipped. The initial longer preparation run was cancelled before any staging probes/submissions.

Expected: successful page access and an authenticated JSON probe response after normal WordPress bootstrap. Actual: HTTP 403 to the QA runner. Underlying hosting/access cause is unproven; no bot-detection diagnosis, allowlisting, fingerprint change or security relaxation was attempted.

## Execution evidence

| Run | Purpose | Result |
|---|---|---|
| [39769068915](https://github.com/Ciborio71/BadAround/actions/runs/39769068915) | Initial full certification | Cancelled during browser preparation; no staging submissions |
| [39769630618](https://github.com/Ciborio71/BadAround/actions/runs/39769630618) | Focused browser UI check | Blocked before form by non-200 HTTP |
| [39769847477](https://github.com/Ciborio71/BadAround/actions/runs/39769847477) | HTTP diagnostic | HTTP 403, captured body and screenshot |
| [39770050695](https://github.com/Ciborio71/BadAround/actions/runs/39770050695) | Read-only remote custody | PASS, 33 files |
| [39770329432](https://github.com/Ciborio71/BadAround/actions/runs/39770329432) | Bundled Chromium full certification attempt | STOP at read-only probe HTTP 403; subsequent tests/submissions skipped |
| [39770666979](https://github.com/Ciborio71/BadAround/actions/runs/39770666979) | Final read-only custody and harness absence | PASS — 33/33 and no temporary QA probe remains |

Artifacts: [HTTP response + screenshot](https://github.com/Ciborio71/BadAround/actions/runs/39769847477/artifacts/12421857246), [custody](https://github.com/Ciborio71/BadAround/actions/runs/39770050695/artifacts/12422246296), [last attempt custody](https://github.com/Ciborio71/BadAround/actions/runs/39770329432/artifacts/12421163918). Retention: 90 days. No secret/token is included in this report.

## Per-subtype actual staging certification

All rows are **BLOCKED / NOT RUN**, not semantic failures. The field-set columns were not measured; expected field sets remain available only in the frozen contract, and are not presented as actual UI results. No subtype can be awarded PASS from these runs.

| Category | Subtype | Visible set | Required set | Optional set | Hidden set | Dependencies | Emergency | Result |
|---|---|---|---|---|---|---|---|---|
| vehicle | `vehicle_stolen` | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN | BLOCKED |
| vehicle | `vehicle_parked_damage` | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN | BLOCKED |
| vehicle | `vehicle_hit_and_run` | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN | BLOCKED |
| vehicle | `vehicle_parts_stolen` | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN | BLOCKED |
| vehicle | `vehicle_observed` | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN | BLOCKED |
| vehicle | `vehicle_other` | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN | BLOCKED |
| property | `property_theft` | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN | BLOCKED |
| property | `property_attempted_breakin` | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN | BLOCKED |
| property | `property_intrusion` | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN | BLOCKED |
| property | `property_vandalism` | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN | BLOCKED |
| property | `property_observed_behavior` | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN | BLOCKED |
| property | `property_other` | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN | BLOCKED |
| hazard | `hazard_falling_element` | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN | BLOCKED |
| hazard | `hazard_road_disruption` | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN | BLOCKED |
| hazard | `hazard_flood_or_ice` | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN | BLOCKED |
| hazard | `hazard_road_obstruction` | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN | BLOCKED |
| hazard | `hazard_fire_or_smoke` | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN | BLOCKED |
| hazard | `hazard_other` | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN | BLOCKED |
| public_space | `civic_waste` | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN | BLOCKED |
| public_space | `civic_surface_damage` | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN | BLOCKED |
| public_space | `civic_public_lighting` | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN | BLOCKED |
| public_space | `civic_road_signage` | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN | BLOCKED |
| public_space | `civic_public_furniture_green` | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN | BLOCKED |
| public_space | `civic_other` | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN | BLOCKED |
| animal | `animal_missing` | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN | BLOCKED |
| animal | `animal_sighted_or_found` | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN | BLOCKED |
| animal | `animal_injured` | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN | BLOCKED |
| animal | `animal_risk` | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN | BLOCKED |
| item_document | `wallet_docs` | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN | BLOCKED |
| item_document | `keys` | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN | BLOCKED |
| item_document | `electronics` | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN | BLOCKED |
| item_document | `valuables` | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN | BLOCKED |
| item_document | `other` | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN | NOT RUN | BLOCKED |

## Required final gates

| Gate | Result |
|---|---|
| Native version | `badaround-report/v2` observed after reload |
| Application changes | NO |
| Runtime hashes | PASS — 33/33 |
| Contract SHA | PASS — exact frozen SHA above |
| 33/33 subtypes | FAIL certification — not run, HTTP gate blocked |
| Failed subtypes | No semantic failures determined; all 33 uncertified |
| 2,805 matrix | NOT RUN |
| 554 dependency activations | NOT RUN |
| Frontend/backend live differential | NOT RUN |
| Damage 2 REQUIRED / 14 OPTIONAL / 17 HIDDEN | Frozen counts verified; actual staging sweep NOT RUN |
| Eight Altro specifications, stale, summary, payload | NOT RUN |
| Nine emergency triggers, yes/unsure, false positives | Full sweep NOT RUN; no claim of no false positives |
| Requiredness, back/forward, stale errors, keyboard | NOT RUN |
| Responsive 320 / 390 / 1440 | NOT RUN |
| V1 backend / historical v1 read | NOT RUN on staging in NF-UX-05 |
| C.1 | PASS isolated quick regression: reject active-value and cross-version overwrite; 74 snapshot assertions |
| C.2 | PASS isolated quick regression: 500 assertions, v1/v2, 7 crash boundaries, 9 partial states, 10 conflict states |
| GEO / Social / consents | NOT RUN |
| No-media / staging media fail-closed | NOT RUN; no real upload attempted |
| DB schema / taxonomy | No mutation by this task; live SQL snapshot comparison NOT RUN |
| Protected fixtures #35–#68 and related events | No write/submission/moderation/publication by this task; live SQL digest comparison NOT RUN |
| Existing #68 / event #344 and residual #57 | Protected status retained; no mutation by this task |
| Temporary harness | REMOVED = YES — independently verified via FTPS directory listing |

The isolated C.1/C.2 tests ran locally against production PHP classes and an isolated SQLite test database, never staging. They are supporting regressions, not a substitute for the blocked staging differential. Their logs accompany this report.

## Real submissions

Count: **0**. New report/event IDs: **none**. No fixture register addition is needed. No publication, moderation, Social publication, Sentinelle action, real media or real PII was introduced.

## Continuation boundary

Resume NF-UX-05 only when the authorized QA runner can reach the staging page and the protected read-only probe with ordinary permitted access. The access cause needs to be established; this report does not authorize or implement hosting/security changes. The existing v2 candidate and protected fixture must remain intact. No runtime defect was proven, and no runtime fix was made. Do not start NF-HOME-01 or NF-FINAL-01.

**NF-UX-05 STOP — 33-SUBTYPE NATIVE V2 REGRESSION NOT CERTIFIED**
