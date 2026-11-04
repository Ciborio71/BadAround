# BA-IDENTITY-02H — Live visual demo and moderation projection candidate

Date: 2026-11-04. Tracking: #82. These deliverables are separate from enabling real Identity registration.

## Visual demo now on badaround.it
- Created WordPress **private** page ID **387** `/identity-preview/` with fictional account data and five design sections. This private page could not be inspected unauthenticated because live WordPress still serves its maintenance screen.
- To give the project owner a usable visual link without weakening maintenance mode, deployed a **standalone static HTML** fixture at:
- `https://badaround.it/wp-content/plugins/badaround-core/identity-dormant/identity-demo-static-20261104.html`
- This is an **unlisted but not authenticated** URL. It contains **only fictitious names, events, and non-interactive visual controls**. No forms, scripts, cookies, API calls or real personal data. Meta robots: noindex,nofollow,noarchive. Anyone with the URL may view it.
- GitHub Actions run `41799772366` PASS: appended exactly one HTML file with checksum `26a27ca00af60e8dc01f94527831669798a0fb960eb5b72dc2112baad7ad1afc`; active WordPress Core and MySQL not modified.
- Remote `get_page_html` matched the `.ba-demo` component from the static URL, confirming the HTML is served rather than the maintenance screen.

## Approved comment projection candidate — CI only
- Added `public-comment-schema-candidate.php`: additive proposal for `ba_identity_public_comments` with numeric `comment_id`, unique private-moderation `intent_id`, event/parent relationships, display name, content, publish timestamp, visibility indexes.
- Added `test-public-comment-schema.php`: tests parent-reply reference, uniqueness of moderation-intent projection, no private identity columns in public reads, and hidden-content filtering.
- CI PHP/MySQL run `41800243153` both jobs PASS; MariaDB 10.3 `41800243181` PASS; safety gate `41800243239` PASS.
- **This 11th table is NOT in production. No DDL approved or executed.** Further server-approved moderation projection logic and retention workflow are required before any real comments are exposed.

## Postflight
- Production had 84 `ba_evento` and 84 private `ba_reports`, schema option `1.9.0` unchanged.
- REST discovery shows no public Identity login, registration, own activity or comments endpoints.

## Open release blockers
- Public Privacy Policy #3 still marks itself a prepublication legal draft; separate editorial/legal approval and retention matrix required.
- Secure server root key and reviewed private QA network/proxy configuration not provisioned. No real user data or live OTP delivery authorized.
- Reply handling, moderator approval/outbox projection and verified owner revisions not finished. No automatic publication or event reset.
- #82 remains OPEN, PR #80 remains DRAFT. Do not equate the visual demo with a production account system.