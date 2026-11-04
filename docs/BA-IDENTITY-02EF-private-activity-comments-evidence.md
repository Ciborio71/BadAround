# BA-IDENTITY-02E/02F — Personal activity + moderation-only comments

Date: 2026-11-04. Follow-up issue: #82. Current release decision: NO-GO public.

## 02E — Private Area personale activity
- Added `mysql-private-activity.php`: protected read model selects at most the last 20 moderation requests belonging solely to the identity identified by a verified live session. Requires CSRF, same Origin, active verified identity. No raw email, content payload, moderator identity or other-user records are returned.
- Added `wordpress-activity-rest.php`: private-only `POST /identity/my-activity`, HTTP no-store, disabled by default. `identity-portal.js` renders only textContent (no HTML injection) into a hidden signed-in activity list.
- MySQL and MariaDB 10.3 disposable integration tests PASS, verifying isolation, CSRF, cross-origin and revoked session/identity denial.
- Scoped dormant deployment run 41743231995 PASS, verified three existing file replacements and two new modules, preserving Core and database.

## 02F — Comment form pending moderation
- Added `wordpress-comment-form.php` shortcode `badaround_identity_comment_form`, available ONLY in explicitly authorized private QA with reviewed Privacy Policy, exact canonical published `ba_evento` and moderation status `published`.
- Added `identity-comment-form.js`: same-origin/CSRF session recovery and private intent submission to existing `/community-comments`; requires login, anonymous mode, 3–2500 characters, shows only pending moderation receipt, never directly publishes.
- No replies or pseudonym public projection yet: both require trusted server-side parent and alias read models, so neither is misrepresented as complete.
- Initial 02F test caught a string assertion mismatch and blocked deployment as intended; the test was corrected and rerun.
- Scoped dormant deployment run 41743862432 PASS. Byte checks confirm new files and unchanged Core/database; all new public feature flags remain OFF.

## Live postflight
- WPVibe lists 52 files in `identity-dormant/` (PHP/JS/manifests/backup).
- 84 ba_evento records and 84 private ba_reports remain intact.
- Public WordPress REST index exposes no Identity login, register, Community comments, or personal activity endpoints.
- The Identity database already has ten validated tables; the Core database schema remains 1.9.0.

## Blocking before real-user activation
- Live published Privacy Policy #3 remains an explicitly marked prepublication legal draft. The fail-closed gate denies it even when metadata matches. Legal/editorial review and replacement required.
- Dedicated high-entropy server-root key and reviewed proxy/IP allowlist are not provisioned; real TurboSMTP OTP delivery and browser QA remain outstanding.
- Reply threads, pseudonyms, verified report ownership, revision projection, and moderator workflow must be completed/accepted before feature parity with the approved specification.
- Do not merge PR #80, enable public routes, or reset test events without release authorization.