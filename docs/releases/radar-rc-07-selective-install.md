# RADAR-RC-07 — Production-safe selective installation contract

Date: 2026-10-30. Status: **PREPARED; NOT DEPLOYED**.

## Live discovery (WPVibe authenticated, read-only)
- Core `wp-content/plugins/badaround-core/badaround-core.php` live currently ends the include list at `class-badaround-native-media-rest-controller.php`, then `register_activation_hook`; no Radar bootstrap yet.
- Live `wp-content/themes/badaround-child/page-radar-demo.php` has the existing `id="rad-reports"` followed immediately by approved explanatory text. Private preview not installed.
- Live Core includes 58 PHP files; existing archival `class-badaround-publication-service-OLD.php` **must be retained, not copied from GitHub or deleted**.
- Active theme includes live-only changes compared with older snapshots. **Never upload full theme archive or replace plugin directory.**
- Database, `httpdocs`, and `badaround-private-media` backup downloads were confirmed by site owner; restoration is **not verified**.

## Allowed deployment delta only
1. Add 9 NEW files to `httpdocs/wp-content/plugins/badaround-core/includes/`:
   - `class-badaround-intelligence-trends.php`
   - `class-badaround-intelligence-peak-candidates.php`
   - `class-badaround-intelligence-data-quality.php`
   - `class-badaround-radar-canonical-adapter.php`
   - `class-badaround-radar-view-model.php`
   - `class-badaround-radar-offline-bridge.php`
   - `class-badaround-radar-private-qa.php`
   - `class-badaround-radar-public-counts.php`
   - `class-badaround-radar-public-renderer.php`
2. Append precisely the **default-OFF conditional Radar QA block** from RC06 into the LIVE Core bootstrap immediately before `register_activation_hook`. Do not replace the full live file. The constant `BADAROUND_RADAR_PRIVATE_QA_ENABLED` is NOT to be set by default.
3. Insert precisely the **default-OFF admin-only preview block** from RC06 after the live `id="rad-reports"` element, preserving the existing explanation, CSS, JS, and map IDs. The constant `BADAROUND_RADAR_PUBLIC_COUNTS_PREVIEW_ENABLED` is NOT to be set by default.
4. Preserve all other files, filesystem ACL, uploads, database and maintenance settings. No private data handling or public alerts.

## Installation gates
- Confirm exact current live contents and anchor uniqueness immediately before editing. Abort on mismatch.
- Capture per-file rollback copies on the server separately from the downloaded site backups, and prove a restore path.
- Run PHP lint before activating feature. Execute WordPress smoke including `/mappa/`, Home, Radar, intake, admin moderation, and published Discovery.
- Then **only for authorized admin QA** enable both constants explicitly (prefer guarded server config). Confirm an unauthenticated user sees unchanged layout; admin sees counts. Disable immediately after QA pending public release review.
- Do not merge this candidate to default or run an FTPS auto-deploy from this branch; existing CI FTPS flows might deploy unexpected files.

## Stop conditions
404/503 unexpected, PHP fatal, missing class, permissions leak, broken map/list, mismatch in live anchors or lack of restore access => rollback and stop.

This file is a selective install contract; it has executed **zero** production writes.
