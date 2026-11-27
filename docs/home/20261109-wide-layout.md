# Full-width home and application navigation — 2026-11-09

Baseline: a1ab68c2db5dddbd8d40e05c6c9cec5ffdbcc6fc.
Runtime candidate: 9339fd78304d04aaf70f4b44e2b927e25c2a5951.

User reported a narrow home after the report-heading adjustment and requested the same use of screen width as Map/Radar, with the logo at the left edge and report CTA at the right.

Source inspection: report-heading adjustments are confined to report workspace selectors. report.css and native-report.css are conditionally enqueued for reporting screens. No source evidence links that adjustment to a global Home constraint. The shared .ba-container--wide baseline separately caps header and Home section content at 1680px. This produces increasingly wide margins on larger screens. Visual browser confirmation of the reported symptom remains unavailable behind maintenance/access restrictions.

Implementation changes only home.css and shell.css. The shared header's own inner container and Home's own wide containers now use the viewport/container width minus 32px (16px gutter per side), with no maximum width. The global reusable container definition is untouched, preserving wizard, account, legal and event-detail content widths. Above 1712px, the Home hero text column stops at 760px and its left padding stays 32px, giving the map the remaining width while keeping existing text measure and heading sizes. Existing tablet/mobile navigation breakpoints remain unchanged.

Validation: four Home proximity JavaScript tests passed locally; whitespace and release-driver syntax checks passed. The guarded workflow additionally runs Home server tests, 92 wizard/Smart Address/GEO JavaScript tests, PHP syntax/publication tests and rollback tests.

Release: PASS, run 42630367753 / job 129204824827. Four Home proximity JS tests and all 92 wizard/GEO/Smart Address JS tests passed. PHP Home/publication and release/rollback checks passed. The two runtime CSS files matched the pinned live baseline before installation; backups and final SHA-256 readback passed. Core entrypoint, activation and data preserved. No reset or security/access change.
https://github.com/Ciborio71/BadAround/actions/runs/42630367753
