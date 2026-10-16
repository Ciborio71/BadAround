# RELEASE-QA-02 — Native Form completion gate

Date: 2026-10-17. **READ-ONLY staging + isolated tests. No fixture creation, moderation mutation, staging/production deployment or merging.**

## Verified operational baseline

- Real staging `/segnala-un-evento/` delivers all 7 wizard steps in canonical order: Cosa è successo, Dove, Quando, Dettagli, Immagini, Contatto e privacy, Riepilogo e invio.
- Generated field/config metadata: 68 fields, 8,025 canonical territory records (compact browser tuples).
- Last Native report #19 is validated and linked to event #278, still pending moderation. This is evidence of existing real submission/persistence, not a fresh post-release full end-to-end submission.
- Private geolocation/identity/public projection restrictions remain in application; no database field was altered.
- Images are a user-deferred hosting workstream. The media Step 5 UI and synthetic DOM tests run, but live provider upload limits, storage and reliable 5 × 5MiB transport remain **NOT CERTIFIED**.

## Newly executed completion regression

- [RELEASE-QA-02 Native Form Completion Gate](https://github.com/Ciborio71/BadAround/actions/runs/38980920708) PASS; 127/127 JavaScript tests passed and required PHP synthetic tests passed.
- Included: F1.6 JS and DOM, F1.9 UX/mobile/focus, SF8 national and SF8B locality, UX01 address behavior, SF10 registry, SF9 public privacy boundaries, F1.7D media DOM and extra media error/retry coverage.
- PHP lint of plugin/child theme and syntax of model, wizard, Smart Address, media, media-view, API and errors passed; PHP native F1.3/F1.4/F1.5, territorial, publication/discovery and geometry synthetic suites passed.
- Existing F1.9 assertion forbade **all** CSS `!important` within later styles, including the already deployed single privacy fence `.ba-native [data-native-location-fields][hidden]{display:none!important}`. Updated the test only: it now permits **exactly that one selector** and rejects other `!important` rules; no CSS changes were made.
- PHP synthetic F1.6 warns about undefined test-harness array keys during some DOM-related tests; suite exits successfully. Treat as maintenance debt, not an operational blocker absent reproducible live defect.
- Historical top-level PHP Syntax workflow remains red due to retired pre-Native `report.js`/old frozen assertions; release QA gate is separate and green.

## Exit criteria / remaining launch blockers

**Native Form functional code: candidate PASS** based on synthetic regression plus prior real staging report; not an unconditional launch certificate.
1. **Full post-SF8B real Google place→canonical locality ID 5 persisted**: still needs one controlled Golden Path verification, or an equivalent backend read-only event created after SF8B. Do not repeat submissions merely to reproduce old fixtures.
2. **Media hosting**: owner requested defer until hosting is selected; do not claim live 5×5 MiB image capacity.
3. **Browser/mobile assisted smoke**: 7-step keyboard/touch flow + Google Places selected locality, visible summaries, errors/retry; previous user confirmed Step2→Step3 Torvaianica, and synthetic tests cover remaining UI.
4. **Publication privacy and legal choices**: full plate, exact public pin and cross-posting not authorized; current masking/approximation and moderation remain in force.

## Priority choice

Finish user-facing Native Form first. Defer broader Home/Map/Sentinelle release verification until Native submission and hosting blockers have explicit owner decision. Preserve all existing staging reports and events; no auto-publishing.
