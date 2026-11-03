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

## Coordinator OTP → session (2026-11-03)

- Added `verification-coordinator.php` and deterministic tests. It resolves an **existing, verified, non-revoked identity** from a trusted server-side email HMAC; rejects unknown identity before OTP consumption.
- Requires successful **atomic** OTP consumption, rechecks verified identity, then creates a session. An OTP cannot issue a second session.
- No HTTP response, cookie, WordPress login, email message, plugin bootstrap, or database migration is included. Caller must attach cookie after session persistence.
- This is intentionally NOT the first-time identity enrollment flow: collecting mandatory name/surname/email, checking consent, sending OTP and creating the verified identity require separate privacy/atomicity work.
- Future production coordinator must handle the race between verified-identity revocation and session creation with a transactional/locking strategy, plus session revocation on identity revocation.
- A session issuance failure after OTP consumption requires a new OTP; do not retry the same consumed credential.
- Fixed malformed extension-check command in GitHub Actions MySQL job; job execution results are **not confirmed** from connector.

## First-time enrollment (2026-11-03)

- Added `enrollment-policy.php`: mandatory first/last name/email validation, normalized email bound cryptographically to the OTP challenge, private pending state and one-time post-verification activation. No WordPress user creation.
- Added `test-enrollment-policy.php`: missing name, invalid email, wrong OTP, OTP replay, duplicate verified email and cross-email challenge mismatch cases.
- **Current adapter is memory-only.** The real identity/pending repositories, uniqueness constraint on email HMAC, expiry/deletion of pending PII, simultaneous enrollment race handling and privacy notice/consent integration are NOT yet implemented.
- Existing login coordinator currently handles already verified identities only; do not treat the two prototype components as a complete public enrollment/login endpoint.
- OTP purpose separation (login vs new enrollment), atomic identity activation and session issuance, email delivery, rate limits and revocation transactions remain explicit release blockers.
- GitHub Actions was extended to lint/test enrollment; **pass/fail must still be checked**. No merge/deploy until a controlled integration review.

## MySQL encrypted enrollment adapter (2026-11-03)

- Added `mysql-enrollment-store.php`, which persists pending private profile fields encrypted with AES-256-GCM and creates one verified identity using an InnoDB transaction and a UNIQUE indexed email HMAC.
- Added `test-mysql-enrollment.php` in disposable CI database. Covers encrypted pending storage, invalid OTP rejection, one-time activation, replay rejection, and duplicate verified-email rejection. More real concurrent enrollment and crash/recovery tests are still necessary.
- Enrollment staging now requires a stored, matching, unconsumed and unexpired OTP challenge; fabricated challenge records cannot stage a profile.
- Added a **review-only** draft of pending/identity schemas. No migration is shipped and no public WordPress handler is registered.
- **Release blockers addressed in isolated MySQL path:** OTP consumption and activation now use a single InnoDB transaction. The memory-only test double still uses a two-phase flow, and no live WordPress integration has been tested. The active-identity record currently contains encrypted PII, but key management/rotation, retention, consent proof, and deletion mechanisms are not implemented.
- The draft CI workflow's MySQL job was reconstructed after malformed commands were found. **Do not assert CI success until GitHub run logs show passing tests.**

## Atomic identity enrollment transaction — 2026-11-03

- `BadAround_Identity_Atomic_Enrollment_Store` allows the main enrollment policy to delegate OTP verification and profile activation to one database transaction.
- The MySQL adapter locks the challenge row (`SELECT ... FOR UPDATE`), accounts for wrong-code attempts, checks pending profile binding, creates identity under a unique email key, updates OTP and pending flags, and commits all together. A uniqueness exception rolls back the OTP as well.
- The old `activate_once` method in the MySQL adapter throws `LogicException` to prevent bypassing OTP verification.
- Integration assertions cover successful OTP consumption, wrong-code attempts and rollback after duplicate-email rejection. Existing two-worker challenge test remains in CI.
- **CI execution has not yet been observed as PASS**; do not merge/deploy based on file creation alone.
- **Important remaining gates:** transactional concurrent enrollment test across independent challenge IDs for same email, verified purpose binding to challenge, deletion/retention policies, audit logs, real delivery/retries, database-backed session revocation on identity revocation, key rotation, public error normalization, and integration with WordPress/Core.

