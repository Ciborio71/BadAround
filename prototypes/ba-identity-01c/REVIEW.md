# BA-IDENTITY-01C — Verification notes and deployment gate

**Branch:** feature/ba-identity-01c-dormant-20261103
**Status:** isolated prototype only. **NOT READY for production.**

## Added in this tranche

- `mysql-challenge-store.php`: parameterized PDO SELECT/INSERT and single conditional UPDATE using `version` and `consumed_at IS NULL`. Requires transactional InnoDB.
- `mysql-session-store.php`: parameterized insert/find/revoke (conditional, idempotent-fail-closed).
- `session-policy.php`: 256-bit random bearer token returned once to controller, HMAC digest persisted, absolute expiry, 24h fixed last_seen bound, revocation, secure cookie options; **cookie is not emitted here**.
- `schema-review-only.sql`: DDL design document, no migration.
- Test suite expanded in `.github/workflows/ba-identity-01c-tests.yml`.

## Important limitations

1. **MySQL integration and parallel-CAS tests have been added** to GitHub Actions using a disposable MySQL 8 service; **CI success is not yet verified**. The two-process barrier test checks exactly one winner for the same OTP. Transaction rollback, deadlocks and crash recovery remain untested.
2. **Session idle refresh** is not implemented. For now 24h from issue/last_seen set at creation, notwithstanding the 7-day maximum absolute lifetime. Do not claim sliding inactivity window.
3. No WordPress bootstrap, CSRF middleware, real cookie issuance, rate-limiting application layer, OTP delivery, user lifecycle or public endpoints.
4. No table creation, migration, schema version bump or live install.
5. `ba_identity_sessions.identity_id` references a future identity lifecycle store; no foreign key or identity assignment has been deployed.
6. Challenge `delivery_code` is intentionally returned to a future delivery coordinator; never persist or include in logs, API responses or exceptions.
7. Challenge consumption must commit before session issuance. Cross-system transactional handoff and failure-recovery remain to be designed/tested.
8. Public alias selection and ownership authorization require separate verified bindings and complete moderation integration.
9. CI push workflow status must be inspected: both the pure-test job and the disposable-MySQL job must pass. Green CI does not establish production readiness.
10. Since GitHub is not aligned byte-for-byte with live production Core, no broad theme/plugin deploy is allowed.

## Next gate

Validate the newly added disposable-MySQL CI service and two-worker race test; then implement challenge expiry cleanup, retry logic and secure controller integration. The isolated session-bound CSRF policy and fail-closed edit-guard policy are present, but WordPress middleware integration, CSRF rotation and authenticated resource lookups remain outstanding. Prepare installer migrations only after comparing live schema and obtaining approval.

## Security hardening — 2026-11-03

- Tightened the Origin policy to reject paths, query strings, fragments and user-info in an Origin header, in addition to HTTPS host/port matching.
- Added `edit-guard.php` (prototype only) requiring all three checks for modifying a resource: valid unrevoked session, session-bound CSRF token with strict Origin, and verified ownership for exactly the requested resource.
- Added negative tests for missing/expired/revoked sessions, cross-origin and malformed Origin, other-resource and other-identity attempts.
- **The ownership record is explicitly server-loaded by contract; no request-provided ownership may be trusted.**
- These are isolated policy tests, **not WordPress endpoint integration tests**. CI status still requires inspection.
