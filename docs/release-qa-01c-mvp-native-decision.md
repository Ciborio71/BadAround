# RELEASE-QA-01C — MVP Native RC decision record

Date: 2026-10-17. Scope: isolated repository candidate and **read-only staging verification**.
Decision: **STAGING GO / MAIN MERGE HOLD / PRODUCTION HOLD**.

## Candidate identity and verified deployment

Release branch: `release/mvp-native-rc`, rooted at RELEASE-QA-01B reviewed head `c263f3993c6f5312c608129b6159cbad7ecf957d`. All changes introduced by RELEASE-QA-01C are governance/CI/docs, **no application files**.

Verified by exact read-only content comparisons against active staging WordPress:
- Child theme: `assets/js/native-report/smart-address.js`, `inc/native-report.php`, `assets/js/native-report/wizard.js`, `template-parts/native-report/shell.php`, `page-segnala-evento.php`.
- Core plugin: `includes/class-badaround-native-report-golden-path-service.php`, `includes/class-badaround-publication-service.php`, `includes/class-badaround-discovery-query.php`, `includes/class-badaround-territory-resolver.php`, `includes/class-badaround-report-persistence-service.php`.

These ten files match the release branch application snapshot exactly. This is an **important partial code parity check**, not exhaustive proof that every staging file matches the Git tree. GitHub Actions [38980092613](https://github.com/Ciborio71/BadAround/actions/runs/38980092613) passed the Native candidate gate before the final documentation-only commit.

## Decision on alternative SF.8 PR #24 versus adopted PR #25

Both PRs branch from SF.7 SHA `60aa8b292fbb0fd272a3ace1353d1bffa08994e6` but diverge (8 PR #24 commits; 11 PR #25 commits). They are **not** interchangeable or auto-mergeable.

- PR #24 carries alternative province UTS code transport derived from taxonomy term metadata, by adding a `code` property to each provincial registry object through `inc/native-report.php`; it modifies Smart Address separately and brings its own national tests.
- PR #25 uses the client-side region-scoped, auditable province-signature table, strictly anchored to canonical taxonomy parent matches. No additional provincial data property is required in PHP's registry.
- The adopted lineage additionally reconciles localities (PR #26), removes Piazza abbreviation duplication (#27), serializes 8,025 territories compactly (#28), and adds privacy QA (#29).
- Importing PR #24's PHP transport directly conflicts with SF.10's compact tuple schema and could enlarge browser configuration again. It requires its own security/performance/test review, and the active flow is already validated against stage.
- **Decision:** retain PR #25 → #29 lineage as candidate; **do not merge PR #24**. Keep its tests and regional coverage ideas available for separate comparison; do not close it automatically.

## CI governance — transparent transition

Historical `.github/workflows/php-lint.yml` is not green for Native-only branches. It checks a **retired** `wordpress/themes/badaround-child/assets/js/report.js` and historical freeze invariants. Historical F1/F1.2 test harnesses import removed WPForms mapper source; original F1.6 tests a withdrawn legacy fallback; LEG-RETIRE-01 demands byte identity to a pre-SF8 Smart Address file. These historical contracts are **not claimed PASS** for this release candidate.

Official **Native release candidate gate**: `.github/workflows/release-qa-01b-native.yml` is enabled for `release/mvp-native-rc`, while preserving the prior historical workflows unchanged. It checks:
- Native-only routing, explicit pending status and no public auto-projection;
- PHP syntax for all plugin/theme PHP files;
- current synthetic PHP intake, Golden Path, territory resolver, publication/discovery and Sentinelle tests;
- current JS Native wizard, Smart Address, SF8/SF8B/UX01/SF10 and SF9 privacy regressions;
- immutability of application tree relative to prior approved SF9 SHA.

**Remaining CI governance task:** Review and approve rewriting or retiring historical PHP Syntax and pre-Native test assertions on the shared release policy. Do not remove the old workflow or fake passing checks without explicit change review.

## Functional and operational holds

- User-confirmed browser check of Smart Address Torvaianica and Step 2→3; report #19/event #278 native persist succeeded, pending, hidden from Discovery. Does not yet prove canonical locality ID 5 was persisted for a NEW post-SF8B submission.
- Rendered live page smoke: Home, Mappa, Torvaianica and published event #269. Not a full functional map/browser/Sentinelle email delivery test.
- F1.7 hosting capability deferred by owner; do not certify 5×5MiB upload readiness.
- Legal/privacy policy decisions (full plates, exact public map pins, social) not yet adopted.
- Staging is not production. No `main` merge or production deploy authorized by this record.

## Release gates and next steps

1. Decide branch governance and historical CI replacement; inspect code-review/compatibility of nested dependencies #15–23 and #25–30 before main merge.
2. Execute user/browser QA for map interactions, filters, Sentinelle delivery, mobile wizard, and privacy negative cases.
3. Confirm provider hosting limits and formal legal policies before public launch.
4. Obtain explicit approval for any merge to main, production deployment, or changes to moderation/privacy defaults.

This record does not authorize closure of open PRs, schema migration, mutation of fixtures, or public publication.
