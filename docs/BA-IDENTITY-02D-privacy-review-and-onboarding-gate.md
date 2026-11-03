# BA-IDENTITY-02D — Privacy review required before real onboarding

Date: 2026-11-04. **Editorial/legal working paper — NOT an approved privacy policy; do not publish automatically.**

## Verified live defect
WordPress page #3, `/privacy-policy/`, status `publish`, modified 2026-10-24 16:26:03 UTC, includes prominent **BOZZA PRE-PUBBLICAZIONE**, as well as text saying that service providers, legal bases, retention, and rights **must be completed**. Its published status does not mean legal approval.

## Required decisions by owner / privacy counsel
1. Confirm controller name/contact and categories of data subjects; verify existing Max Advisor S.r.l.s. references and privacy@badaround.it.
2. Define lawful basis **for each purpose** (account administration; moderation; safety/abuse detection; public content projection; geolocation when chosen; Sentinelle notices). Distinguish mandatory notice acknowledgement from an optional consent. No blanket GDPR consent claim.
3. Describe Identity data: mandatory private first name, surname and email; optional public pseudonym/anonymous display; verified email lookup HMAC; hashed OTP; encrypted profile/consent metadata; secure session-digest and abuse-limiting buckets.
4. Describe public-facing Community comments, replies and revision requests: private author identity, moderation-before-publication, reported content, edit history and possible content removal.
5. Determine actual retention and deletion procedures for pending OTP/enrollments, verified accounts, sessions, quota records, pending/rejected comments, revisions, audit records and legal holds. Existing 12-month retention for rejected/nonpublished reports alone does not define the new Identity retention model.
6. Confirm providers, roles and cross-border transfers, including email transport (TurboSMTP), map/location technology, hosting/CDN/security providers and any AI integrations actually used; do not invent GDPR roles.
7. Specify access, correction, export, erasure, objection and complaint avenues and how contact requests are authenticated; take account of immutable audit records where justified.
8. Establish privacy-policy version release protocol: first approve text, publish it, then derive and independently review the version identifier before configuring `BADAROUND_IDENTITY_PRIVACY_APPROVED_VERSION`.

## Code safety guard deployed
- The dormant `wordpress-privacy-notice.php` now rejects draft markers such as `bozza pre-pubblicazione` even if WordPress reports the page as published and version metadata matches.
- GitHub Actions workflow 41738948003 PASS; public Core unchanged, Identity flags OFF, user onboarding OFF.
- Private portal shortcode additionally requires an independent explicit reviewed-version constant and private QA IP allowlist.

## QA prerequisites
- No real user PII or live OTP until the page is complete and approved.
- Technical tests with disposable identities/fake mail can continue in isolated CI.
- Secure root key/host provisioning and actual email delivery require independently authorized hosting operations and data handling safeguards.

**Go/no-go:** currently NO-GO for public and real-user beta. Do not mark issue #82 closed as complete on the basis of structural deployments.