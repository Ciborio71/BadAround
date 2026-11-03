# BA-IDENTITY-01J — MariaDB parity and dormant security patch
Date: 2026-11-03. Production-first approved.

## Database engine discovered
- Production database: MariaDB 10.3.39-MariaDB-log (not MySQL 8).
- DB option ba_db_schema_version stays 1.9.0.
- No ba_identity_* production tables present at preflight.
- Inventory before reset: 84 ba_evento and 84 private reports.

## Parity CI
- GitHub Actions run 41716052322: PASS on MariaDB 10.3.39.
- CI exercised ten-table additive schema candidate, transactional community quotas, ownership, WordPress actor binding, MySQL Gateway, and end-to-end WordPress composition.
- This is schema compatibility only, not permission to migrate without verified DB backup and restoration.

## Exact production dormant patch
- GitHub Actions run 41716397897: SUCCESS.
- Existing dormant REST adapter patched only its cookie constant from ba_identity_session to __Host-ba_identity.
- SHA-256/byte-for-byte source match required before replacement; old source backed up and verified.
- Core main file unchanged, database unchanged; postflight WPVibe confirms the new constant.
- No new REST routes or hooks registered, no events deleted.

## Still required for user-visible activation
- Recoverable database backup and restore rehearsal, then an additive production schema migration.
- Verified dedicated key custody / rotation, enrollment and login cookie issuance, form consent and lifecycle.
- Complete revision and comment review routing, snapshot privacy, audit, rate-limit and retention policy.
- Scoped Core live bootstrap and end-to-end private QA; stable beta signoff before destructive reset.