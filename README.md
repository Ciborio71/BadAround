# BadAround

BadAround is a WordPress-based platform for local event reporting, territorial alerts, community sentinels, moderation, maps, social distribution and geolocated advertising.

## Repository policy

- `main` is the stable branch.
- New work is developed on dedicated branches.
- Significant changes are reviewed before merge.
- Application logic belongs primarily in the custom plugin `badaround-core`.
- Presentation-specific customizations belong in the child theme.
- GitHub is the canonical source for deployable code and approved frontend customizations.

## Staging sync and deploy rule

- No approved change may remain only on staging.
- Before any subsequent deploy, every approved staging change that affects code, templates, CSS, JavaScript, theme assets, or other deployable frontend behavior must be synchronized back into the repository.
- A staging-only customization is considered temporary until it has been committed to GitHub.
- Deploys must originate from the current canonical repository baseline and must not overwrite approved staging behavior with older repository files.
- Before deployment, compare the intended release with the last approved staging state whenever staging-only work may have occurred.
- After deployment, run a focused regression check on the areas touched by the release before declaring the deployment complete.
- If a regression is detected, stop further deploys affecting the same area until the canonical baseline has been reconciled.

## Status

Initial repository bootstrap in progress.
