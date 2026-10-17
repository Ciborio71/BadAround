# NF-VISUAL-02 — Native visual continuity with the legacy report form

Owner requested the recognizable legacy card interface after clarifying that NF-FINAL-01's subtle polish did not meet that visual expectation. Baseline: staging deployment 8b73163. Scope remains staging only, without report submissions, DB writes, media enablement, backend or Smart Address changes.

## Visible changes

- Six category cards reproduce the legacy icon paths, colors, explanatory descriptions and selected treatment. Canonical category keys and membership are unchanged.
- Category layout: three columns desktop, two tablet, one narrow mobile. Native radio inputs provide keyboard/assistive support.
- Subtype cards appear after category selection; relationship choices appear after a valid subtype. This disclosure affects presentation only, not the schema or required validation.
- Other short enum questions (up to six choices) become cards; long lists remain selects. Step 2 stays untouched with one visible Google address search.
- Native selects remain the single canonical model input. Card input/change events delegate through those controls, preserving validation and the existing media guard. Payload equivalence is tested.
- Field error links and image-removal focus target visible radios rather than the hidden presentation select. The sole media-view change is focus targeting; no upload/backend behavior was edited.
- No WPForms selector, form ID or runtime dependency introduced. Original selects remain usable if enhancement does not run.

## Verification

- Native completion gate plus four new card regressions: 134 JS/DOM tests.
- New coverage: category/subtype disclosure, invalidation of incompatible subtype, focus on visible error controls, back-navigation selection retention, payload equivalence, disabled snapshot states and change-only assistive input.
- Local Chromium checks all seven steps at 320, 390, 768 and 1440px: overflow, >=44px navigation targets, category arrow keys, Tab order, Space checkboxes, error focus and heading focus. Screenshots reviewed on desktop and mobile.
- JS syntax and PHP lint; PHP application files unchanged.
- Four runtime deployment files only: native-report.css, wizard.js, errors.js and media-view.js.
- Staging source preflight matched 8b73163 for all four files. Rollback source is that verified baseline.

No production deploy, core plugin change, Smart Address change, F1.2 change, media enablement, DB mutation or real submission.
