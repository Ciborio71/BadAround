# RELEASE-QA-01B — Native MVP baseline reconciliation (2026-10-17)

Status: **CANDIDATE / NO MERGE / NO PRODUCTION DEPLOY**.

## Traceable native candidate

Git head of audit baseline: `b5003a661210b20cbc8d4d2f0c9cdb54dd99c615` (SF.9). This candidate branch adds ONLY audit documentation and an independent Native release QA workflow; no WordPress application changes.

Dependency chain:
- PR #25 SF.8 national territory — `a9f0a2da42214f05ebd29c75daea76021e35310d`
- PR #26 SF.8B locality — `694bf444be4e828c207d97ca8f1da41dc3fb6d43`
- PR #27 UX-01 address dedup — `6f3c4407e6f8290c6a010d8548c31a56adf30d50`
- PR #28 SF.10 compact territory registry — `130b3109d68a86eb4a7340c7629e8ed088aa3499`
- PR #29 SF.9 privacy QA — `b5003a661210b20cbc8d4d2f0c9cdb54dd99c615`

Each PR remains DRAFT and unmerged. Earlier dependencies also remain unmerged. This chain is NOT directly mergeable into main as an approved production release.

## Recorded staging state (read-only verified)

- `smart-address.js` and `inc/native-report.php` match the exact SF.10 source SHA (previously validated).
- Native report page uses `template-parts/native-report/shell`, and renders all 8,025 canonical territories as compact tuples.
- Native intake report #19 ↔ event #278 remains pending and excluded from public Discovery.
- Public page smoke: Home, Mappa, Torvaianica territory and published event #269 render.
- Public Discovery API includes only published records and explicit public fields.
- Images/F1.7 infrastructure verification is on HOLD pending hosting decision.

## Branch divergence / competing approaches

- Comparing `main` with SF.9 head: **diverged** (554 commits ahead, 21 behind, 180 changed files). Counts reflect all ancestry in the comparison, NOT necessarily 554 independent feature changes.
- Alternate PR #24 SF.8 originates from SF.7 and is **divergent** from PR #25: 8 commits ahead vs 11 on PR #25; touches Native PHP configuration and additional tests. Do NOT auto-merge/cherry-pick PR #24 into this chain. Review its semantic diff separately, then choose one implementation.
- No authorization to merge the dependency chain, close competing PRs, or deploy to production.

## Known CI debt — preserve historical fences

General `.github/workflows/php-lint.yml`, step `Test recovery golden-state invariants`, calls `grep` on retired `wordpress/themes/badaround-child/assets/js/report.js`. The Native-only child theme no longer ships this file, so the historical gate fails with exit 2 **after successful PHP syntax checks**. Another historical scope gate later in the same workflow compares against frozen old child-theme lists and may also fail for current intentionally revised UX. Do NOT silently bypass frozen protections across unrelated historical branches or recreate the retired `report.js`.

The new `RELEASE-QA-01B — Native Candidate Gate` gives this Native lineage a separately named QA workflow requiring retired legacy absence and canonical Native entrypoint, plus PHP/Node regressions and an application-code change fence. It does not claim that the old global workflow is green or replace final CI governance.

## Release decision and holds

**STAGING: GO** for further QA. **MAIN MERGE: STOP** until PR #24 decision, baseline/ancestry reconciliation, and canonical CI governance are approved. **PRODUCTION: STOP** pending those and all legal/hosting decisions, as applicable.

Additional manual E2E verification: map interactions, search/filter combinations, Sentinelle notification delivery, and mobile wizard after SF.10. No fixture modification and no social publishing under this audit.
