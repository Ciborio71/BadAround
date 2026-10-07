# F1.6 — Native Report Form / Wizard Foundation

Verdict: **F1.6 COMPLETE — FORM REVIEW REQUIRED BEFORE FREEZE**.

Implementation and automated verification are complete. Staging browser QA and one real synthetic submission have **not** been executed; this verdict does not certify those gates or authorize cut-over.

## Baseline and scope

- Repository: Ciborio71/BadAround.
- Parent release: `release/mvp-reconciled`, `aa40f45721586f55f9a186a1803eb32573496635`.
- Feature: `feature/native-report-form-f16`, created from that exact release.
- Observed deployment: run `37593444226`, success at the same SHA.
- Live Core `0.17.0`, database schema `1.8.0`, active child theme and WPForms #6 confirmed by read-only precheck.
- F1.1–F1.5 plugin files, taxonomy identities, persistence, moderation and legacy assets are unchanged.

The native page is a client of the frozen contract and endpoint. The ordinary reporting URL continues to render the existing legacy page. New CSS is scoped to `.ba-native`; no production frontend dependencies or global styling changes are introduced.

## Components and behavior

- `inc/native-report.php`: staging/admin gate, read-only taxonomy labels, safe schema-derived config, field renderer and presentation labels.
- `template-parts/native-report/shell.php` and `step.php`: actual server-rendered seven-step markup, grouped controls, labels, help and error descriptions, final review, success message and no-JS consultation notice.
- `assets/js/native-report/model.js`: in-memory state, crypto UUID, conditional engine, effective payload and UX validation.
- `api.js`: JSON POST to the F1.5 route, protocol marker sourced from its controller, `mode: same-origin`, anonymous credentials, timeout and structured responses.
- `errors.js`: Italian messages, field errors, accessible summary and field navigation without raw debug text.
- `wizard.js`: disclosure, forward/back, focus, final explicit submit, double-click guard, retry and distinct-report reset.

Steps: Cosa è successo → Dove → Quando → Dettagli → Immagini → Contatto e privacy → Riepilogo e invio.

Enums, constraints and conditional rules are projected from `BadAround_Report_Schema`. Category/subtype membership comes from that schema; Italian taxonomy labels are read through the existing F1.1 mapper. No taxonomy mutation occurs during rendering. Missing taxonomy fails closed with a link back to the reference form.

Hidden values remain in memory for backtracking but are excluded from the payload. Conditions also require their controlling fields to be applicable, preventing stale plate knowledge or exact-time knowledge from activating hidden descendants. Category changes invalidate incompatible subtype values in the effective payload.

UUID is created once per compilation, stays unchanged on navigation, validation corrections and retry, and changes only on explicit new report after success. After a request with uncertain outcome, data and navigation are locked and the exact payload snapshot is retried. A known server validation rejection permits corrections with the same UUID. A completed duplicate response is success; a payload conflict is not success and never silently generates a new UUID.

Location order is Regione → Provincia → Comune → Località/Frazione/Quartiere, followed by area/address and optional coordinate pair. Address/civico, coordinates, full plate and contacts have explicit private-use copy. Identity display choices remain the existing canonical choices. No Google Places or geolocation integration is added in this foundation; `place_id` remains a deferred external reference, not a new identity.

No binary upload or simulated file selector is present. Only the optional existing `media.availability` field is rendered. `media.items` is deferred to the native media task. Refresh loses the in-memory compilation; no storage, Save & Resume or offline persistence is implemented.

Consent presentation is versioned `native-f16-v1`. It is a QA presentation identifier, not a claim that final public legal policies have been approved. The existing privacy-policy URL is linked when configured. Final policy/terms wording and destinations remain part of review before public cut-over.

## QA exposure and rollback

After a **separate reviewed release integration and staging deployment**, use the existing page while authenticated as an administrator:

`https://staging.badaround.it/segnala-un-evento/?native_report=1`

The gate requires the exact staging host, `manage_options`, the exact query value and the native backend classes. Anonymous visitors and other hosts retain the reference form even with the query. QA responses send no-cache headers and noindex/nofollow/noarchive. Removing the query returns to the unchanged reference form. QA is not a public feature flag or final cut-over.

The intake request deliberately omits session credentials so that the public anonymous F1.5 path is exercised even when viewing the QA page as an administrator. Origin and Fetch Metadata headers are browser-generated.

## Verification performed

- PHP syntax: 81 repository/test files, PASS.
- F1.6 PHP/config/SSR: 362 assertions, PASS.
- JavaScript model/API and DOM: 18 tests, PASS.
- F1.1, F1.2, F1.3, F1.4 and F1.5 regression suites: PASS.
- Existing Golden State invariants, D1–D3 and E1–E5 CI steps: PASS.
- Payloads built by the actual frontend model for all six categories accepted by the frozen F1.3 raw-contract and normalized validators: PASS.
- Diff whitespace check: PASS.

DOM tests load actual PHP-rendered markup and production JS using jsdom. They verify boot, category/subtype filtering, navigation/backtracking, state retention, conditional disclosure, stale value exclusion, UUID stability, identical retry payload, structured errors, successful and duplicate-completed states, double-submit guard, labels/descriptions/focus, escaped review text and no request before explicit final submit. API responses in these tests are mocked.

Run with Node 24 and PHP (with mbstring for the existing E2 suite):

```sh
php tests/native-report-f16.php
npm ci --prefix tests --ignore-scripts --no-audit --no-fund
npm test --prefix tests
```

`BA_PHP` can select a local PHP binary for the JS tests. `php tests/native-report-f16.php --html` renders a test fixture and `--config` emits schema-derived test config. Test fixtures use synthetic taxonomy objects, not live terms. Neither command creates live reports/events.

## Required output / readiness

| Area | Check | Result |
|---|---|---|
| Foundation | Native shell, wizard state, navigation, disclosure, conditional engine | PASS (SSR/DOM) |
| Contract | Schema | `badaround-report/v1` |
| Contract | Canonical keys and category/subtype values | PASS |
| Contract | WPForms field IDs used / native runtime dependency | NO / NO |
| API | F1.5 endpoint, protocol marker, stable UUID, retry same ID, structured errors | PASS (automated client checks; mocked transport) |
| UX | Mobile foundation | PASS (scoped responsive implementation); visual browser check pending |
| UX | Keyboard accessibility / error focus | PASS (semantic controls and DOM focus); manual keyboard check pending |
| UX | Success state / no auto-publish copy confusion | PASS (DOM) |
| Privacy | Private field UX, full plate, precise location and contacts | PASS (markup/config review) |
| Test | Automated tests | PASS |
| Test | Browser QA on staging | NOT RUN / readiness FAIL |
| Test | Native synthetic submission against live endpoint | NOT RUN / readiness FAIL |
| Test | Live report/event deltas, event status and duplicate objects | NOT MEASURED; no live writes executed |

The local real-Chrome launcher was attempted but the environment denies Chrome's Unix socket creation (`Operation not permitted`). jsdom results do not substitute for desktop/mobile/keyboard browser QA. No live synthetic POST was made through an emulated browser or raw HTTP workaround.

Pending after reviewed integration: desktop Chrome, mobile width and manual keyboard checks; one real same-origin synthetic native submission; verify one report + one event in `Da moderare`, zero duplicate objects, and completed-retry behavior with the same UUID. Do not publish the test event. Verify normal URL and anonymous query still use WPForms, and native QA assets do not require builder assets.

No release merge, deployment, WPForms deletion, historical submission changes or production cut-over was performed in F1.6.
