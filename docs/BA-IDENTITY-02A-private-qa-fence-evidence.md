# BA-IDENTITY-02A — Private QA fence security release evidence

Date: 2026-11-03. Follow-up issue: #82. Foundation issue #81 remains closed.

## Reviewed threat and correction
- Original dormant private bootstrap assembled the registration controller and registered its hooks before assembling login and Community. A later failure could leave a partially registered feature. New source assembles all three controllers before any registration.
- Original double-feature-flag Core seam alone did not prove that the visiting client was an authorized QA tester. New `wordpress-private-access.php` requires a separate server-managed `BADAROUND_IDENTITY_QA_IP_ALLOWLIST` (array of literal IPs, max 16) matching the web-server observed `REMOTE_ADDR`. Missing, empty, invalid or unlisted values deny every private QA request.
- Proxy forwarding headers such as `X-Forwarded-For` are deliberately ignored; if behind a reverse proxy, its trusted peer mapping must be assessed separately before using private QA.
- Private REST route callbacks retain their independent feature flags, CSRF/Origin/HTTPS requirements and disabled defaults. No access tokens, root secrets or user emails are included in this patch.

## Production custodial deployment
- GitHub Actions run 41735975531 succeeded; exact-match patch of dormant `wordpress-private-bootstrap.php` plus new `wordpress-private-access.php` with backup and rollback. The production Core file was not modified.
- Postflight WPVibe read-only inspection confirms both PHP files and the private QA IP authorization condition.
- REST index still has no public Identity login, registration or Community endpoints.
- Existing WordPress events unchanged at 84. No database writes were requested.

## CI
- PHP+MySQL workflow 41735975529: both jobs PASS.
- MariaDB 10.3 workflow 41735975481: PASS.
- Scoped predeploy safety gate 41735975478: PASS.
- Private QA fence regression includes attempts with unknown peer and forged X-Forwarded-For; all denied. No production QA IP list or private root secret has been provisioned.

## Remains blocking before real QA
- Define server-side high-entropy root secret in protected hosting configuration without exposing it to Git, UI or diagnostics.
- Define an accurate QA peer-IP allowlist only after verifying the actual production reverse-proxy topology. It is not safe to assume the client address presented by PHP is the original tester address.
- Confirm the privacy notice and data retention obligations, run authorized real SMTP test and browser flows; maintain all feature flags OFF until then.

Decision: BA-IDENTITY-02A private fence security hardening PASS, overall issue #82 remains OPEN.