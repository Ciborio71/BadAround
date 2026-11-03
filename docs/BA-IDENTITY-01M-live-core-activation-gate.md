# BA-IDENTITY-01M — Live Core integration seam / runtime activation gate

Observed on badaround.it, 2026-11-03. This is a deployment specification, NOT an active patch.

## Canonical bootstrap
- `wp-content/plugins/badaround-core/badaround-core.php` version 0.18.0 ends with `register_activation_hook(...)` and `badaround_core_run()`; its 110 lines are the live, approved baseline.
- `includes/class-badaround-core.php` registers existing Publication, native intake, contributions, media, discovery, sentinels and moderation. Do not alter their registration order or behavior.
- Existing Radar has examples of strictly gated private QA code. Do not accidentally use the Radar flag for Identity.
- New `identity-dormant` directory contains 31 PHP modules. None are required or registered by the Core bootstrap.
- The Identity MySQL schema exists and is empty. Do NOT raise BadAround_Installer::SCHEMA_VERSION (1.9.0) for an independent Identity migration.

## Activation implementation contract
1. Never enable the current community REST adapter publicly without a verified passwordless enrollment/login transport, consent records and session-cookie issuing controller.
2. Provision a dedicated high-entropy root secret outside public webroot; derive purpose-specific keys, test rotation and prevent diagnostics from exposing raw key material.
3. Inject PDO using trusted WordPress DB context. Verify the actual WordPress table prefix and ten-table schema before any runtime registration; fail closed on missing dependency.
4. Cookie must be `__Host-ba_identity` Secure, HttpOnly, Path=/ and without Domain, SameSite=Lax; all write requests use validated origin plus session-bound CSRF.
5. Current community-rest-adapter deliberately rejects revision requests (503) and replies (400). Implement server-loaded canonical snapshot and parent comment lookup first, without changing existing publication service.
6. Implement moderation administration with WP actor + BadAround Identity verified binding, audit of decision/provenance and separate visibility projection. Never infer ownership from private email/name.
7. Add public UI only after end-to-end private QA; comments, revision requests and user profile must preserve mandatory name/surname/email privately and approved anonymous/alias publication rules.
8. Any live bootstrap patch must be a minimal verified diff against the current FTPS bytes, with before/after hash, backup, automatic rollback and no bulk plugin deploy.

## Release gates
- Structural database migration: PASS (10/10 present; 23 indexes; Core schema 1.9.0 unchanged; 84 events/84 reports preserved).
- Runtime implementation: NOT YET DONE. Feature activation: OFF. User beta/reset: NOT YET DONE.