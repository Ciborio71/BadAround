# BA-IDENTITY-01D — Combined integration gate (3 November 2026)

## Live source audited read-only
- Canonical `BadAround_Report_Intake_Service::ingest` yields `report_id`, `event_id`, `duplicate`. Do not assign ownership on duplicate receipts.
- Existing `BadAround_Contribution_Service` includes email verification, rate limits and public/reserved contribution handling. New comments must coexist with it, never silently replace it.
- `BadAround_Publication_Service` remains the controlled event publication path. Moderation intent approval must not directly update public events.

## Isolated prototype components
- OTP and active-identity/session/CSRF checks.
- Explicit creator ownership grants and durable recovery intent with version checks.
- Private comment/revision proposals and moderator decisions; public changes remain outside these prototypes.

## Required integration gates
1. Reconcile the actual live plugin and schema without deploying stale GitHub files. WPVibe read-only source may be redacted, not byte-exact.
2. Integrate ownership using a recoverable transactional outbox or a proven shared transaction around canonical intake. No email-based retrospective claims.
3. Protect moderator decisions with server-side authorization, durable audit, stale revision detection, retention and deletion policy.
4. Preserve the existing contribution flow while extending private mandatory name, surname and email and per-post display choices.
5. Build a feature-flagged WordPress adapter with secure cookie, CSRF, Origin and quota controls; browser/accessibility, migration and rollback tests before deployment.

No live hooks, API endpoints, database migrations, plugin bootstrap or production deploy are included here.
