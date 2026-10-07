# F1.6A — PR #8 review and controlled QA candidate preparation

Source release: `aa40f45721586f55f9a186a1803eb32573496635` (`release/mvp-reconciled`).
Initial reviewed PR head: `a3cef1f55dd804ae035f1cfef239f68e753c5c0d`.
The final reviewed head includes the localized corrections documented below. The exact final head, two-parent QA candidate SHA and successful GitHub Actions run must be recorded in the delivery message, after CI finishes. This document alone does not attest remote CI completion.

## Scope reviewed

Every original PR file was inspected, together with its relevant frozen sources:

| File | Review |
|---|---|
| `.github/workflows/php-lint.yml` | F1.6 PHP/production JS tests; test-only Node dependencies; retained regressions; read-only Golden/Recovery checks |
| `docs/development/native-report-f16.md` | Accurate foundation scope, mocked transport limitations, pending browser/live gates and QA-only consent version |
| `tests/native-report-f16.php` | Real schema/config and SSR; expanded actual page/enqueue and request-host coverage |
| `tests/native-report-f16.cjs` | Actual production model/API; canonical payloads passed to frozen F1.3 validators |
| `tests/native-report-f16-dom.cjs` | Actual PHP markup/production JS in jsdom; navigation, focus, final submit, errors and retry |
| `tests/package.json` | Private development-only test package |
| `tests/package-lock.json` | Pinned jsdom dependency tree and registry integrity metadata; no frontend runtime package |
| `wordpress/themes/badaround-child/assets/css/native-report.css` | All selectors now rooted under `.ba-native`, including responsive rules |
| `wordpress/themes/badaround-child/assets/js/native-report/api.js` | F1.5 route, JSON protocol marker, same-origin, credentials omitted, structured responses |
| `wordpress/themes/badaround-child/assets/js/native-report/errors.js` | Text errors, semantic summary, field association and focus; no raw technical codes in UI |
| `wordpress/themes/badaround-child/assets/js/native-report/model.js` | Contract-derived conditions/enums/constraints; effective payload; stable in-memory UUID |
| `wordpress/themes/badaround-child/assets/js/native-report/wizard.js` | Seven steps; retained values; corrected rejection classification and snapshot retry accessibility |
| `wordpress/themes/badaround-child/functions.php` | Conditional native assets and unchanged legacy asset branch |
| `wordpress/themes/badaround-child/inc/native-report.php` | Read-only schema/taxonomy presentation; corrected request-host gate; native backend availability |
| `wordpress/themes/badaround-child/page-segnala-evento.php` | Ordinary URL/anonymous/non-staging keep actual WPForms output |
| `wordpress/themes/badaround-child/template-parts/native-report/shell.php` | Semantic SSR, seven headings, labels, errors, success and actionable unavailable-state fallback |
| `wordpress/themes/badaround-child/template-parts/native-report/step.php` | Private-data copy, truthful deferred media scope, contact/consent presentation |

Unrelated drift: NO. No plugin file, frozen test, taxonomy identity, legacy JS/CSS asset, routing or deployment workflow is changed. Lightweight schema-derived client checks support UX; frozen server validation, idempotency and persistence remain authoritative. No WPForms IDs, builder runtime or independent domain enum source is used by the native client.

## Findings and corrections

| Severity when found | Finding | Resolution |
|---|---|---|
| BLOCKER | Gate trusted configured `home_url` host without checking the actual request host; a staging configuration could expose QA on an alias | Require both configured staging host and actual staging `HTTP_HOST`; only the expected hostname or explicit HTTPS port 443 is accepted. No forwarded-host trust |
| BLOCKER | Every HTTP 422 cleared the submission snapshot, including non-validation F1.5 errors | Unlock only a recognized, non-retryable validation rejection for an applicable rendered field. Other errors preserve the UUID/snapshot; retryable errors stay on final review; non-retryable errors block resubmission |
| REQUIRED BEFORE FREEZE | Some CSS selectors were namespaced but not rooted under `.ba-native` | Prefix every native rule, including media-query selectors |
| REQUIRED BEFORE FREEZE | Missing taxonomy displayed fallback advice without an actionable return link | Add escaped normal-page link and SSR regression |

