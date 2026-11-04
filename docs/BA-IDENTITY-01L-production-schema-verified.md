# BA-IDENTITY-01L — Production database structural migration completed

Date: 2026-11-03. Host: badaround.it. Database: MariaDB 10.3.39; prefix cixe6qoXf_.

## Executed and verified
- All ten additive Identity tables have now been created in the production database, with individual WPVibe user approvals.
- Exact tables: ba_identities, ba_identity_challenges, ba_identity_community_budget, ba_identity_moderation, ba_identity_ownership, ba_identity_pending, ba_identity_rate_limits, ba_identity_recovery, ba_identity_sessions, ba_identity_wp_actors.
- The last WPVibe approval `op_28d6d071e6684dea` was executed successfully. The `SHOW CREATE TABLE` check verified the composite primary key and expiry index on ba_identity_community_budget.
- A separate live information_schema.TABLES SELECT explicitly covering ba_identities returned all 10 table names. Note that LIKE 'ba_identity%' by itself excludes the plural table ba_identities.
- A live information_schema.STATISTICS audit returned 23 declared indexes across the 10 tables, including required uniqueness on verified email, resource ownership, recovered report and WordPress user/Identity association.
- All 10 tables use InnoDB. Their approximate information_schema row counts were zero when checked; do not treat InnoDB TABLE_ROWS as an exact proof in later data-bearing use.
- Existing WordPress Core database version remains 1.9.0, unchanged; its automatic installer was not invoked to create the new tables.
- Existing data: 84 ba_evento WordPress posts and 84 ba_reports rows, unchanged at this check.

## Not yet accomplished
- Schema availability does NOT activate Identity, OTP, accounts, sessions, Community, comments, moderation UI or revision UI. The 31 runtime modules remain dormant on the live server.
- No ownership grants for previous records, no simulated consent, no automatic publication, and no reset of pre-beta test events.
- Backup existence was confirmed by the project owner; an independently tested restore is not documented in this connector session.
- Before activation: complete secure server-side root-key provisioning, email OTP transport and privacy/consent workflow; verified session issuance and CSRF handling; trusted WordPress bootstrap, moderation audit, revisions and private acceptance tests.

## Release invariant
Keep the production Core baseline canonical, perform additive targeted changes only, keep public feature flag disabled until complete acceptance and preserve all test records until final stable release plus separately scoped reset.