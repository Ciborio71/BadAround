# Public and reserved interaction flow — 7 November 2026

The witness card provides two explicit destinations: public discussion via Identity and a reserved information form using the existing email-confirmed contribution service. The legacy form UI no longer duplicates public comment submission: it explicitly sends visibility_requested=reserved and public_identity_mode=public_anonymous. Existing endpoint validation, moderation, media policy, approved historical public projections and original records remain unchanged.

Reserved does not mean staff-only. The existing moderation service can notify the event author and grant temporary reserved-content access. Copy explicitly describes that possible sharing and no community publication. No new recipient or permission is introduced.

The Identity composer preserves drafts when authentication is needed and offers explicit access in-page using the existing BadAroundAccess widget. Successful verification returns focus to the comment submit without automatically submitting a comment. Network/server/rate-limit failures do not falsely request login. Pending and result states are visible; replies, alias choice and revisions retain existing authorization/CSRF gates.

Conditional pseudonym, cancel-reply and access elements respect hidden despite theme display rules. Choice cards use two columns on desktop and one below720px; controls wrap without forced full width for the comment submit.

Verification: 17 local JS cases PASS (public/reserved form behaviour, inline authentication draft retention, network failure distinction, replies/alias, Home and detail map regressions). Three guarded-release tests PASS. Release also runs contribution E1/E2/E3/E5, public-details/GEO/community PHP suites and lint. Updated E5 assertions reflect the approved two-destination UI, preserving reserved-publication denial.

Live visual mobile QA remains blocked: the remote browser sees the intentional maintenance page at the actual event URL. No allowlist, maintenance, runtime gate or network path was changed to bypass it. No comment/contribution submitted and no email sent during QA.

Release sequence: initial five-file run42412812778 PASS; reserved-sharing copy run42412949225 PASS; final presenter wording run42413088801 PASS. Final source5a6e5f6298b6748c508fa4fb243dfbe6c6a7936f. Original moderation wording “soltanto dopo approvazione” is retained for the existing isolated Identity contract test.

Live authenticated event395 render confirms two destination cards, public anchor to #commenti, reserved hidden request field and disclosure of possible sharing with the event author. There is no legacy public visibility choice in the reserved UI. H1 remains unchanged. The E2 reserved-cannot-publish test, E1/E3/E5 contribution suites, public-detail/GEO/community suites and all17 JS cases pass. Computed-style fixture confirms hidden pseudonym, cancel-reply and access panel have display:none despite theme styles.

Final Identity functional CI run42413088774 PASS (primitives and MySQL). Remaining certification: real protected browser/mobile visual QA and future hosting/media/performance launch gates; none are claimed complete by these source/DOM/server-render checks.
