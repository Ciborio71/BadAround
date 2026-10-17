# NF-FINAL-01 — Authorized staging deployment evidence

Date: 2026-10-17. The owner explicitly authorized staging deployment after the local UI consolidation review. Production deployment remains excluded.

## Source identity

- Reviewed local commit: `81a0fab719eaca6dbab475cf95ae3d2a6d46b9e5`.
- GitHub application commit: `5b03f0728d8f1065b86725fb90ab59d94ea73803`.
- Application tree: `accb7f697db6e53f071bf91b8545ab0739a49598`, identical for both commits.
- Deployment commit: `8b73163ea2051c1476f1c8e8498bb1250824d36f`.
- Only seven child-theme application files uploaded. No core plugin deployment or data mutation.

## Validation and deployment

- [Native regression gate](https://github.com/Ciborio71/BadAround/actions/runs/39016154907): SUCCESS.
- [Staging workflow](https://github.com/Ciborio71/BadAround/actions/runs/39016184805): validation job SUCCESS; FTPS upload SUCCESS; final runner HTTP smoke FAILED with HTTP 403.
- The runner HTTP failure is not reported as a green workflow or as an application test failure. Authenticated WordPress source reads and served HTML checks were completed independently after upload.
- Rollback artifact prepared before upload: [nf-final-01-predeploy-rollback](https://github.com/Ciborio71/BadAround/actions/runs/39016184805/artifacts/12094682840).
- Seven predeploy WordPress source reads matched baseline `9a94937`; seven postdeploy source reads matched the approved application, ignoring terminal whitespace introduced by the source-reading transport.
- Served HTML contains the new accessible progress-status element and the minimal address-search UI.
- Existing filemtime asset versioning supplies fresh CSS/JS URLs after deployment.
- No new report, media upload, publication or moderation action was performed.

## Scope

DEPLOY: STAGING ONLY

PRODUCTION: UNCHANGED

F1.2 MODIFIED: NO

SMART ADDRESS RECONCILIATION MODIFIED: NO

MEDIA ENABLED: NO

DATABASE MUTATION: NO

MERGE: NO

STAGING DEPLOY: COMPLETE; seven active sources verified. Fresh real Google submission and physical-device QA were not repeated.
