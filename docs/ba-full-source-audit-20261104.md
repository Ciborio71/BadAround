# BA-IDENTITY full production source audit — 2026-11-04

## Verified read-only GitHub Actions run

Workflow run: https://github.com/Ciborio71/BadAround/actions/runs/41851626128

Source custody comparison covered two deployable scopes:
- `wordpress/plugins/badaround-core/` — 124 tracked eligible files, **122 byte-identical**, 2 differences, 0 missing, 19 additional live source files.
- `wordpress/themes/badaround-child/` — 56 tracked eligible files, **53 byte-identical**, 3 differences, 0 missing, 0 additional live source files.

Total **175 of 180 matched**. This measures deployable file parity, not the installed WP options, database state, or live functionality.

## Five changed files online

- `plugins/badaround-core/includes/class-badaround-core.php`
- `plugins/badaround-core/includes/class-badaround-publication-service.php`
- `themes/badaround-child/assets/css/home.css`
- `themes/badaround-child/front-page.php`
- `themes/badaround-child/page-radar-demo.php`

These **must be reconciled from byte-exact live source before merging or deploying**. Do not overwrite with the repository's older counterparts.

## 19 extra live files

Identity: `DEPLOY-MANIFEST.txt`, `LOGIN-ADDENDUM.txt`, `REGISTRATION-ADDENDUM.txt`, `identity-demo-static-20261104.html`.

Core: `class-badaround-external-report-intake.php`, `class-badaround-intelligence-data-quality.php`, `class-badaround-intelligence-peak-candidates.php`, `class-badaround-intelligence-trends.php`, `class-badaround-publication-service-OLD.php`, `class-badaround-radar-canonical-adapter.php`, `class-badaround-radar-offline-bridge.php`, `class-badaround-radar-private-qa.php`, `class-badaround-radar-public-counts.php`, `class-badaround-radar-public-renderer.php`, `class-badaround-radar-quality-registry.php`, `class-badaround-radar-review-admin.php`, `class-badaround-radar-view-model.php`, `class-badaround-radar-window-counts.php`, `class-badaround-radar-window-renderer.php`.

Classify these extras before copying; the backup marked `-OLD` and dormant manifests are not runtime release code. Never replace a live source file blindly.

## Safety

- Live FTPS tool uses read-only commands; production file changes = zero.
- Release PR #84 stays draft until all source divergence is resolved and app-level QA passes.
- Private QA and privacy approvals remain independent release gates.
- This audit is not public activation of Identity.
