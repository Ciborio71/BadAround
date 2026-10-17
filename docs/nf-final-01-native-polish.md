# NF-FINAL-01 — Native wizard UI/UX consolidation

Baseline: `9a94937` (`feature/release-qa-02-native-completion`). Dedicated branch: `feature/nf-final-01-native-polish`. The owner's certified Smart Address → territory → Native persistence → pending moderation path is accepted as the MVP baseline; earlier QA documents' requests for further real submissions are superseded for this task.

No deploy, merge, database mutation, real submission, plugin removal, media enablement, or hosting change.

## Seven-step review

| Step | Review and result |
| --- | --- |
| 1 — Cosa è successo | Added concise introductory guidance. Category/subtype conditional disclosure retained; required state, validation summary and keyboard focus checked. |
| 2 — Dove | One visible address search. Explicit presentation flag prevents the old manual fallback revealing technical fields on provider failure or reset. Search widget receives an accessible name and status description. Country/territory/coordinates/identifiers remain internal. Reconciliation, address selection, persistence and provider request logic unchanged. |
| 3 — Quando | Added guidance for approximate/unknown time; existing timezone and conditional date/time behavior retained. |
| 4 — Dettagli | Added factual-description guidance; private cards retained. Corrected choice-card CSS specificity and checkbox alignment. Optional/required status consistently displayed. |
| 5 — Immagini | Existing availability, controlled unavailability, disabled picker, retry/error DOM coverage retained. No media endpoint, feature flag or hosting limit changed. No live upload attempted. |
| 6 — Contatto e privacy | Required indication added to confirmation labels and array legends. Checkbox text grouped for narrow screens. Removed duplicated optional wording from phone. Existing private-contact and separate public-identity guidance retained. |
| 7 — Riepilogo e invio | Cards follow wizard order. Technical geographic values excluded from visible review, while private address retains its explicit privacy note. Payload unchanged. Existing frozen retry snapshot, loading, duplicate guards and success behavior retained. |

Common review: existing titles, spacing, typography, cards, helper/error text, disabled/loading states, Continue/Back/Submit semantics, mobile sticky actions, keyboard focus and step scroll behavior inspected. Added live “Passaggio X di 7” status, explicit box sizing and wrapped controls. Corrected stale errors on change-only assistive/select interactions. Hidden geographic errors now link back to the visible search rather than inaccessible controls. Removed the last boot-error instruction to return to a legacy form. Primary CTA uses #087f8c with white text (approximately 4.7:1).

## Legacy design classification

Inspected historical `assets/css/wpforms.css` and pre-retirement `report.css` at `c32e1b2^`, as well as the current Native skin.

| Class | Rules | Outcome |
| --- | --- | --- |
| REUSE | Report workspace shell, navy/teal palette, typography, rounded cards, spacing, mobile action area | Retained existing neutral `report.css` / Native rules. |
| MIGRATE | Choice-card padding/alignment, 48–52px controls, focus feedback, responsive field wrapping, privacy-sensitive cards | Semantic Native selectors; privacy cards were already migrated by LEG-RETIRE-01 and are preserved. This task fixes choice specificity, control sizing/wrapping and CTA contrast. |
| DELETE | WPForms page/field-number selectors, five-step assumptions, builder indicator suppression, save/resume UI, blanket `!important` skin | Already retired in baseline; no resurrection or WPForms selector added. No plugin uninstalled. |

## WPForms references and live read-only inventory

WPForms is not a Native reporting engine or fallback. References still remain:

- `inc/native-report.php`: reads the existing Google Places browser key from `wpforms_settings`.
- `functions.php`: maps reuse that same configuration option.
- Historical source/persistence compatibility in core: report intake, report repository, persistence service, installer default, event type resolver, audit log, media repository and moderation admin. These do not add a WPForms Native form dependency.
- Comments/README mention the legacy engine; core README's form-ID description is historical.

