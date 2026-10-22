# NF-FINAL-01 — Native v2 UX consolidation / pre-deploy report

Date: 23 October 2026 (Europe/Rome)  
Branch: `feature/nf-final-01-native-ux-consolidation`  
Candidate: `b4aca00fe7acea5b80aa8b45d423ab915fa071e3`  
Dedicated CI: [39867687289](https://github.com/Ciborio71/BadAround/actions/runs/39867687289)  
Staging deployment: **NOT PERFORMED**. Separate explicit authorization is required.

## Immutable baseline and scope

Home baseline: `a4ef16aa067afc94a96cacd55a46a62d1248ed93`. Native v2 baseline: `d0ac50bf4866b326943f3f4487b49fbe3d878d7b`. Core NF-UX-03A remains certified.

The actual certified wizard has **six navigation screens** (Evento, Dove e quando, Dettagli, Immagini, Contatti, Riepilogo). Time is already merged into location, and the five declarations are in Contatti. The historical seven-step reference does not describe the deployed navigation. This task preserves the exact six-screen config/order rather than inventing a seventh screen.

Runtime files changed, and no others:

1. `wordpress/themes/badaround-child/assets/css/native-report.css`
2. `wordpress/themes/badaround-child/assets/js/native-report/errors.js`
3. `wordpress/themes/badaround-child/assets/js/native-report/location-map.js`
4. `wordpress/themes/badaround-child/assets/js/native-report/smart-address.js`
5. `wordpress/themes/badaround-child/assets/js/native-report/wizard.js`
6. `wordpress/themes/badaround-child/template-parts/native-report/step.php`

Core, validators, normalizers, persistence, Native model/API/media modules, Native config PHP, contract, taxonomy, Home, map engine and public-location rules are byte-unchanged against the certified baseline. The CI enforces the exact six-path runtime diff.

Frozen contract SHA-256:
`dc282153243ffb80f3bd4f2a222ea0f4c4f42806fba6f3a0bde0a76855bd8e3c`.

## What changed

- Home category copy and approved icon geometry/colors are reused. Expanded category cards are smaller; the selected answer remains compact and editable.
- A meaningful choice remains visibly selected for 140ms before progression. Radio arrows keep alternatives open. Long native selects can focus an active dependent specification. Free text and clearing a select never auto-advance.
- Short answers use compact cards. Long subtype labels use one column at 320px; long contact/Social answers use one column on phones. Contact email/phone remain paired at 768px and above, stacked below.
- Numbered progress, completion ticks, “Passaggio N di 6”, current/completed/remaining accessible labels, smaller spacing and coherent 48px CTAs.
- Existing field order is retained. Topic headings clarify active vehicle/property/animal/object/damage/authority/reward/identity/Social sections and disappear when their fields are hidden.
- No anonymous/reward default is introduced. Existing explicit choices, pseudonym rules and reward conditions remain unchanged.
- One safe-header/CTA-aware focus policy handles progression and errors. Emergency notice anchoring remains visible during auto-focus.
- After successful Google/GPS confirmation, scroll preserves the verified-address confirmation and focuses the next time question. Request-ticket deduplication avoids repeated animations; moving focus elsewhere during the request prevents focus theft. Coordinate/territory resolution and denied-permission preservation are unchanged.
- A single local error focuses its actual control/file/declaration checkbox without a duplicate generic summary. Multiple/global errors retain their summary. Field-level message associations and backend error codes remain intact.
- Review keys, values and grouping are identical to baseline. Redundant non-privacy input hints are omitted from review; private-address, identity/contact and Social guidance remain. Declaration review rows are visually separate.
- Essential input/radio boundaries meet 3:1 contrast; text-input keyboard outline is explicitly 3px and at least 3:1; CTA/privacy-link text meets 4.5:1. Emergency contrast remains certified.

## Evidence model

The deployed baseline was inspected in the authorized real cloud browser through category/subtype, Google suggestion confirmation in Pomezia, time, details, no-media and identity/contact. The page was reloaded afterward; no consent was accepted and no submission was made there.

Candidate QA runs in isolated Chromium against the actual PHP Native/header/footer templates, captured certified config/context and the exact deployed Astra CSS/JS obtained through **read-only strict FTPS**. Every browser request is intercepted. Google/GPS provider responses are isolated test fixtures for deterministic interaction tests; no real positive upload or staging report creation occurs.

This is a **pre-deploy** candidate certification, not a claim that the candidate already runs on staging. Read-only staging custody before and after QA proves the previous certified runtime remains in place. No probe or temporary remote component was installed.

## UX consolidation

| Gate | Result |
|---|---|
| Category/subtype | PASS |
| Selected state / change answer | PASS |
| Choice density | PASS |
| Auto-next / Altro not skipped | PASS |
| Scroll | PASS |
| Focus / sticky header | PASS |
| Stepper | PASS |
| Details/reward grouping | PASS |
| Contact layout | PASS |
| Error UX | PASS |
| Summary | PASS |
| CTA | PASS |

## Responsive and accessibility

| Width | Result |
|---|---|
| 320 | PASS |
| 390 | PASS |
| 768 | PASS |
| 1024 | PASS |
| 1440 | PASS |

Candidate full-page tests use a 1000px viewport height; the independent Google/GPS interaction tests use 850px. Overflow: **NONE**. Active controls, dependent fields and CTAs fit; keyboard/focus, back/forward, refresh and intercepted v2 submission are covered.

Keyboard, focus, labels, required state, error association and targeted contrast: **PASS**. Hidden fields are excluded from keyboard flow. Eight specification fields are exercised in Chromium with text entry and Tab exit. Emergency notice remains one semantic live region, with no focus trap.

## Semantic regression

| Gate | Result |
|---|---|
| 33 subtype oracles | PASS |
| 2,805 field/subtype pairs / PHP differential | PASS |
| 554 dependency activations | PASS |
| Damage: 2 required / 14 optional / 17 hidden | PASS |
| Altro 8/8: activation, limits, Unicode/plain text, keys, stale, summary/payload | PASS |
| Emergency: exact 9 + yes/unsure + dual/stale + no false positives | PASS |
| Stale policy | PASS |
| V2 payload | PASS |
| Review semantic keys | PASS |

All 33 candidate payloads and review-key lists are compared directly with the certified baseline, normalizing only the generated submission UUID. The model/config and backend remain unchanged. All 33 isolated Native v2 REST/Golden Path service scenarios verify canonical persistence, pending status and idempotent retry.

Historical media DOM tests formerly required focus on the generic error summary. Their focus-only expectations now assert the intentionally requested direct field/file focus. The no-media differential still compares every request, header, normalized payload, state, retry, snapshot, error and upload count exactly; all other flow focus remains identical to baseline. No semantic assertion was removed.

## Platform regression and custody

| Gate | Result |
|---|---|
| Backend v1 | PASS |
| Backend / Native v2 | PASS |
| C.1 immutability | PASS |
| C.2 completion recovery | PASS |
| GEO / denial preservation / public-location request | PASS |
| Social preference serialization | PASS |
| Five consents | PASS |
| No-media | PASS |
| Media fail-closed and CI binding compatibility | PASS |
| DB/schema | UNCHANGED |
| Canonical taxonomy | UNCHANGED |
| Protected fixtures #35–#71, including #57 | UNCHANGED by this task |

C.1/C.2 use quick existing deterministic fixtures; the broad live resilience track was not reopened. No remote DB writes, moderation, publication or fixture mutations were issued. Real new reports/events: **0**. Browser submissions were intercepted in memory. Remote temporary components: **none**.

Staging custody: 33 Core/backend/Native/contract files, 15 rendering dependencies and four Home/map paths match the certified deployed baseline before and after the candidate tests.

## Performance

New heavy runtime dependencies: **NO**. No framework, icon bundle or asset was added. No additional Google Maps bootstrap/load was introduced; provider-load regression remains covered. Observed functional/rendering regression: **NONE** in controlled QA. No live post-deploy LCP claim is made because deployment has not been authorized.

## CI and screenshots

Final dedicated run: **SUCCESS**. Both jobs passed; **204/204 semantic tests**, zero failures. The full-template browser sweep covered **60 states** at five widths, in addition to eight specifications, emergency and GEO provider-boundary regression.  
Semantic artifact: [12464932655](https://github.com/Ciborio71/BadAround/actions/runs/39867687289/artifacts/12464932655).  
Browser artifact: [12465430748](https://github.com/Ciborio71/BadAround/actions/runs/39867687289/artifacts/12465430748).

The separate general “PHP Syntax” workflow retains the pre-existing legacy `report.js` failure at “Test recovery golden-state invariants”. It was not fixed in this task; candidate PHP lint and the dedicated gates are independent.

The CI GPS stress harness intentionally inserts a 1100px spacer and a fixed header to exercise scrolling. Its full-page stress screenshot is retained only in the artifact. The linked 390px GPS visual below comes from an additional local Chromium execution of the **same final candidate**, full Native/header/footer templates and verified Astra assets, without those artificial additions; Google/GPS responses remain mocked. This supplemental run passed the same location/denial/drag assertions. Its [source](nf-final-01-evidence/location-full-template.cjs) and log are preserved with the evidence.

Selected evidence (fresh final candidate):
- [320 category/subtype](nf-final-01-evidence/320-category-subtype.png)
- [320 complex details](nf-final-01-evidence/320-details.png)
- [320 summary](nf-final-01-evidence/320-summary.png)
- [320 keyboard/error](nf-final-01-evidence/320-keyboard-error.png)
- [390 location/GPS](nf-final-01-evidence/390-location-gps.png)
- [390 contact](nf-final-01-evidence/390-contact.png)
- [390 emergency/Altro](nf-final-01-evidence/390-emergency-altro.png)
- [1440 category](nf-final-01-evidence/1440-category.png)
- [1440 details](nf-final-01-evidence/1440-details.png)
- [1440 contact](nf-final-01-evidence/1440-contact.png)
- [1440 summary](nf-final-01-evidence/1440-summary.png)

## Pre-deploy verdict

**NF-FINAL-01 READY FOR STAGING DEPLOY — NATIVE V2 UX CONSOLIDATION PASSED CI**

No deployment. No merge. No production. No automatic final Golden Path.
