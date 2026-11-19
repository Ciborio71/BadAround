# Email confirmation clarity and operation progress — 2026-11-10

Baseline: e314f322f2412be1e3127c46a43f100f28098d76.
Runtime candidate: 7a3afa77fab16d880651aa55d5fb3ab517b2567d.

The final wizard email dialog now explains why confirmation is needed and that the current compilation is retained. Delivery/status instructions precede the six-digit input. A concise email/spam helper is associated with that input. The existing masked email, numeric keyboard, OTP autofill, six-digit validation and leading-zero behavior are preserved.

One compact branded primary button confirms and submits; resend, email correction and return-to-report are secondary text actions. The code field has an appropriate bounded width. The dialog retains responsive width, bounded scroll height, native modal semantics, focus behavior and visible keyboard outlines.

Sending, resending and verifying display a spinner on the active action, a live status, disabled action buttons and a busy attribute on the action. Waiting for the user to enter an acknowledged code stops the spinner. The existing 60-second resend restriction is now visible as a countdown and disables resend until available; server limits and challenge/session semantics are unchanged. Error recovery removes the spinner and re-enables appropriate actions. Reduced motion disables spinner animation while retaining feedback text.

An optional local onPhase callback distinguishes access checking, email confirmation, verification completion, editing and cancellation. The wizard shows access-check progress before a dialog opens, suppresses misleading report-submission feedback while the code dialog awaits the user, and shows the existing report-submission spinner only after successful authorization. Snapshot creation and actual submission still follow successful matching-email verification. User cancellation returns to the retained compilation without an identity error; snapshot retry restrictions remain enforced. All REST routes, request payloads, privacy acknowledgements, authorization checks, code/session storage rules and backend security controls are unchanged.

The shared access script and feedback stylesheet enqueue versions increase to 1.1.0 so browsers receive the revised assets. No Core entrypoint modification.

Local tests: six access UI tests passed, covering slow delivery/verification, request duplication guard, code retry, cooldown expiry/resend progress, failed delivery, leading-zero code, session proof, profile completion and storage constraints. Added a wizard integration test for confirmation-phase progress and cancellation with retained values.

Guarded release: PASS (run 42839457553, job 129910448652). All 106 targeted JavaScript tests passed, alongside PHP portal/security/publication and release/rollback checks. Both Identity functional CI jobs passed (run 42839457747). Four runtime files matched the pinned live baseline; backups and final remote SHA-256 readback passed. Frozen Core entrypoint, private configuration, activation and data preserved; no reset or URL changes.

https://github.com/Ciborio71/BadAround/actions/runs/42839457553
https://github.com/Ciborio71/BadAround/actions/runs/42839457747

Tests use mocked identity requests and do not send real emails. Live visual confirmation remains subject to the existing authorized-browser/maintenance restriction. No access expansion or maintenance change was attempted.