Both blocker regressions were reproduced before correction. Corrections affect F1.6 presentation/client code, tests and related CI/documentation only. The existing full Golden/Recovery invariant checks are reused in the non-deploy CI job; backend semantics remain untouched.

Open findings after these corrections: **BLOCKERS 0; REQUIRED BEFORE FREEZE 0; FOLLOW-UPS 1**.

The follow-up is final legal wording and policy/terms destinations before public cut-over. `native-f16-v1` is exclusively a QA presentation identifier. This review neither finalizes legal policies nor certifies them. Deferred binary media upload and Places/geolocation remain planned features, not defects in this foundation.

## Code-review outcomes

ARCHITECTURE, QA GATE, WPForms FALLBACK, ASSET ISOLATION, WIZARD STATE, PROGRESSIVE DISCLOSURE, IDEMPOTENCY CLIENT, API CLIENT, PRIVACY UX, ACCESSIBILITY CODE REVIEW, MEDIA SCOPE, NO CUT-OVER: **PASS** after correction.

The exact staging host, `manage_options`, string query `native_report=1` and frozen native backend classes are all required. Missing host, alias/production host, unexpected port, incorrect query values and anonymous users fail closed. Template/enqueue tests exercise the real functions rather than replicating their decisions. Normal URL still calls WPForms #6 through its existing configurable form-ID hook. No WPForms runtime is required by the native assets; independent plugin asset auto-enqueue behavior remains a browser-QA observation.

All seven steps preserve editable state on backtracking. Conditional parents must themselves be applicable; stale hidden descendants are excluded. Category changes invalidate incompatible subtype in the effective payload and DOM. UUID persists through navigation, correction and uncertain retry. Network/uncertain retries preserve byte-identical JSON, and only explicit new-report action after success creates a fresh UUID. Double submit is guarded; completed duplicates render success without exposing object IDs.

API uses `POST /wp-json/badaround/v1/reports`, `Content-Type: application/json` and `X-BadAround-Intake: badaround-report/v1`, sourced from F1.5 configuration. It does not supply Origin or Fetch Metadata, WPForms nonce or WPForms AJAX. Full plate, exact address/civic number, precise coordinates and contact data have private-use copy, with explicit separate public identity choices.

Labels, grouped fieldset/legend controls, real buttons, heading hierarchy, focusable error summary/headings, field error descriptions and `aria-invalid` are present. Errors have text and focus targets, not color alone. This is a code/DOM verdict; manual keyboard/screen-reader and desktop/mobile behavior remain unverified. Binary upload, file selectors and `media.items` are absent; availability is the existing optional canonical field.

## Verification and remaining QA

- F1.6 PHP/config/SSR: **393 assertions PASS**, including actual page and enqueue scenarios.
- Production JS/model/API/SSR DOM: **20 tests PASS**, including unknown/non-validation 422 and retryable 422 snapshot regressions.
- Local execution of every non-dependency-install CI run step: **PASS** for PHP lint, F1.1–F1.5, F1.6 PHP, complete existing Golden/Recovery checks, D1–D3 and E1–E5.
- Existing E3 CI fixture-version adaptation is retained; frozen E3 source is not edited.
- Diff whitespace and frozen plugin/legacy asset/deploy-workflow isolation: **PASS**.

Remote full CI must pass first on the updated PR head, then on the exact QA candidate. Candidate construction uses release as first parent, reviewed PR head as second parent, and the exact reviewed tree. PR #8 stays OPEN/DRAFT/NOT MERGED and the release branch stays at the source SHA.

No browser QA, live same-origin native POST, database observation, staging write, release promotion or deploy is performed in F1.6A. Mocked API/DOM tests do not prove the live endpoint. F1.6B must separately approve/deploy the exact candidate and verify desktop, mobile width, keyboard, normal/anonymous fallback, one report plus one event in `Da moderare`, zero duplicate objects and completed retry. No cut-over is authorized.
