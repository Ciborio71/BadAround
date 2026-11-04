# BA-IDENTITY-01E — Migration and runtime integration release gate

**Status: design / READ ONLY / NO DEPLOY.** Date: 2026-11-03.

## Live Core facts verified from production (read-only WPVibe)
- `badaround-core.php` identifies Core version **0.18.0** and loads the installer, native report intake, existing contributions and publication service.
- `BadAround_Installer::SCHEMA_VERSION` is **1.9.0**; `maybe_upgrade()` is hooked to `admin_init` **and** `init` at priority 30 and calls `install_schema()` on mismatch. The installer runs `dbDelta($sql)` and updates `ba_db_schema_version` afterwards.
- Existing private data: `{$wpdb->prefix}ba_reports` contains `author_name`, `author_surname`, `author_email`, `user_id`, consent fields, report/event linkage and `deleted_at`. These fields are not evidence of a verified BadAround Identity ownership binding.
- Existing `ba_contributions` and notification/media tables have their own verification/moderation model. Existing contribution records must remain intact.
- The canonical intake service emits `report_id`, `event_id`, `duplicate`; `BadAround_Publication_Service` remains the sole B3 publication authority.

**Limit of inspection:** WPVibe read-only source can mask patterns and does not prove a byte-for-byte baseline. This audit did **not** query or modify the production database.

## Release architecture — do not bundle this in the normal installer yet

A separate, manually gated `IdentitySchemaInstaller` must run only after:
1. immutable backup and restore test of production Core and database;
2. source checksum reconciliation and exact schema inventory (SHOW CREATE TABLE and indexes via privileged read-only connection);
3. migration dry run on a disposable clone and successful rollback drill;
4. explicit approved maintenance window and feature flag confirmed **OFF**.

Do **not** change `BadAround_Installer::SCHEMA_VERSION` just to introduce Identity: because `maybe_upgrade()` also runs at public `init`, an unreviewed schema change could affect live traffic.

## Proposed additive tables, final names to be reviewed against real prefix
- `ba_identity_challenges`: opaque challenge ID, email HMAC, purpose-bound digest, attempts, expiry and consumed/version fields. No cleartext OTP or email.
- `ba_identity_pending`: encrypted mandatory first name, surname, email; opaque lookup; expiry, privacy consent version/time and verification state.
- `ba_identities`: unique normalized-email lookup HMAC, encrypted PII, verification/revocation timestamps, key version.
- `ba_identity_sessions`: HMAC session digest, identity ID, fixed/idle expiries and revocation.
- `ba_identity_rate_limits`: independent purpose/email/IP opaque buckets and interval counters.
- `ba_identity_ownership`: UNIQUE(resource_kind,resource_id) creator binding, verified identity ID, revocation and audit.
- `ba_identity_recovery`: unique operation and report IDs, receipt linkage, state, retry/version, manual-review routing.
- `ba_identity_moderation`: immutable private encrypted comment/revision intent, status/version, moderator, decision timestamp; public presentation must be a separate approved projection.

The existing prototype `schema-review-only.sql` is **not** authorized as a production migration. Reconcile its column names and foreign keys against this plan before writing any executable installer.

## Transaction and recovery invariants
- A newly created report's ownership may only be bound from a server-issued receipt with `duplicate === false`, valid verified session, and canonical `report_id/event_id` check.
- If report creation and ownership use different transactions, persist a durable recovery intent atomically with the chosen source of truth (or implement a proven outbox); do not return `owned: true` while ownership is pending.
- A single report may never have two creator identities. Duplicate submissions are not a means to claim old/test events. Fail closed on storage errors and expired/revoked identities.
- Moderation is a server-side privileged action, not a flag passed in a request. Approving a proposed revision does not mutate the published event; apply it only through existing controlled B2/B3 and Publication Service.
- One-level comment replies may only target approved comments on the same published event. Pending/rejected comments are not public.
- No retrospective migration of existing report emails into verified identity owners. A separate, consented, auditable claim process would be required.

## Feature enablement sequence
1. Schema in a disposable clone, rollback PASS, then explicit approval for additive live migration while the feature is **OFF**.
2. Real WordPress adapter/service binding and audit, API security (Origin + CSRF + secure cookies), pre-send OTP quotas, verified session and ownership checks.
3. New wizard and contributions collect mandatory first name, surname and email privately, privacy consent proof and optional anonymous/pseudonym/first-name display.
4. Private moderator inbox for comments and revision proposals; no bypass of existing Contribution Service or Publication Service.
5. Browser/mobile/A11Y/security tests, production smoke for maps/Radar/SEO/public event pages, rollback rehearsal.
6. Explicit release approval to enable the flag and separately authorize removal of test events.

## Current exit status
- Isolated policies and MySQL CI: **PASS** at commit `87ae87ad` (run `41667733869`).
- Live schema checksum and physical backup/restore: **NOT VERIFIED**.
- WordPress runtime and browser QA: **NOT IMPLEMENTED**.
- Public release: **BLOCKED**. No merge, DB write or deployment.
