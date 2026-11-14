# Wizard explanatory text width — 11 November 2026

User request: use the available wizard width for explanatory text instead of wrapping early while leaving a large blank area on desktop.

Exactly three CSS declarations changed from max-width:72ch to max-width:none: active-step page introduction in report.css, step introduction and native help paragraphs in native-report.css. Selectors remain scoped to the report wizard; Home/header layouts, typography, colors, spacing, form values, validation, GEO, publication and Identity behavior are unchanged. Text wraps naturally within its containing block on smaller screens; no nowrap rule, forced line, font reduction or horizontal overflow is introduced. Existing stylesheet filemtime versions invalidate browser asset URLs.

Baseline: fee694e83042baf847799290cbc4c866938a6b4d.
Runtime: 888f717a2801f1f8a0e30bd2b9af20ad461d95b6.
Guarded deployment: https://github.com/Ciborio71/BadAround/actions/runs/42920583206

Verification: existing wizard/Smart Address/GEO/UX tests — 99 JavaScript tests passed, 0 failed; PHP GEO suites passed; release rollback tests passed. Both CSS files matched the pinned baseline, were backed up and replaced, then read back with matching SHA-256 hashes. Release PASS; activation, legacy URLs and data reset unchanged. No new tests were added for this reversible CSS-only adjustment. Browser pixel-level responsiveness is not certified by these automated checks.
