# BA-REPORT-IDENTITY-06 — Locked WordPress OTP reconciliation

Date: 04/11/2026. **Dormant code deployed; functional/public activation NO-GO.**

## Delivered
- `report-otp-wordpress-reconciler.php`: fail-closed reconciliation of a *reserved* OTP intent to an independently retrieved canonical report/event after the native intake has returned. Rejects mismatched submission IDs, bad receipts, duplicate state, missing canonical report, and lock contention. Re-read outbox while holding the certified native UUID lock. No creation, publication, PII output or ownership attribution.
- `report-otp-wordpress-adapters.php`: bridges `BadAround_Report_Repository::acquire_native_lock/release_native_lock`, existing durable outbox, and read-only canonical reader. No automatic WordPress hooks, routes or execution.
- `test-report-otp-wordpress-reconciler.php`: verified lock acquisition/release, missing report pending, contention pending, canonical receipt link, idempotent retry, wrong ID, duplicate or quarantined intent denial.

## CI and custodial deployment
- PHP/MySQL [41813665263](https://github.com/Ciborio71/BadAround/actions/runs/41813665263) PASS, MariaDB 10.3 [41813665322](https://github.com/Ciborio71/BadAround/actions/runs/41813665322) PASS, scope gate PASS.
- GitHub FTPS [41814016260](https://github.com/Ciborio71/BadAround/actions/runs/41814016260) PASS. Two files appended only to `identity-dormant/` and byte verified. No Core, theme, schema or WordPress route changes.
- Read-only live verification: 65 files in dormant directory, 84 `ba_evento`, 84 private `ba_reports` preserved, no public Identity/Community/OTP routes.

## Remaining go-live gates
- The controller integration must persist a *verified* outbox record using server-owned proof and call reconciliation after certified native intake. It must preserve existing report UUID lock, idempotency, media and retry semantics, and never treat arbitrary client JSON as canonical evidence.
- Proposed report OTP and outbox tables require reviewed schema change, backup, rollback and explicit approval.
- Public Privacy Policy #3 remains an editorial pre-publication draft and must be reviewed and published before real Identity OTP collection. Secret provisioning, real SMTP delivery and browser QA must follow.
- The wizard OTP step is not yet live; public routing and report intake remain unchanged.
- PR #80 stays draft, issue #82 stays open; NO-GO until full end-to-end signoff.
