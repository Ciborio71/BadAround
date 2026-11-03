# BA-IDENTITY-01R — Live Core private QA integration evidence
Date: 2026-11-03.

## Privacy notice gate
- Canonical published WordPress Privacy Policy: page ID 3, slug privacy-policy, modified GMT 2026-10-24 16:26:03.
- `wordpress-privacy-notice.php` binds version `privacy-20261024T162603Z` to that specific published page, timestamp and nonempty content. A changed or unpublished page refuses activation until reviewed.

## Production module custody
- Deployment run 41732482098: PASS; appended `wordpress-privacy-notice.php`, `wordpress-private-bootstrap.php`, and `wordpress-runtime-loader.php`, no Core/bootstrap mutation or DB write.
- Deployment run 41732740451: PASS; added a minimal guarded loader invocation to live `badaround-core.php`. Backup, PHP lint, exact live baseline hash, atomic swap and post-upload hash were verified.
- Core SHA-256 after patch: `18cb9ad21a63e08449eb85b7cb9233846bb02e2c472d05f118cd7d5764b7d175`.
- Runtime entry requires BOTH BADAROUND_IDENTITY_RUNTIME_INTEGRATION_ENABLED and BADAROUND_IDENTITY_PRIVATE_QA_ENABLED to be explicitly true. The patch defines neither flag. No public Identity endpoints are registered.
- Deployment run 41733061193: PASS; appended missing `wordpress-registration-composition.php` without overwriting any existing file.
- WPVibe postflight read-only lists 44 files in `identity-dormant/`; endpoint discovery did not expose Identity login, registration, or Community routes.

## Acceptance checks
- Latest PHP+MySQL CI run 41733061367: PASS.
- Latest MariaDB 10.3 CI run 41733061063: PASS.
- Scope guard run 41733061172: PASS.
- Production Core schema option `1.9.0`; existing 84 events and 84 private reports preserved.
- Structural Identity schema still 10 tables; no test-event reset or user data migration in this tranche.

## Next gates before enabled QA
- Provision a high-entropy root secret via trusted hosting config (never GitHub, browser, DB option or logs).
- Review the actual privacy policy text, cookie handling, exact consent notice version and planned retention; protect user-facing terms.
- Verify production transactional mail, dedicated PDO compatibility, 10-table readiness and private QA identities without using real public traffic.
- Complete account area, comments/replies, owner edits/moderation/audit and end-to-end browser tests. Do NOT turn on public route flags before acceptance.
- The patch is inert with flags absent; to roll back, restore the verified Core backup created by the guarded workflow. Never use historical GitHub Core files as the live baseline.