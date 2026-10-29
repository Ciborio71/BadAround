# RADAR / Intelligence — Release preflight (non-deploy)
Date: 2026-10-29

## Approved source
Production badaround.it is the **only approved behavioral baseline**. Current release branch is an incomplete representation of live because production Publication Service and external intake logic track the PR #37 lineage, not the old release branch.

## Blocking checks
- Obtain trusted filesystem manifest SHA-256 for ALL live theme and Core files; compare with GitHub candidates.
- Reconstruct live composite baseline without replacing or reverting production changes; reconcile external intake Publication Service and all dependencies.
- Review the stacked draft PRs #40, #42, #43, #44, #45, #46, #47 as a whole. They are standalone, dormant or synthetic; successful isolated tests do NOT establish operational integration.
- Do not treat descriptive report growth as criminal risk, statistically meaningful anomalies, or distinct incidents.
- Add WordPress E2E QA for Home, map, report wizard, moderation, publication, GEO privacy, sentinels and Radar. Verify no new public data disclosure, responsive regressions, or performance impact.
- Deploy only exact approved commit with tested backup and rollback, after user approval.

## Additional read-only matches to release/production-mvp-01
- class-badaround-moderation-service.php: 134 lines
- class-badaround-discovery-contract.php: 139 lines
- class-badaround-public-geo-contract.php: 217 lines
- class-badaround-report-validator.php: 548 lines

This integration branch contains no active integration. It is a safety checkpoint, **not** a deployable candidate.
