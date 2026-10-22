# NF-UX-05B — split certification evidence and remaining browser limitation

Date: 2026-10-22. Repository: Ciborio71/BadAround. Staging only.

**NF-UX-05 BLOCKED — REAL BROWSER QA INFRASTRUCTURE UNAVAILABLE**

No actual application semantic regression was found. Deterministic certification and actual deployed-page semantic interactions passed. Final certification is withheld because this authorized cloud browser exposes a fixed 1348 × 936 viewport and no supported viewport resizing capability: the required fresh 320, 390 and 1440 px browser checks could not be executed. Prior responsive evidence is not counted as a new run. No GitHub-hosted staging Chromium retry was used.

## APPLICATION CUSTODY

| Check | Result |
|---|---|
| Runtime custody | PASS — 33/33 remote byte hashes |
| Application changes | NO |
| Security changes / permanent relaxation | NO / NO |
| Application candidate | `d0ac50bf4866b326943f3f4487b49fbe3d878d7b` |
| Native version | `badaround-report/v2` |
| Contract SHA | PASS — `dc282153243ffb80f3bd4f2a222ea0f4c4f42806fba6f3a0bde0a76855bd8e3c` |

Custody includes five Native v2 files, NF-UX-03A bootstrap, dual-stack backend, C.1/C.2 classes and frozen JSON. CI compares the entire application tree to the candidate and the plugin tree to NF-UX-03A. FTPS uses verified server identity and TLS; it only reads application files. No merge, production deployment or runtime application edit occurred.

## CI SEMANTIC CERTIFICATION

| Check | Result |
|---|---|
| 33 oracle | PASS — 33/33 |
| 2,805 matrix | PASS — 2,805/2,805 |
| 554 dependencies | PASS — exact count asserted; activation/stale differential exercised |
| Frontend/backend differential | PASS |
| Damage | PASS — 2 REQUIRED / 14 OPTIONAL / 17 HIDDEN |
| Altro 8/8 | PASS — activation, requiredness, limits, canonical keys, Unicode/plain text, stale clearing, summary and payload |
| Emergency | PASS — frozen nine; yes/unsure; no false positives; dual trigger singleton |
| V2 Golden Path service | PASS — 33 actual Native payloads through isolated REST/service/SQL persistence, pending and retry |
| V1 | PASS |
| C.1 | PASS |
| C.2 | PASS |

