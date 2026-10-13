# F1.15B-R.7A.1 — Child Theme Deploy & Rollback Gate

Status: **PREPARATION ONLY — NOT DEPLOYABLE**

This document is a staging release-control proposal. It neither certifies a backup nor grants authorization to deploy. Candidate remains in Draft PR #15.

## Immutable application source

- Repository: `Ciborio71/BadAround`
- Approved Smart Address candidate: `9c45f22eaa2a15d96cd225f077651bfdff344f6a`
- Frozen application baseline: `25b9ff5ad9d747b10cef7c2f3981fab15fbe4ed3`
- Immutable Git tree of baseline: `d62b387358c393c0918793a113cd950d11d883c1`
- Scope: `wordpress/themes/badaround-child/`, **never** `wordpress/plugins/badaround-core/`
- Existing `deploy-reconciled-staging.yml` is disallowed: it checks out `release/mvp-reconciled` and uploads both core and theme.
- Existing `deploy-staging.yml` is disallowed: its checkout is not SHA-pinned.
- Proposed `f1-15b-r7a1-theme-preflight.yml` checks the exact approved SHA and produces SHA256 evidence but has an unconditionally disabled deploy job.

## Mandatory read-only readiness evidence *before any new deploy authorization*

1. Record a timestamped inventory of the **currently installed** staging child theme in `/staging.badaround.it/wp-content/themes/badaround-child/`. Do not assume it equals a GitHub branch, staging release, Plesk backup theme, or old FTPS manifest.
2. In Plesk or a provider-supported secure file backup mechanism, verify that a restorable archive/copy of that actual directory can be made before release. Keep the backup outside the public webroot and outside the deployed theme path.
3. Obtain the checksum/size/file-count inventory of the live tree; compare it with the archived tree. Document who took the backup, its exact location (private operational record), creation time, retention, and restoration access.
4. Demonstrate **without affecting the running staging site** that the archive can be extracted, that paths and permissions are correct, and that the live child theme could be restored atomically. A GitHub commit alone is not a live backup.
5. Record current staging `badaround-core` version and plugin status, native/legacy route, WPForms + Geolocation state, Home and Mappa behavior and privacy no-public-exact-coordinate semantics.
6. Reconfirm that Google Cloud browser-key restrictions include staging referrer and the four required API restrictions. Never print the key or add key material to Actions logs/artifacts.
7. Verify that a designated deployment environment and credentials are staging-only. Avoid action/pipeline which might upload plugin core, delete unknown remote files, or touch production.
8. Document cache invalidation plan and compare active staging theme files to the source tree to identify differences that must be preserved. Resolve divergence before rollout.

## Proposed future release process (requires separate explicit approval)

- Run the SHA-pinned preflight workflow **only after** authorizing its use; collect successful test/manifest artifact.
- Approve separately a staging-theme-only deploy implementation after validating snapshot/rollback. It must use an immutable checkout, require staging environment approval and explicit release SHA, and have no deploy steps for core or database.
- Deploy only the verified child theme candidate; preserve a separately stored backup of the previously running live directory.
- Verify served page, visible version/asset hashes, no Google request attributable to Smart Address before Step 2 interaction, Google Places New address selection/Italy restriction, strict `ba_territorio` mapping, manual fallback and zero `google.maps.Map` in Step 2.
- Verify Home map, Mappa, WPForms Legacy autocomplete/marker, accessibility/mobile, zero inadvertent exposure of private address/coordinates, no real fixture mutation.
- **Stop-and-rollback trigger**: broken Native Step 2, missing controls, unexpected core change, wrong commit, Google loading regression, errors on Home/Mappa/WPForms, privacy regression, or anomalous API/network traffic.
- Roll back by restoring the independently verified previous **live staging theme backup** (not blindly deploying an old GitHub branch); verify hashes, functionality and caches.
- No merge to `main`, no production deployment, no Cloud changes, no real form submission without separate authorization.

## Current blocking evidence

- An exact **live** staging child-theme backup has **not** been independently verified.
- No safe GitHub Action currently deploys the exact Smart Address candidate as a child-theme-only staging artifact with an approved rollback.
- Therefore R.7B staging deploy **must remain STOP** until a separate evidence gate and explicit approval.
