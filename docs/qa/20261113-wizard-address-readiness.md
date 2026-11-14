# Wizard address search readiness — 2026-11-13

Production: https://badaround.it

## Change

Prepare the Places autocomplete when the location step becomes visible, instead of the first click. Do not start Google on the hidden initial step, request GPS, open a map, or focus the widget on entry. Keep a decorative search lens and matching padding in the server-rendered fallback. Hide that lens once the widget supplies its own icon. Reuse the widget on back navigation and fence stale asynchronous activation after a report reset.

Canonical territory validation, privacy modes and publication contracts are unchanged.

## Verification

- Release commit: 869e91bd54a6096e247d00e4b8bdacb5b93614f9 (implementation d6c295f12e2c3347e019d9877b0ea78dce168a35).
- Successful guarded release: https://github.com/Ciborio71/BadAround/actions/runs/43429444567
- 102 DOM/provider tests passed, including hidden-step no-load, step-entry preparation without focus, pending-click focus, hidden-step focus suppression, reset stale-request protection, and existing canonical/GPS guards.
- Chromium presentation and network choices passed at 1440, 1024, 768, 390 and 320 pixels.
- PHP publication, SEO, structured-data, social and private GEO regression checks passed.
- First attempt stopped before deployment due to an outdated Circle fixture lacking setOptions; the mock now implements the current styling API. No runtime map change was needed.
- Guarded FTPS backup, atomic replacement and hash readback passed for exactly smart-address.js, native-report.css and template-parts/native-report/step.php.
- WPVibe readback independently confirmed the production lens markup and visible-step preparation logic after release.

No live user report was submitted by these checks. Browser checks use the canonical fixture and simulated provider; production readback verifies the deployed sources, not a live Google search transaction.
