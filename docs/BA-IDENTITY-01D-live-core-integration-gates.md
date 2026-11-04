# BA-IDENTITY-01D — Real Core integration gates (read-only production review)

Status: **design only**. Code in `prototypes/ba-identity-01c/` is dormant and **must not be enabled in production**. Audited live source 2026-11-03 via WPVibe; the online version is canonical, not GitHub main.

## Existing live seams, verified

1. `plugins/badaround-core/includes/class-badaround-report-intake-service.php`, `BadAround_Report_Intake_Service::ingest()`, accepts canonical report and source; enforces source lock and native submission idempotency, persists private `ba_reports`, then creates a pending `ba_evento`. Return includes `report_id`, `event_id`, `duplicate`. **Never create an alternative intake or bypass Publication Service.**
2. `plugins/badaround-core/includes/class-badaround-contribution-service.php` already handles public event contributions, verification via email and moderation. The live form requires **email only**, has two public identity modes (`public_anonymous`, `public_alias`), and does **not** implement the newly approved mandatory first/last name + four public display modes. `POST /badaround/v1/contributions` uses its own rate limits and consent flow. New discussion/comment features must not silently replace this established path.
3. `plugins/badaround-core/includes/class-badaround-transactional-mailer.php` offers `BadAround_Transactional_Mailer::send()` using the existing turboSMTP transport. Reuse it; do not add new credentials or send OTP before **both email and IP** quota checks succeed.
4. Existing `page-area-personale.php` currently uses WordPress authentication placeholders. It is not yet a BadAround passwordless identity front end.

## Correct integration sequence (not executable on production yet)

1. **Reconcile live Core files byte for byte**, plugin bootstrap and installer/schema before patching. No copy from stale GitHub main over production.
2. **Schema upgrade designed and reviewed only**: challenges, pending protected enrollments, verified identities, sessions, rate-limit buckets, verified ownership, and revision requests. Reversible additive migration, backup, explicit deployment authorization. No schema version bump during review.
3. **Identity core**: purpose-bound OTP with no PII or OTP in logs; mandatory name, surname, email; initial pending profile with defined retention; atomic email activation; secure session cookie, CSRF/Origin, logout and bulk session revocation when identity is revoked. Existing login vs new enrollment use distinct challenge purposes and protected against enumeration.
4. **Pre-send throttling**: enforce independently IP and normalized-email HMAC quotas for OTP issue and resend; do not infer client IP from untrusted forwarded headers. On quota-store failure, fail closed.
5. **Report creation ownership**: verified session is checked server-side; only after canonical intake returns a confirmed *new* report/event (not duplicate) can a creator binding be attached. A reliable transaction/outbox/compensating recovery is needed because WordPress report persistence and prototype PDO ownership inserts are currently independent operations. **Never claim atomicity across the two without a real shared transaction.** No email-based retrospective claims to existing test events.
6. **Edit request workflow**: any report modification creates a private proposed revision linked to the original report, with immutable original, time, actor and audit event. Moderator approval through existing moderation and Publication Service is required to change the public event. No direct edit of published projection.
7. **Comments and updates**: retain existing contributions intact while new moderated one-level discussion is introduced; mandatory private identity and per-contribution public display choice. Newest first, reply-to-one-level, abuse reporting, and consistent moderation. Do not publish comments before review.
8. **Area personale**: show only resources with explicit verified ownership. Cookie/session plus CSRF for writes; no user identification by email query. Username/pseudonym must not leak PII.
9. **Release gates**: real CI green for primitive + isolated MySQL, DB migrations tested in ephemeral clone, full browser/accessibility tests, privacy/retention acceptance, staged feature flag, rollback and no-regression tests for maps/Radar/events/SEO.
10. **Reset test events only after** new wizard, private moderation, contributions, personal area and full smoke tests are working. Explicit separate authorization for destructive reset.

## Current differences that require explicit decisions before live patch

- Live contribution form asks only email; approved name/surname/email mandatory for *all new submissions and comments*. A scoped migration strategy must avoid breaking existing contributions or inventing missing identities.
- Live public contribution modes (anonymous/alias) do not yet implement approved 4-mode display.
- Native report intake has existing idempotency; reporting the same submission must not create a second ownership grant.
- Prototype PDO transaction cannot automatically envelop WordPress CPT/postmeta persistence; ensure ownership reconciliation is fail-closed and auditably recoverable.
- The branch's prototype has no actual live email send, HTTP route, cookie issuance, consent capture, permanent audit, or key rotation.

## Immediate next technical work

Implement a **dormant integration boundary** and test doubles for intake receipt, trusted server auth and ownership grant that refuse duplicates and reject unverified identity. Do not activate or hook WordPress until the live database contract is certified.
