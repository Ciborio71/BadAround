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

## Follow-up: location and public signature

12 November 2026: keep verified address beside its label, wrapping only when needed; internal neighborhood legend and rounded action buttons. Put required private first/last names before the native public-signature select. Remove its card/Modify presentation and duplicated helper; show a safe text-only preview for anonymous, nickname, first name or first name plus surname initial. Moderation reminder is at the end of the review step. No public-identity enum or backend disclosure rules changed.

The wizard marker cloned SVG gradient IDs from the category step, which is hidden when the location step is active. Each cloned marker now receives unique definition IDs and rewritten local references, with upright explicit 44×55 dimensions. This avoids resolving the marker gradient to a hidden original. The browser fixture mounts the real LocationMap adapter with Google Map/Marker test doubles and checks complete SVG, unique local gradient resolution and layout; this is not a live Google-provider screenshot certification.

Runtime: e55e1db672bab9a709fdbf564c905f63b25f699c (same runtime as cea67c086600d7f157259b5200a15da8db7a123a; follow-up aligns sentence-case assertion).
Targeted release: https://github.com/Ciborio71/BadAround/actions/runs/43240593189 .
Identity functional and MySQL: https://github.com/Ciborio71/BadAround/actions/runs/43240262299 — PASS.

Follow-up outcome: PASS — 107 targeted tests, five Chromium viewport checks, independent marker gradient reference check, guarded four-file installation and SHA readback. Production source independently confirmed for marker, private-name ordering and location/zone styling.
