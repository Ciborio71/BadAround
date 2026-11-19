# Inactive AI moderation credential setup — 2026-11-13

Production release: https://github.com/Ciborio71/BadAround/actions/runs/43449581757
Commit: 5a7e2043b2b98fee5ee7b46a3dd6688a6660a417

## Scope

Administrator-only WordPress Tools page at tools.php?page=badaround-ai-moderation. Stores the user-entered project key, moderator email and declared expiration date in a mode-0600 private JSON file outside the resolved WordPress webroot. The key field is never prefilled, and no suffix or key value is returned by administrative readiness diagnostics. Blank key on later saves preserves the credential. Versioned revision prevents stale forms from replacing a key; private lock and atomic file replacement serialize updates. Unsafe permissions, symlinks and malformed existing files fail closed.

POST requires manage_options, HTTPS and WordPress nonce. GET cannot mutate. No REST write endpoint, external API calls, email sends, cron jobs, intake hooks, publication or activation controls are installed. Stored expiration is metadata, not an OpenAI key validity check.

## Verification

- 32 isolated PHP filesystem/authorization cases passed, including private location/mode, stale form rejection, key rotation, non-disclosure, nonce/capability/HTTPS gates, malformed input, symlinks and temporary-file cleanup.
- Existing guarded-release/rollback tests passed. FTPS deploy and source hash readback passed for exactly mu-plugins/badaround-ai-moderation-config.php.
- Authenticated production readiness readback: credential_present=false; private_storage_available=true; moderation_enabled=false; external_calls_enabled=false. No real key was submitted or read by this work.
- WPVibe source readback confirmed the live administrator page registration.

## Handoff

User enters their saved key directly into the WordPress administrative password field, chooses the moderation recipient and records the expiration set in OpenAI. Next work: inspect boolean readiness after the user saves, agree publication-eligibility policy, then implement and test the AI recommendation and human-confirmation workflow. No truth verification or autonomous publication is provided by this setup module.
