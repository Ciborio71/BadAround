# INTELLIGENCE-01L — WordPress runtime preflight (2026-10-29)

**Status: PARTIAL / not certified in WordPress runtime.**

## Read-only observations from connected production
- Site: `https://badaround.it`; WordPress 7.1.4, PHP 8.3.36.
- Plugin BadAround Core 0.18.0 is active.
- Existing canonical `/badaround/v1/discovery?per_page=100`: page 1/1, total 22, 22 returned.
- 15 reports expose a valid public geo mode; 5 reports supply an occurred_date value.
- At least two published report titles are explicitly recognizable as QA/synthetic; this is **not** a provenance audit.
- Discovery is a live published-report feed, not evidence of unique verified incidents, and may change between requests.

## Code state
- Passive WordPress integration lives on `feature/radar-01c-foundation`.
- Core main bootstrap includes `class-badaround-intelligence-read-model.php` in that branch only.
- No Core deployment, activation, endpoint or Radar data-source switch performed.
- Dedicated Intelligence synthetic tests: PASS on previous run.
- General PHP Syntax workflow, after branch-specific legacy test reconciliation: PASS run 40841644971.

## Release gates not yet satisfied
1. Targeted guarded deploy of passive plugin addition plus bootstrap, requiring exact deployed file baseline checks.
2. Real WordPress execution of the new `BadAround_Intelligence_Read_Model::snapshot` method, not merely the pre-existing Discovery endpoint.
3. Reconcile totals/categories with the live feed at a consistent snapshot; verify moderation withdrawals and cache invalidation.
4. Exclude QA and establish trusted episode identity before showing certified episode counts or anomaly alerts.
5. Privacy review for aggregate drilldown and cross-filter differencing.
6. Regression and recovery certification after any production deployment.

**Do not claim that an offline test proves execution on production WordPress.**
