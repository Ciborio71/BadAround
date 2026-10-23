# GEO-PREC-01R — composite staging certification

**GEO-PREC-01 COMPLETE — USER GEO CHOICE AUTHORITATIVE AND PUBLIC CONSUMERS CERTIFIED ON STAGING**

Staging only: https://staging.badaround.it/

- Governance candidate: `48bab2229c00fb28f01d54117dd1278074f10d69`.
- Composite runtime candidate: `29a202fd47d6ba6b7a8109b6a596a71f7508109f`.
- Deploy run: [39879232412 — SUCCESS](https://github.com/Ciborio71/BadAround/actions/runs/39879232412).
- Pinned pre-deploy regression: [39878224374 — SUCCESS](https://github.com/Ciborio71/BadAround/actions/runs/39878224374).
- No merge, production deployment, Native UX change, radius selector, map-controls change or NF-FINAL-01 deployment.

## Deployment and custody

Exactly the authorized nine runtime files were uploaded. Baseline was the certified rollback state at `a4ef16aa067afc94a96cacd55a46a62d1248ed93`; no unexplained drift was found. All nine original files were downloaded byte-exact and verified against that baseline, archived before upload, then independently downloaded and verified from the rollback artifact.

Pre-upload custody: **55/55 PASS**, including Home NF-HOME-01, Native v2, Core bootstrap and frozen conditional JSON. Candidate remote hashes: **9/9 PASS**. Post-upload and final custody: **55/55 PASS**. No other application runtime file was uploaded. Each file transfer used verified FTPS/TLS and temporary-file rename; rollback was prepared for the whole nine-file batch and was **not used**. No claim is made that nine separate filesystem renames constitute a single filesystem transaction.

The final hash check completed at 2026-10-23T00:51:57Z. Temporary probe removal/absence verification completed at 2026-10-23T00:51:59Z.

## Actual staging consumers

| Protected fixture | Detail copy | DOM/map mode | Radius | Result |
|---|---|---|---|---|
| Report 73 / Event 352 | Posizione esatta | exact_point | 0 | PASS |
| Report 74 / Event 354 | Posizione approssimativa | approximate_area | 1000 m | PASS |

These checks used the actual deployed PHP template and a real unauthenticated cloud Chromium browser, not synthetic DOM replacement. Screenshots show the exact pin and the approximate area/circle. The exact detail contains no approximate wording. Approximate geometry differs from its private source point. Only approved public geometry is exposed. Public JSON/DOM contain no private source fields/address, governance proof or reporter contact details; exact coordinates are public only through the reporter-authorized exact projection.

Public discovery was opened unauthenticated in the real browser at `/wp-json/badaround/v1/discovery?limit=100`: Event 352 has exact_point/radius 0; Event 354 has approximate_area/radius 1000. Modes are nonempty. Pending/rejected Events 356/358 are excluded; Event 269 has no geometry. Public Home and Mappa were reloaded and their clusters expanded through UI controls: both show the corresponding exact and approximate marker labels. No map consumer code beyond the authorized discovery fix changed.

## Historical custody

Events **303, 304 and 269** retain their old projection, moderation decision, audit and metadata. Their complete server-rendered detail HTML hashes are byte-identical before/after deployment. Browser copy and map datasets are also identical: exact, approximate/1000 m and territory-only/no map respectively.

Before/after hashes for **all existing report rows, event rows, event metadata, relationships, report/event audits, terms, taxonomy rows and term metadata match**. Schema hashes match across **48 prefixed tables**, with only AUTO_INCREMENT counters normalized in schema definitions. No historical migration, projection rebuild, moderation or republication occurred.

## Governance and negative QA

Actual production moderation box rendering shows the reporter request read-only for Events 352/354/356/358. No moderator mode selector or coordinate/radius input is exposed. Persisted requested mode matches the published projection for both published governed fixtures. Existing A/B whole-report approval and C/D whole-report rejection records were reused read-only; isolated production service tests cover the approval/rejection paths without repeating them on protected fixtures.

Actual `process_geo_decision_request()` calls against pending/rejected governed Events 356/358, with valid administrator authorization/nonce, reject exact, approximate, same-mode and unsupported injected requests with `ba_geo_decision_user_authoritative`. No request, projection, event or audit mutation occurs; complete before/after data digests match.

Negative consumer cases run against deployed reader/template/discovery code using request-local `get_post_metadata` overrides. They never persist invalid metadata. Overrides are removed in `finally`:

- missing sealed projection → no map, no discovery geometry, no mirror fallback;
- all public versioned metadata missing → existing classifier prevents legacy fallback;
- unknown proof authority → fail closed;
- source-proof mismatch → fail closed;
- public mirror mismatch → fail closed;
- unknown governance envelope marker → production contract returns `ba_public_geo_governance_invalid`, without storing the invalid marker;
- actual unpublished Events 356/358 → no public geometry.

No exact-to-approximate coercion was observed.

## Regression evidence

The deploy run passed **79 Node tests**, actual PHP consumer/governance integration tests, C.1 gate, C.1 persistence (**74 assertions**) and C.2 recovery (**500 assertions**). The deterministic tests exercise 33 subtype oracles/2,805 matrix pairs; the frozen contract preserves 554 dependency activations. No GitHub-hosted browser or staging browser retry was used.

Native config remains `badaround-report/v2`; Home/Native files retain their baseline bytes. Smart Address and public privacy-boundary tests pass. Social request data stays private and public consumers use only the approved projection. SEO consumers remain unchanged; no private source data appears in the public pages. Current media fence is **disabled for uploads / fail-closed** (`enabled=false`); positive media binding/no-media compatibility evidence remains the passing pinned pre-deploy CI. No real media upload or Social/Sentinel publication was invoked.

Browser console observations contain the cloud environment's “Error sending browser metadata to extension” diagnostic; no application JavaScript error was observed. This extension diagnostic is not an application regression.

## Fixtures and cleanup

**New reports: 0. New events: 0.** No UUID was reused. Protected fixtures, including Reports 35–76 and related historical events, were not re-moderated, republished, migrated or deleted. Reports 73/74 remain published; 75/76 remain rejected/pending, exactly as before this run.

The temporary WordPress probe was restricted to the staging host, administrator capability plus a random secret token, expiry, read-only snapshot/negative-check actions, private/no-store responses and no indexing. No-token access returned the expected WordPress JSON 403. It created no options or fixture rows. The MU-plugin was removed and FTPS absence verified. A subsequent POST to its route returned **404 `rest_no_route`**. The local raw token was deleted. No permanent QA/security component or security relaxation remains.

## Deferred copy

**NATIVE MODERATION COPY: STILL STALE**, intentionally unchanged.

`wordpress/themes/badaround-child/template-parts/native-report/step.php:36`:

> La tua scelta sarà valutata dalla moderazione, che potrà ridurre la precisione per tutelare la privacy. Indirizzo completo e numero civico non vengono pubblicati automaticamente.

NF-FINAL-01 remains ON HOLD. No follow-on task was started.

## Evidence

- [Sanitized structured evidence](GEO-PREC-01R-staging-evidence.json)
- [Exact detail screenshot](GEO-PREC-01R-event352-exact.jpg)
- [Approximate detail screenshot](GEO-PREC-01R-event354-approx.jpg)
- Rollback artifact **12469922134**, SHA-256 `ec868c622cecff0f9871783a97586927da8610101d57d6b50f1f8d47a2e8f473`.
- After-upload artifact **12470011711**, SHA-256 `8c4255c3fc1e0432d842574901ddd2acd5b2eb695ff73ee9e9807fd498296d54`.
- Final artifact **12469414355**, SHA-256 `a17e5274c9a83ecc83796b5b573715b1e7700493f8f9e1fb041f9bf70f723fcb`.

All three artifact archives were independently downloaded and their SHA-256 values verified. Secrets and raw production proof seals are excluded from this report/evidence.
