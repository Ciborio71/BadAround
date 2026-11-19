# BA-IDENTITY — production reconciliation checkpoint (2026-11-04)

Status: **pre-deploy / fail-closed**. This file documents verified facts only. No activation or production mutation is authorized by its presence.

## Verified sources

- Live WordPress `https://badaround.it`: plugin `wp-content/plugins/badaround-core/badaround-core.php` version 0.18.0 includes `BA-IDENTITY-01R` private runtime loader behind TWO constants, lines 102–111.
- The canonical `release/production-mvp-01` plugin entrypoint, at `wordpress/plugins/badaround-core/badaround-core.php`, **does not include** that loader.
- Identity modules were inspected on production via read-only WPVibe source reads; they are present in `wp-content/plugins/badaround-core/identity-dormant/`.
- Database: `ba_identities` and nine `ba_identity_*` tables exist. `ba_identity_recovery` exists.
- `BADAROUND_IDENTITY_RUNTIME_INTEGRATION_ENABLED` and `BADAROUND_IDENTITY_PRIVATE_QA_ENABLED` were not defined during the inspection.
- Registration, login, community, portal and activity routes also have independent activation guards; the private bootstrap checks canonical HTTPS origin, privacy version, IP allowlist, transactional mailer, InnoDB tables and root-secret requirements.
- `wordpress-privacy-notice.php` rejects a user-facing privacy document that still includes draft markers.
- Branch `feature/ba-identity-01c-dormant-20261103` contains test and deploy scripts but **is not a deployable baseline** of `release/production-mvp-01` (the compared branches diverge, with 203 changed files in the compare result). Do not merge blindly.

## Mandatory sequencing

1. Obtain an exact, complete, non-redacted backup of live plugin and child theme sources via approved hosting deployment mechanism, excluding credentials and user uploads. Check hashes and reconcile per-file against the release branch. WPVibe read_file source is masked and **not byte-exact**, so must NOT be used as source for a bulk copy.
2. Reconcile approved live changes in a new GitHub branch. Include private Identity modules and tests; preserve Home/Mappe/Radar and current Core behavior. Record a reviewable diff and automated QA results.
3. Review privacy notice and get explicit human/legal approval; configure root secret and server-only QA allowlist using approved secure environment management. Never commit secrets.
4. Run private QA of registration OTP, login/session/logout, identity ownership, event-edit and comment/moderation. Confirm failure cases, throttling, CSRF, consent and no unintended public routes.
5. Release public features only after QA passes and approval; smoke test anonymous and authenticated browsers, Mappe/Radar/Home and rollback.

## Safety boundaries

**Do not** enable the Identity flags or deploy the old branch while live/repository divergence remains. Do not create owner links for old events without an explicit recovery contract. Do not disable privacy guards or publish unmoderated contributions.

Tracking: https://github.com/Ciborio71/BadAround/issues/83