Main [run 39782339986](https://github.com/Ciborio71/BadAround/actions/runs/39782339986): SUCCESS, 101 Node tests and 24 PHP suites. This is isolated CI semantic certification, not a substitute for deployed-browser QA. [Semantic artifact](https://github.com/Ciborio71/BadAround/actions/runs/39782339986/artifacts/12427427660); [33-file custody artifact](https://github.com/Ciborio71/BadAround/actions/runs/39782339986/artifacts/12426828788).

Supplemental [run 39787383242](https://github.com/Ciborio71/BadAround/actions/runs/39787383242): SUCCESS. Exact 554 dependency assertion and 36/36 emergency DOM checks using a QA-only copy of the frozen emergency suite with the expected frontend version set to v2. No original test or application file changed.

## REAL BROWSER

The cloud browser reached and executed the actual deployed `/segnala-un-evento/` page and its assets. Interactions used visible controls and keyboard; read-only DOM observations compared current fields against the deployed frozen configuration. No local synthetic DOM was used for this part. All 33 distinct canonical subtype choices passed the minimum sweep. Hidden/visible questions and required markers were checked after relevant progressive answers, with immediate danger=no for automatic-warning classification.

| Check | Result |
|---|---|
| 33/33 subtype sweep | PASS |
| Altro 8/8 | PASS |
| Stale clearing | PASS — subtype/category, reward, authority and specification transitions |
| Summary | PASS — actual three summaries agree with persisted canonical fields under frozen summary/GEO projection |
| 320 | NOT RUN — browser viewport capability unavailable |
| 390 | NOT RUN — browser viewport capability unavailable |
| 1440 | NOT RUN — browser viewport capability unavailable |
| Actual 1348 × 936 | PASS — usable form, CTA, dependent fields and emergency notice; no observed horizontal overflow |
| Keyboard | PASS — Tab, Space and Enter, including dependent fields and submissions |
| Accessibility | PASS targeted at available viewport — required semantics, associated blocking errors, hidden/disabled dependent controls, reachable Altro field, semantic emergency text, no observed focus trap |

The exact viewport gaps are infrastructure limitations, not application FAIL results. No CSS mutation, browser fingerprint change or synthetic viewport substitution was used. Deep checks covered simple/complex, damage-required/hidden, Altro, reward, authority and emergency profiles; forward/back and parent changes were exercised. Reward missing amount/conditions blocked forward with associated errors; choosing no reward removed stale fields/errors and forward then succeeded. Authority other→no cleared old type/specification. Every Altro activation became required and cleared after the parent changed.

### Per-subtype actual browser results

| Category | Subtype | Damage | Automatic emergency | Result |
|---|---|---|---|---|
| animal | animal_injured | HIDDEN | True | PASS |
| animal | animal_missing | HIDDEN | False | PASS |
| animal | animal_risk | HIDDEN | True | PASS |
| animal | animal_sighted_or_found | HIDDEN | False | PASS |
| hazard | hazard_falling_element | OPTIONAL | True | PASS |
| hazard | hazard_road_disruption | OPTIONAL | True | PASS |
| hazard | hazard_flood_or_ice | OPTIONAL | True | PASS |
| hazard | hazard_road_obstruction | OPTIONAL | True | PASS |
| hazard | hazard_fire_or_smoke | OPTIONAL | True | PASS |
| hazard | hazard_other | OPTIONAL | True | PASS |
| item_document | wallet_docs | HIDDEN | False | PASS |
| item_document | keys | HIDDEN | False | PASS |
| item_document | electronics | HIDDEN | False | PASS |
| item_document | valuables | HIDDEN | False | PASS |
| item_document | other | HIDDEN | False | PASS |
| property | property_theft | OPTIONAL | False | PASS |
| property | property_attempted_breakin | OPTIONAL | False | PASS |
| property | property_intrusion | OPTIONAL | False | PASS |
| property | property_vandalism | REQUIRED | False | PASS |
| property | property_observed_behavior | HIDDEN | False | PASS |
| property | property_other | OPTIONAL | False | PASS |
| public_space | civic_waste | HIDDEN | False | PASS |
| public_space | civic_surface_damage | HIDDEN | False | PASS |
| public_space | civic_public_lighting | HIDDEN | False | PASS |
| public_space | civic_road_signage | HIDDEN | False | PASS |
| public_space | civic_public_furniture_green | HIDDEN | False | PASS |
| public_space | civic_other | HIDDEN | False | PASS |
| vehicle | vehicle_stolen | OPTIONAL | False | PASS |
| vehicle | vehicle_parked_damage | REQUIRED | False | PASS |
| vehicle | vehicle_hit_and_run | OPTIONAL | True | PASS |
| vehicle | vehicle_parts_stolen | OPTIONAL | False | PASS |
| vehicle | vehicle_observed | HIDDEN | False | PASS |
| vehicle | vehicle_other | OPTIONAL | False | PASS |

### Eight field-level Altro specifications

All PASS: `location.place_type_other_detail`, `vehicle.type_other_detail`, `vehicle.color_other_detail`, `authority.type_other_detail`, `property.tampered_entry_point_other_detail`, `property.access_method_other_detail`, `property.stolen_item_categories_other_detail`, `animal.type_other_detail`.

Global danger yes/unsure/no all matched the contract. Exactly nine automatic notices were observed, with no false positive on the other 24 subtypes. Dual automatic+unsure trigger on the actual emergency submission rendered one notice.

## REAL SUBMISSIONS

COUNT: **3**, exactly. No fourth submission needed. The execution order was B, A, C.

| Profile | Report | Event | Subtype | Public-location request | Schema | Event state |
|---|---:|---:|---|---|---|---|
| B — Altro + damage hidden | 69 | 345 | animal_missing | exact_point | badaround-report/v2 | pending |
| A — simple | 70 | 346 | civic_waste | approximate_area | badaround-report/v2 | pending |
| C — multi-conditional/emergency | 71 | 347 | hazard_fire_or_smoke | exact_point | badaround-report/v2 | pending |

| Assertion | Result |
|---|---|
| All v2, envelope and canonical | PASS |
| All pending / Da moderare | PASS |
| Exact request | PASS — #69 and #71 |
| Approximate request | PASS — #70 |
| Canonical field set / no inactive stale fields | PASS — read from staging SQL, real PHP evaluator/normalizer recheck unchanged |
| Correct subtype and territory | PASS — SQL term bindings and canonical both match |
| Social declined / five consents preserved | PASS — all three |
| Idempotent identical retry | PASS — #69 remains report 69/event 345, duplicate=true, COMPLETED |
| Exactly one report/event per submission | PASS — only reports 69–71 and their distinct events 345–347 |
| No publication | PASS — all remain pending; no moderation/publication/Social/Sentinelle action |

All data are synthetic, reserved `.invalid` contact, no real PII or media. The retry reuses the persisted canonical through the existing Golden Path service, under an administrator+token guard restricted to these three fixtures. It is backend retry proof, not a second ordinary UI submission.

All three actual Smart Address flows resolved the public place Torvaianica, Lazio → Roma → Pomezia → Torvaianica. Canonical private coordinates, territory and versioned public-location request were preserved. Requested exact_point does not force publication or reinterpret the existing public_precision adapter.

Device geolocation permission was denied in this cloud environment. Graceful denial preserved the valid address and allowed completion. A fresh positive device-location result is **not claimed**; Smart Address, private exact point, territory and both request modes were verified live. There was no positive real media upload.

## REGRESSION

| Check | Result |
|---|---|
| GEO | PASS for Smart Address, private exact point, territory, exact/approximate request and denial fallback; positive device-location acquisition NOT RUN |
| Social | PASS — declined preserved in all three canonical payloads |
| Consents | PASS — truthfulness, media_rights, publication_rules, terms, privacy all preserved |
| No-media | PASS — three real no-media successes and CI differential |
| Media fail-closed | PASS — actual staging configuration media_enabled=false; fail-closed/binding tests in CI |
| DB schema | UNCHANGED — 269 ordered information_schema column metadata rows identical before/after; no DDL issued |
| Taxonomy | UNCHANGED — terms, taxonomy and termmeta digests identical |
| Previous protected fixtures | UNCHANGED — reports 1–68, related prior events/meta and prior relationships digests identical, including #57 and #68/#344 |

Database row additions are exactly the authorized new synthetic fixtures. Schema custody covers column type, nullability, default, extra and collation on report/core taxonomy/event tables; no separate index-definition audit is claimed. `nf-ux-05b-evidence/db-custody.json` records the before/after metadata and seven data digests. Aggregate data custody uses per-row SHA256 over ordered columns, count and four 64-bit XOR lanes to avoid GROUP_CONCAT truncation; it is not a complete database backup.

## TEMPORARY QA COMPONENTS

REMOVED: **YES**. Temporary `wp-content/mu-plugins/nf05b-verification.php` was limited to the exact staging host, administrator capability, secret token, fixture IDs 69–71, synthetic marker and pending/no-media/Social-declined guards. Private/no-store response. First invocation performed the authorized existing-service identical retry; second bounded installation was read-only for bootstrap/v1/media/hash confirmation. Each self-unlinked in finally, with FTPS finally cleanup/absence verification. No token is stored in reports or artifacts.

Unauthorized request: **403 WordPress JSON `nf05b_denied`**. Authorized request: **200 JSON**. After use: **404 `rest_no_route`**. Final cleanup artifact states `removed=true`, `absence_verified=true`: [run 39787383242 artifact](https://github.com/Ciborio71/BadAround/actions/runs/39787383242/artifacts/12429677632). Initial retry channel [run 39786782217](https://github.com/Ciborio71/BadAround/actions/runs/39786782217) also finished successfully. No application semantic/security setting was changed.

Final compact WordPress bootstrap verification: contract class definition loaded 33 rows, exact frozen SHA; historical v1 canonical accepted by existing validation path; media_enabled=false; fixture snapshot unchanged, report_count=1 and event_pending=true.

## INFRASTRUCTURE NOTE

GITHUB RUNNER 403: **KNOWN INTERMITTENT QA-INFRA ISSUE**. Historical origin remains UNKNOWN. APPLICATION DEFECT: **NO semantic defect observed**. No GitHub runner staging HTTP/browser retry, WAF bypass, allowlist, fingerprint spoofing or security change was attempted. The separate legacy report.js workflow was not repaired or treated as this task's blocker.

## Final gate and continuation

The missing fresh 320/390/1440 browser checks prevent a COMPLETE declaration. Finish them in an authorized browser environment with supported viewport controls; reuse the protected #69–71 fixtures and current successful evidence. No new submission is needed for the remaining viewport work. Do not start another track, merge or deploy production.

**NF-UX-05 BLOCKED — REAL BROWSER QA INFRASTRUCTURE UNAVAILABLE**
