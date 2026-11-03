# BA-IDENTITY-01P/01Q — Registration consent and live deployment evidence
Date: 2026-11-03.

## Complete code in isolated branch
- Mandatory first_name, last_name, email and affirmative privacy_acknowledged before new registration.
- `registration-service.php` stages an encrypted private profile with explicit checkbox evidence, notice_version and server time; sends purpose-bound enrollment OTP using an injected transport.
- On mail delivery failure, invalidates the challenge; OTP verification/identity creation use the existing atomic MariaDB enrollment path.
- `wordpress-registration-rest.php` is flag-OFF by default, requires HTTPS, exact Origin and JSON, returns a generic opaque challenge receipt and never exposes the private identity ID.
- `wordpress-registration-composition.php` composes the trusted PDO repositories, encrypted enrollment, quota and existing transactional mailer. It is not yet on the live server.

## CI results
- MariaDB 10.3 parity workflow 41730296385: success.
- PHP+MySQL suite workflow 41730296452: both jobs success.
- Scope safety workflow 41730296352: success.

## Production update
- Scoped FTPS workflow 41729805093: success. Two existing dormant modules patched with source match, backups and rollback, and `registration-service.php`, `wordpress-registration-rest.php` appended.
- WordPress read-only postflight confirms 40 directory files: 37 PHP modules and 3 manifests, with updated encrypted consent code.
- The live plugin bootstrap has not changed, no Identity routes are registered, no real OTPs or registrations performed. No production schema changes beyond the earlier ten-table migration.
- Existing 84 ba_evento records confirmed after this deployment. Do not reset until stable version.

## Still required before public enablement
- Connect registration composition to Core with verified injected root secret and real privacy policy/version; confirm consent copy, data retention and deletion.
- Provide tested client UI and actual transactional delivery; verify cookies, identities, sessions, permissions and audit under private QA.
- Do not assume test-passing code implies an activated feature.