# Wide wizard canvas with readable controls — 2026-11-09

Baseline: d617548551400470a824646ddb23de367ec482b0.
Runtime candidate: a2c845d69da501932326382eb19bc4beda3bbdf3.

The Native wizard and report page identity row no longer cap their widths at 880px/820px. They use the full inner width of the existing report workspace. Heading, progress, questions and footer trust copy share matching horizontal insets. The outer workspace, Home, navbar and other page widths are unchanged.

Individual controls retain readable widths: normal text/email inputs cap at 42rem, name/email at 32rem, selects at 32rem and long textareas at 80ch. Existing narrower phone/number/plate widths, compact date column, paired fields, 65/35 map-choice layout, and mobile stacking remain. Font family and font sizes are unchanged. No JavaScript, form validation, report schema or provider behavior was modified.

Whitespace and release-driver syntax checks passed locally. The guarded workflow runs all 93 wizard/Smart Address/GEO JavaScript tests, PHP syntax/publication checks and release/rollback tests before upload.

Production release: PASS (run 42637215849, job 129228460341). All 93 JavaScript tests passed; PHP syntax/publication and release/rollback checks passed. The two runtime CSS files matched the pinned live baseline; verified backups and final remote SHA-256 readback passed. Core entrypoint, activation, data and URLs preserved.
https://github.com/Ciborio71/BadAround/actions/runs/42637215849

Visual verification at desktop/mobile viewport sizes remains unavailable behind existing authorized-browser/maintenance restrictions. No access expansion or maintenance change was attempted.
