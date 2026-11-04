# BA-IDENTITY-01F — Production-first rollout and beta reset policy
Date: 2026-11-03. Project-owner decision.

## Environment
- Production (https://badaround.it) is the sole integration and QA environment in this phase.
- Staging is bypassed; restoring it is not a prerequisite for current work.
- Approved live Core/theme is canonical. Do not upload historical GitHub snapshots or run broad full-plugin FTPS deployment.
- Apply isolated additive changes, default-off feature flags, verified backups, pre/post checks and rollback capability.
- Production-first does not waive privacy, security, schema verification, backups or acceptance tests.

## Release sequence
1. Complete WordPress runtime integration for identity, OTP, sessions, ownership, comments and moderated revisions without bypassing canonical intake or Publication Service.
2. Test schema migrations on disposable MySQL databases, then separately authorize narrowly scoped production migration with proven backup and rollback.
3. Run private production QA and full regression checks on Home, Mappe, Radar, events, SEO, permissions and privacy.
4. Establish a stable definitive release with an explicit acceptance gate.
5. Prepare a separate destructive reset inventory: events, reports, media, comments/contributions, ownership, moderation, audit, Radar projections and retention obligations. Identify which data must be retained.
6. Obtain separate explicit authorization for the precise test-event reset after backup, only after the stable release.
7. Begin a new beta-test dataset with fresh submissions.

## Invariants
- No reset now and no automatic event deletion as part of deployment.
- Do not infer verified ownership from legacy email/name fields.
- Keep privacy, audit and B2/B3 publication guards intact.
- This changes the environment strategy; it does not claim runtime or deployment readiness.