# BA-REPORT-IDENTITY-05 — Scoped production custody and readiness

Date: 2026-11-04. **Release status: DORMANT DEPLOY PASS / PUBLIC ENABLEMENT NO-GO.**

## Completed and verified
- Read-only `report-otp-canonical-reader.php` checks matching native UUID source identity, validated private report, linked `ba_evento`, non-trashed post, canonical payload UUID and authoritative `_ba_primary_report_id`. Never returns email or private report body.
- Disposable SQL regression `test-report-otp-canonical-reader-mysql.php` denies invalid UUID, failed report, incorrect linkage, deleted event; PHP/MySQL workflow 41812234377 PASS; MariaDB 10.3 workflow 41812234201 PASS.
- Ten **non-executing** PHP report-OTP modules transferred append-only into live `identity-dormant/` using guarded workflow [41812463367](https://github.com/Ciborio71/BadAround/actions/runs/41812463367), all 10 byte-verified. No Core/theme changes, no database creation/update, no hook, route or feature enabled. Predeploy safety, CI PHP/MySQL and MariaDB checks all PASS.
- Live read-only postflight: 63 files total in dormant directory; 84 `ba_evento`, 84 private `ba_reports`, Core schema version `1.9.0`; no public Identity or report OTP routes.

## Technical gate remaining
The existing native intake persists report and event in multiple WordPress `$wpdb`/post-API phases. The isolated PDO proof plus outbox candidate is not enough by itself: an audited WordPress-native coordinator must hold the existing UUID lock, validate the OTP-specific email and submission UUID, use the canonical intake and detect retry/recovery, then reconcile the server-loaded actual report+event before marking the outbox linked. The adapter is **not yet wired to the native Core**, and the proposed new OTP/outbox tables have not been created in production.

## Business and compliance gates
- WordPress Privacy Policy #3 is *published but contains an explicit legal pre-publication draft*. Needs substantive editorial/legal approval and release of an updated version/retention schedule. The existing fail-closed identity gate prevents real onboarding until then.
- Server root secret, proper proxy/IP configuration and authorized real TurboSMTP OTP delivery test are missing.
- Wizard UX must have a proper account-free OTP step, including retries and media without regressions; full desktop/mobile end-to-end tests and rollback are required.

## Decision
**Do not enable public OTP/report registration yet.** The requested scoped *code deployment* is complete; **functional go-live is not complete**. No reset of existing test reports or PR #80 merge. Tracking issue #82 stays OPEN.
