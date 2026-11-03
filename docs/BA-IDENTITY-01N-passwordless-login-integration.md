# BA-IDENTITY-01N — Verified passwordless login composition
Date: 2026-11-03.

## Implemented in isolated source
- `mysql-verified-resolver.php` resolves only verified, not revoked identities from a server-computed HMAC email lookup. It refuses malformed input, raw email and database failures.
- `wordpress-login-composition.php` connects MySQL challenge store, purpose-specific login OTP verifier, verified identity resolver and persisted hashed sessions.
- Dedicated session purpose key follows `BA-ID-01H:sessions`, matching the dormant WordPress Community composition; login purpose key uses `BadAround_Identity_Purpose_Keys::for_purpose('login', root)`, preventing enrollment OTP reuse.
- Tests `test-mysql-verified-resolver.php` and `test-wordpress-login-composition.php` exercise unknown and revoked identities, database failure, invalid/replayed OTP, cross-purpose misuse and persisted opaque session secrets.

## Deployment policy
- This change remains in `prototypes/ba-identity-01c/` and is not automatically uploaded to WordPress.
- Production Identity schema is complete (10 tables), but public login endpoints, opt-in consent, mail delivery, rate limiting, cookie issuance and private UI remain unimplemented or unconnected.
- DO NOT enable a WordPress route or capture real personal information until those control gates are met.
- Existing `badaround-core.php` 0.18.0 and its accepted site behavior remain canonical and unchanged.

## CI
- Current workflow head b90227b3, extended login composition workflow head 119a4c5b. Confirm full MariaDB 10.3 and MySQL CI jobs before promoting this tranche.