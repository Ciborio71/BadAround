# BA-IDENTITY-01K — Additive schema rollback rehearsal and live backup gate
Date: 2026-11-03.

## Verified production preflight (read only)
- Production MariaDB 10.3.39-MariaDB-log, database table prefix cixe6qoXf_.
- Existing schema version 1.9.0 and 84 reports / 84 ba_evento posts.
- No ba_identity_* tables exist on production.
- WordPress database account has schema creation privileges. This is NOT evidence of a restorable backup.
- Connected WPVibe WordPress abilities do not expose a database backup or restore operation. Its emulated WP-CLI supports db queries but not db export/import.

## CI-only recovery rehearsal
- `schema-recovery-policy.php` introspects exactly the ten Identity candidate tables and refuses removal if any is nonempty.
- Actual DROP is hard-gated to exact `ba_identity_ci` database and `ba_recovery_ci_` temporary prefix with an explicit boolean switch.
- `test-schema-recovery.php` checks idempotent additions, retention of unrelated baseline table, refusal without explicit switch, refusal for populated identity mapping, and deletion of ten *empty* temporary tables.
- Integrated in existing MySQL 8 and MariaDB 10.3 CI workflows.

## Production schema hold
- No CREATE TABLE was run on live MariaDB.
- Before live DDL, obtain a fresh encrypted production database backup outside webroot with a recorded restore procedure; preferably verify a restore to an isolated database and validate WordPress prefix and row counts.
- The recovery rehearsal proves only the CI candidate could be rolled back if EMPTY. It does not replace a production backup and must not be used to discard user data.
- A production migration must remain additive, independently versioned and distinct from Core's automatic maybe_upgrade hook.
- Do not clear existing events/reports as part of schema migration. Reset only after full stable release.