## Parallel same-email enrollment gate — 2026-11-03

- `test-mysql-enrollment-race.php` uses **two independent PDO connections**, two OTPs for the *same* normalized email and a two-worker barrier. The test requires exactly one activated identity and one committed OTP consumption; the losing transaction must roll back its OTP.
- The test runs against a disposable `ba_identity_ci` MySQL service only; no production database access or secrets.
- Added to the GitHub Actions `identity-mysql` job. **No passing CI logs have been independently observed in this session**, so the gate remains **unverified**, not PASS.
- Remaining before public WordPress integration: OTP purpose binding, server-side throttling and email delivery, transactional ownership grants, verified-identity revocation/session invalidation, identity key rotation, retention/deletion controls, audit and CSRF middleware, and private area UX.

## Explicit ownership and CI gate — 2026-11-03

- Added `mysql-ownership-store.php`, a dormant repository with explicit creator bindings keyed by resource type and ID. The SQL schema in the ephemeral test uses UNIQUE(resource_kind,resource_id) to prevent competing creators from taking ownership.
- Added `test-mysql-ownership.php`: owner access, other-identity denial, wrong-resource denial, wrong-kind denial, duplicate-grant denial and revocation checks. CI syntax and MySQL jobs include these files.
- **Release blocker:** `grant_creator()` is NOT an end-user endpoint. The caller must prove the identity is verified and associate ownership in the same controlled transaction that creates the report/comment. A future production adapter also needs owner revocation policies and per-resource authorizations.
- The current implementation is isolated prototype code. No installed WordPress plugin includes or DB changes were made.
- **GitHub CI:** workflow definitions have been updated, but live run results remain unverified because the available connector does not list push-triggered workflow run statuses. Do not merge or deploy without logs from both jobs.

## 01D pre-integration tranche — 2026-11-03

- Production source was read-only inspected: actual live intake is `BadAround_Report_Intake_Service::ingest`, with idempotent `report_id/event_id/duplicate` receipt; public contribution REST route is already operational, with email required but not approved first+last name and only 2 public display modes; email transport is existing `BadAround_Transactional_Mailer`. See `docs/BA-IDENTITY-01D-live-core-integration-gates.md`.
- New `mysql-rate-limiter.php`: transactional fixed-window rate budget, purpose + opaque email/IP HMAC buckets, no raw PII in the table. Caller MUST derive client IP from a trusted origin and fail closed when database unavailable. Test covers limits, dimensions and resets; the real email sender is NOT wired.
- New `post-intake-binder.php`: isolated boundary for **server-trusted canonical intake receipts**; refuses duplicates, wrong resource/event pair, unknown identity and second grants. A caller still MUST prove a verified session, and handle rollback/outbox across WordPress persistence and separate ownership storage. Tests use fixtures, not production APIs.
- New `test-post-intake-binder.php`, `test-mysql-rate-limiter.php`, updated CI and review-only DDL.
- **Release gates still open:** observed green CI, separate challenge purpose and per-IP/email quota wiring, transactional or recoverable ownership linkage after live intake, private review/moderation workflow for edits, revised mandatory name/surname/email on contributions and comments, public identity display modes, real session cookie/CSRF controllers, revocation, encrypted PII retention/deletion, WP live Core diff and explicit deploy authorization.
- Absolutely no plugin bootstrap, production schema migration, live reset or deploy performed.

## Community and proposed revisions, 2026-11-03

- Added `community-policy.php`: all four approved public identity modes (anonymous, pseudonym, first name, first name + surname initial), pending-only comment intents, one-level replies only to an approved top-level comment of the SAME event, and owner-checked private description revision intents.
- Added `test-community-policy.php` to isolated primitive CI. Moderation is immutable at request creation (`pending`), and an edit request cannot directly mutate a published event.
- This is **not** the live comments UI, persistence, real ownership lookup, comment abuse report, notification system, or publication workflow; these remain separate integration tasks. The caller must escape public text on rendering and keep private identity fields outside the public projection.
- Live contribution form still differs from contract; WordPress changes require a separate controlled review and no regression to existing contributions and Radar/maps.