Authenticated staging reads on 2026-10-17: installed plugins include WPForms and its geolocation/offline/post-submission/save-resume add-ons. Saved forms are #6 “Segnala un evento” and #255 “Modulo vuoto”. No current post content embeds found for `[wpforms` or `wpforms/form-selector`; no matching embeds in post metadata or widget options. Repository theme has no WPForms rendering calls. No other active form placement was found within these checks. This does not assert absence in arbitrary third-party runtime code. Plugin removal and key configuration migration are outside scope.

## Verification

- Native completion JS/DOM gate: 130/130 tests, including Native F1.6/F1.9, Smart Address SF2/SF4/SF6/SF7/UX01/SF10, national SF8/SF8B, SF9 privacy and existing media DOM/error/retry tests, plus NF-FINAL tests.
- PHP: F1.3, F1.4, F1.5, canonical territory, publication contract, consumers and geometry suites pass; 75 plugin/theme PHP files lint clean. Seven runtime JS modules pass syntax checks.
- Corrected an existing synthetic F1.6 `get_terms` stub to return no terms for the unrelated territorial query, preventing warning text corrupting JSON test output. No application validation weakened.
- Local Chromium: all seven steps at 320, 390, 768 and 1440px; no horizontal overflow; visible navigation targets at least 44px; error summary/field links, tab order, Space checkbox behavior and heading focus pass. Screenshots visually inspected for mobile/desktop. These use actual PHP-rendered Native markup and source assets, with all network intercepted; no WordPress writes or real Google requests.
- Smart Address resolver/loader prefix and selection/reconciliation pipeline unchanged. Changes in its module are presentation-only fallback gating and accessible widget attributes. Prior live Google certification remains the accepted baseline; no new live Places or touch-device provider certification claimed.
- Existing historical F1/F1.2 harnesses referencing deleted WPForms mapper, retired fallback assertions and old exact-byte freeze tests are superseded by the current Native candidate gate, as documented in RELEASE-QA-01C. Historical global PHP Syntax workflow is not claimed green.
- New dedicated CI workflow repeats the current gate plus local browser smoke and guards all core plugin files and frozen fixtures from modification. Local validation is reported separately from any remote CI result.

## Required output

STEPS REVIEWED: 1–7

FILES CHANGED:

- `.github/workflows/nf-final-01-native-polish.yml`
- `docs/nf-final-01-native-polish.md`
- `tests/native-report-f16.php`
- `tests/nf-final-01-ux.cjs`
- `tests/nf-final-01-browser.cjs`
- `wordpress/themes/badaround-child/assets/css/native-report.css`
- `wordpress/themes/badaround-child/assets/js/native-report/errors.js`
- `wordpress/themes/badaround-child/assets/js/native-report/smart-address.js`
- `wordpress/themes/badaround-child/assets/js/native-report/wizard.js`
- `wordpress/themes/badaround-child/inc/native-report.php`
- `wordpress/themes/badaround-child/template-parts/native-report/shell.php`
- `wordpress/themes/badaround-child/template-parts/native-report/step.php`

REUSABLE WPFORMS DESIGN MIGRATED: semantic choice-card treatment, responsive field/control sizing, accessible focus/CTA treatment; existing private cards and neutral workspace preserved.

WPFORMS RUNTIME REFERENCES REMAIN: Google key option reads and legacy persistence/media/moderation compatibility listed above; plugins and saved forms remain installed. No Native WPForms renderer or fallback.

MOBILE: PASS (local browser at listed widths)

KEYBOARD: PASS

VALIDATION UX: PASS

SMART ADDRESS REGRESSION: PASS (automated; certified live baseline preserved)

NATIVE REPORT REGRESSION: PASS

F1.2 MODIFIED: NO

MEDIA ENABLED: NO

DEPLOY: NO

PRODUCTION: UNCHANGED

FINAL VERDICT: **NF-FINAL-01 COMPLETE — NATIVE WIZARD UI/UX CONSOLIDATED**
