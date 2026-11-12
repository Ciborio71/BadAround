# Wizard presentation — 12 November 2026

Scope: six theme runtime files; no schema, database, canonical consent, publication, map-coordinate or moderation change.

- Red report CTA keeps white text across anchor interaction states.
- Address search has the shared address/municipality/neighborhood placeholder and one focus treatment. Remove duplicated privacy and keyboard tutorial copy, retaining useful loading/error statuses and map keyboard accessibility instructions.
- Verified location card uses a transparent background and “La tua posizione è:”; neighborhood proposal is in the right-hand precision column and stacks on mobile.
- Continue and final submit use blue/white when enabled.
- Contextual found-subject labels appear for observed vehicles, found/sighted animals and objects; incompatible former choices are cleared without changing canonical enum values.
- Anonymous presentation explicitly explains private names; required identity fields remain required.
- Community updates and social visibility each have an introductory panel and concise explanations for every choice. Neither is selected by default. Moderation and social publication limitations remain explicit.
- Repeated wizard emergency banners removed; danger question and event-detail emergency information retained.
- Centered progress panel while submitting or checking access; hidden when email code entry or other interactive authentication is needed. Existing duplicate-submission guard retained.

Validation: PHP lint, JS syntax, native wizard and smart-address regressions, geo projection/area contracts, anonymous/contextual-choice/pending-submission tests and existing OTP cancellation/confirmation tests. Controlled Chromium fixtures at 1440, 1024, 768, 390 and 320px check overflow, focus, column order, button colors and centered progress. Fixtures do not certify the real Google shadow-DOM appearance or actual production email delivery.

Deployment uses source comparison against afd4bee37e68fdd71fa10224ab29546057e1dd67, backups outside the web root, verified TLS, atomic file replacement, SHA readback and rollback on error. No maintenance exceptions or IPs changed.

## Outcome

- Runtime commit: 26b13dfef6f1663d3ea41a745355530d396aed9f.
- Guarded release: https://github.com/Ciborio71/BadAround/actions/runs/43233054212 — PASS. Six runtime files backed up, installed and SHA verified.
- Targeted suite: 107/107 PASS; PHP geo contracts and three release/rollback tests PASS.
- Chromium: all five widths PASS, no horizontal overflow, expected focus/CTA/continue colors and centered spinner.
- Full Identity functional and MySQL CI for identical runtime bytes: https://github.com/Ciborio71/BadAround/actions/runs/43232397007 — PASS. Follow-up commits adjust test expectations and fixture origin only.
- Independent production readback confirmed address markup, CTA states and new submission/anonymous UI source. Actual external browser remains limited by maintenance IP access; no Google-provider live visual or real email claim.
