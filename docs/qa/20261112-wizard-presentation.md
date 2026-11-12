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

## Final reporter-preferred ordering

The reporter subsequently confirmed keeping the initial question before private names: “Come vuoi comparire online?” → dynamic privacy/signature preview → optional pseudonym → required first/last names. Native select and all four canonical identity modes retained. Private-name helper now refers to the choice above. Runtime 5e352127d72f2d37ed66bb83dc7d75dc2a6ee410. Guarded one-file release https://github.com/Ciborio71/BadAround/actions/runs/43245133473 PASS; 107 tests and five responsive widths PASS; SHA readback PASS. This ordering supersedes the prior names-first proposal.

## Final decision: remove neighborhood choice

The optional district/neighborhood confirmation box is disabled in the public wizard (`neighborhoodProposal=false`). No explicit neighborhood field or confirmation is required or submitted by this presentation. Address, exact/approximate public position and default 100m area stay unchanged. Existing moderated territorial data and legacy canonical support are preserved; no district-to-municipality name conversion or new automatic geographic enrichment is introduced. Future use in detail/SEO requires verified public geographic data, never an inferred one-to-one mapping from numbered municipalities.

Runtime 932c59af729c61dc220cf182327b4eb33fe03b9a; guarded one-file release https://github.com/Ciborio71/BadAround/actions/runs/43253967585 PASS. 109 tests and five viewport checks PASS, final source SHA and independent production configuration readback confirmed. This supersedes all prior requests to position/style the neighborhood choice box.

## Larger map pinpoint and shorter location step

Wizard-only map marker enlarged from 44×55 to 64×80 CSS pixels, retaining upright SVG, independent gradient IDs and original point/drag semantics. Runtime df5fd34241a2cd0b41004bae1cfc82570948ea35; guarded release https://github.com/Ciborio71/BadAround/actions/runs/43255063736 PASS.

Subsequent requested copy cleanup removes the successful-map status “Il segnaposto indica la posizione riservata…” and the extra date guidance “Indica anche quando è successo…”. Loading/errors and map keyboard help remain functional. Runtime 13e0ad961b624d9b95b80af7d17d80adc06711f9; guarded release https://github.com/Ciborio71/BadAround/actions/runs/43255221735 PASS. 76 targeted address/map/geo tests and five responsive viewport checks PASS; final SHA and production source readback confirmed. No map coordinates, privacy selection, dates or event data changed.

## Final identity presentation: always-visible choices

Latest approved heading: “Come vuoi comparire nella segnalazione?”. Restore the four radio cards before required private names; all options stay visible after selection with no Modify action. Preserve the dynamic text-only public-name/privacy preview, now separated by 18px top margin and 18px top padding. The hidden canonical select and identity enum remain unchanged. This supersedes the native dropdown presentation.

Runtime 6a7249f89b90a1bb27c2238555feb86d695ac9a9; guarded three-file release https://github.com/Ciborio71/BadAround/actions/runs/43256127368 PASS. 31 wizard/identity DOM tests, five responsive browser fixture widths and independent production source readback PASS. Full Identity and MySQL https://github.com/Ciborio71/BadAround/actions/runs/43256127049 PASS.

## Private email helper

Add the requested plain-text helper under Email: “La tua email serve solo per le comunicazioni da BadAround e non viene mai resa pubblica.” Existing field description/error IDs, required email validation, OTP and private storage are unchanged. Runtime 6c02621d42d10cee4e6f265358e028a38dbc34fc. One-file guarded release https://github.com/Ciborio71/BadAround/actions/runs/43263879917 PASS; 31 wizard/identity DOM tests, responsive fixture checks and source SHA/readback PASS.
