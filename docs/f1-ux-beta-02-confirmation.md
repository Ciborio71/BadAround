# F1-UX-BETA-02 — Category explanations and one explicit final confirmation

Baseline: verified active staging wizard and CSS match deployed 8f30a5716a7a7e691901f3c600b77d7fcc5c90ff. Current files inspected before changes; no historical restore.

## Product change

All six event cards now explain their scope. Case e attività explicitly includes furti, tentati furti and danneggiamenti in homes, shops and offices. Existing category values, icons, selected style and collapse behavior remain unchanged.

Following the explicit product request, one unchecked-by-default visible confirmation replaces the four visible required controls. A bold heading confirms good faith; its visible detail explicitly states content rights, acceptance of publication rules and terms, and acknowledgement of having read the privacy notice. Moderation/privacy explanation remains immediately below and is associated with the checkbox. Existing privacy-policy link stays available.

This supersedes BETA-01's conservative presentation choice of independent required checkboxes. It does not claim legal certification. No optional consent or contact preference is included; privacy is worded as acknowledgement of notice, not blanket permission for processing. No waiver of responsibility or automatic publication is introduced.

The five existing canonical booleans remain separate, with unchanged backend validation and storage. Explicit check sets all five; uncheck revokes all five. Partial states render unchecked. Hidden originals are disabled and not HTML-required; custom validation errors focus the visible control. Review shows one confirmation row.

Runtime files: assets/js/native-report/wizard.js and assets/css/native-report.css under wordpress/themes/badaround-child. Tests: tests/f1-ux-beta-01.cjs, tests/f1-ux-beta-01-browser.cjs, tests/nf-final-01-ux.cjs and tests/nf-final-01-browser.cjs. Existing focus/keyboard checks now target the single visible control instead of its hidden canonical privacy input.

## QA boundaries

Focused DOM checks cover explicit copy, one usable checkbox, all five canonical values, revocation, partial state, optional contact independence, hidden required controls and category description.

Source-backed Chromium exercises six full paths at 390/1440px: vehicle + 112 + anonymous, vehicle + pseudonym, property theft with conditionals; one visible checkbox and all five true values in the intercepted submission. Mobile and desktop screenshots inspected. Google and submission are mocked in these browser tests. No new real report or database mutation is necessary for this presentation-only patch.

The staging workflow gates deployment on full Native/Smart Address/media DOM regression, PHP/JS syntax, backend/privacy/territory suites, four browser suites and frozen-file guards. Only the two runtime files are uploaded; rollback is taken from verified 8f30a57. Production, F1.2, Smart Address, media backend, publication and SEO remain unchanged.
