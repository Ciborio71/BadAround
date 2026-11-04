# BA-REPORT-IDENTITY-02 — Account-free OTP storage and atomic report binding

Date: 04/11/2026. Status: **implementation and disposable CI complete; no public activation**.

## Product contract
- Reporting an event does **not** require a WordPress/Identity account.
- Private first name, surname and email remain mandatory.
- A future OTP `report_submission` is bound to the one specific `submission_id` and normalized email lookup, with no reuse of `enrollment` or `login` codes.
- A verified email is **not** proof of ownership of historical reports. Ownership requires separate evidence and a reviewed workflow.
- Moderation and Publication Service are not bypassed.

## New isolated components
- `report-otp-schema-candidate.php`: additive 12th table candidate `ba_report_otp` with unique submission, HMAC email lookup, OTP digest, expiry, attempts, verified/consumed timestamps and version. **NOT applied to production.**
- `report-otp-store.php`: CSPRNG 8-digit OTP, key separation from Identity, 10-minute lifetime, stored HMAC digest, at most five attempts, row-locked verification, anti-replay checks, and claim using caller-owned PDO transaction.
- `test-report-otp-mysql.php`: disposable DB tests no plaintext email/code storage, malformed/expired/replayed code rejection, other-email/other-submission denial, verification one time, claim required same transaction, rollback retry and committed duplicate denial.
- CI PHP/MySQL run **41804430052** both jobs PASS; MariaDB 10.3 run **41804430146** PASS; scope safety **41804430019** PASS.

## Canonical persistence integration — mandatory before release
1. Inspect the current `BadAround_Native_Report_Golden_Path_Service` and existing `ba_reports` / `ba_evento` persistence transaction boundaries.
2. Introduce a server-only OTP issuance endpoint with trusted server IP, persistent rate-limit per email/IP, and actual transactional mailer. Never return the OTP or raw email via REST or log.
3. Bind the challenge to the stable canonical `submission_id` prior to submission, without a user account. The report request may provide an opaque challenge ID, but **the backend must load the proof from storage** and compare it against the **server-derived** normalized email HMAC and submission ID; browser JSON is never a proof.
4. Verify the OTP before granting any submit capability. `claim_in_transaction()` must run within the exact canonical persistence transaction/connection and commit **together with the new report**. If native report persistence has a different transaction or WordPress API writes that cannot share the PDO transaction, use a reviewed transactional outbox/reservation protocol instead; do **not** assume the current adapter is directly pluggable.
5. On mail failure invalidate the challenge. On quota store failures deny issuance. Do not alter public intake until real email delivery, privacy notice, key custody, browser and rollback tests pass.
6. Prevent duplicated submissions from consuming new codes, and preserve existing idempotent canonical receipts.
7. Add dedicated feature flags and explicit go/no-go; old report flow remains as-is until this is accepted.

## Current exclusions
- **No new live DB table.** Requires explicit schema approval, backup and rollback.
- No staging/production OTP email, secret provisioning, REST route, WordPress wizard hooks or public CSS changes.
- No user data generated or copied.
- Existing published Privacy Policy still visibly states `BOZZA PRE-PUBBLICAZIONE`, so no real account/OTP onboarding.
- PR #80 draft, issue #82 open. NO-GO for public activation.

## Live architecture finding (read-only inspection, 04/11)
Production native Golden Path uses BadAround_Report_Persistence_Service, which creates events with wp_insert_post(), updates reports with WordPress $wpdb and holds a named submission lock. This is NOT a documented single PDO transaction with the new ba_report_otp table.

Therefore DO NOT call claim_in_transaction() on a distinct PDO connection after WordPress report persistence and assume atomicity. Such an implementation risks consuming an OTP without a report or writing a report without consuming the proof.

The next integration must use a reviewed shared transactional boundary OR a durable report-reservation/outbox state machine under the existing submission lock. Required tests: PHP process interruption, database failure, duplicated calls, retries and recovery. No live enforcement until this is implemented and accepted.
