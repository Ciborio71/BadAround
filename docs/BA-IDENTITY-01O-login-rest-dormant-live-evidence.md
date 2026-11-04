# BA-IDENTITY-01O — Secure login HTTP boundary and production custody
Date: 2026-11-03.

## Source and behavioral test
- `wordpress-session-cookie.php` defines the `__Host-ba_identity` Secure/HttpOnly/SameSite=Lax/Path=/ cookie boundary. It rejects unverified HTTPS and missing/invalid bearer secrets, and supports safe expiration for logout.
- `wordpress-login-rest.php` implements a flag-off WordPress REST transport for login start and verify. It requires HTTPS, exact Origin and JSON; rejects cross-site requests. No hooks are registered absent BADAROUND_IDENTITY_LOGIN_ENABLED===true.
- Login-start responds with a 32-character opaque challenge regardless of success or failure. Failed requests receive a decoy ID; no raw OTP is exposed. Issuance remains rate limited and integrated with the existing transactional email transport via the previously tested adapter.
- Login-verify consumes the purpose-bound OTP once, checks verified identity before issuing the session, sets the host-only cookie and returns only a CSRF token to JavaScript. A cookie-emission failure attempts to revoke the newly issued session.
- `test-wordpress-session-cookie.php` tests HTTP denial, malformed bearer, host-only attributes and writer failure. `test-wordpress-login-rest.php` covers successful request/verify, invalid and replayed OTP, HTTPS/Origin enforcement, and failed cookie issuance.
- Isolated PHP tests require no real email provider, browser or WordPress registration. They do NOT constitute public acceptance.

## Current production custody
- GitHub Actions run 41727472079: `IDENTITY_LOGIN_APPEND_PASS=4; CORE_UNCHANGED=1; DB_UNCHANGED=1; FEATURE_ENABLED=0`.
- Added four new PHP files only: mysql-verified-resolver.php, wordpress-login-composition.php, wordpress-session-cookie.php, wordpress-login-rest.php.
- Verified remotely byte-for-byte with append-only safety; refused existing-file overwrite. Existing `badaround-core.php` fingerprint unchanged.
- WPVibe postflight lists 37 files in identity-dormant: 35 PHP modules and two manifest text files.
- Schema remains Core 1.9.0; 84 events and 84 reports unchanged. Ten Identity tables exist as previously verified.

## Incomplete activation requirements
- The login controller is still not required or instantiated by live Core. Its WordPress routes are not registered.
- Dedicated production root secret is not provisioned for this controller; no actual OTP email was sent in this work.
- First-time enrollment consent, privacy, profile rules, public frontend, parent replies, revisions, moderation UI, audit/retention and browser QA must be finished before turning on flags.
- No event reset before final stable release.