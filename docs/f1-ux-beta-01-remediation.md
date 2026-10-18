# F1-UX-BETA-01 — cognitive load and flow remediation

## Baseline and scope

Baseline is the active staging theme read on 2026-10-18, matched against the current checkout, not a historical restore. Eight active theme sources matched; active schema and repository excerpts confirmed optional authority reference, existing contact codes, and separate required consent fields. Runtime patch touches only five theme files. No model, API, Smart Address, F1.2, F1.7, Publication, SEO/permalink or certified fixture changes.

## Result

- All selected category cards use neutral aqua/teal state and check; category pin colors and home geometry are retained.
- Timeline labels: Evento, Dove, Quando, Dettagli, Media, Contatti, Riepilogo. No numbered circles. Current/next section is stated in live progress text.
- Visible applicable questions are numbered dynamically in DOM order across the path. Conditional questions add/remove numbers without gaps. Required unanswered questions defer subsequent groups; related short field pairs stay together. Hidden canonical controls are disabled and not HTML-required, except current hidden territory controls remain writable by the certified Smart Address adapter and are never HTML-required. Canonical model validation remains authoritative.
- Contact section is “Informazioni di contatto”; identity choice comes first, anonymous first/default, then conditional pseudonym and private names/email/optional phone. The moderation/policy qualifier is retained.
- Community information relayed through BadAround and communications from BadAround operators have separate explanatory blocks. The existing three contact-preference codes remain mutually exclusive; no new independent notification permissions or private messaging feature is invented.
- “Prima di inviare” visually groups the final confirmations. Only good faith/truthfulness and rights to supplied content share a UI checkbox; the two canonical booleans are still separately emitted. Publication rules, terms and privacy acknowledgement retain separate unchecked controls and separate storage/validation. The UI does not merge optional communication preferences with these.
- Authority reference is removed from config, SSR, labels and unused CSS. No historical data is removed.
- One authority/service question maps to existing status/type fields. “Numero Unico di Emergenza 112” maps to status=yes/type=public_service. This is the existing broad public-service classification, not a new canonical 112 discriminator. It never implies police/carabinieri and never requires a second force. Existing service and no/not-yet/prefer-not values remain representable.
- Reward wording and purpose are explicit; conditional amount/terms/expiry/confirmation remain. Reward is an optional feature (none default) with canonical conditional rules and downstream amount/expiry storage, so removal requires a separate product decision.
- Exact address/civic privacy copy is visible beside the search before entry. Intro uses “non saranno pubblicati automaticamente”. Repeated generic per-field warnings are consolidated; specific address, plate, identity, contact and moderation help remains.

## Authority-reference dependency audit

`authority.reference` is an optional private F1.2 string (max 191), active only when authority.status=yes. Its only dedicated core reference is its schema definition. The repository generically retains supplied canonical content in the private JSON envelope. No dedicated moderation, notification, publication or other downstream logic requires it. UI omission is valid under the frozen schema; historical storage and fixtures remain unchanged.

## Consent boundaries

This is a conservative presentation grouping, not a legal certification. Good faith and rights to content are declarations. Acceptance of publication rules and terms, and privacy acknowledgement remain independent required fields and controls. Contact preference remains a distinct three-valued choice. No consent is automatically granted by choosing anonymity or by the grouped declaration.

## Validation

- 154 JS/DOM tests: Native, conditional flow, hidden required controls, consent independence, retry/idempotency, privacy, Smart Address and media regressions.
- PHP syntax and SSR schema/config assertions, seven existing backend/territory/publication/privacy suites and JS syntax.
- Seven-step Chromium smoke at 320/390/768/1440px; home/form computed icon parity and private-map clone regressions.
- Dedicated browser paths at mobile 390 and desktop 1440: vehicle + 112 + anonymous, vehicle + pseudonym, property theft + conditional fields; no media; keyboard activation, sequential numbering, overflow checks and synthetic 201. Desktop/mobile screenshots manually inspected from the source-backed fixture.
- Staging HTTP intake QA and deployed active-source comparison are recorded separately after deployment. A mocked browser 201 is not evidence of a real database event.

## Files

Runtime:
- wordpress/themes/badaround-child/inc/native-report.php
- wordpress/themes/badaround-child/template-parts/native-report/shell.php
- wordpress/themes/badaround-child/template-parts/native-report/step.php
- wordpress/themes/badaround-child/assets/js/native-report/wizard.js
- wordpress/themes/badaround-child/assets/css/native-report.css

Tests:
- tests/native-report-f16.php
- tests/native-report-f16-dom.cjs
- tests/nf-final-01-ux.cjs
- tests/nf-ux-03-compact.cjs
- tests/nf-visual-02-cards.cjs
- tests/f1-ux-beta-01.cjs
- tests/f1-ux-beta-01-browser.cjs

Existing expectations updated only for the requested presentation removal, contact title, named progress and deferral of controls outside their visible section. Canonical backend fixtures remain untouched. Deployment workflow and this report are the remaining support files.
