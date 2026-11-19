# Wizard guidance and dedicated information page — 2026-11-10

Pinned production source baseline: dee8c17941f793579528599950f4cfdbc05d59b2.
Runtime candidate: f21a1972a992aa6549368c8a27ae4a8bef84d81a.

User requested removing the private-QA information disclosure and introductory moderation notice from the top of the report wizard, adapting the information into a new page linked in the footer.

Created a practical information page, “Segnalazioni, privacy e moderazione”, slug /segnalazioni-privacy-e-moderazione/ (WordPress page 398). It explains wizard fields, email verification, private/public data, geography, moderation, comments/reserved contact/revisions, images, test content and emergency boundaries. It links the existing Privacy Policy, terms and publication rules. It is operational guidance rather than a new legally approved Privacy Policy. The version-bound private QA notice and existing legal pages are preserved; no consent/version/security binding is changed. The adopted QA notice includes provisional legal bases, processor/transfer checks and proposed retention periods, which were not copied as settled platform policy.

Removed the report-template call to render_notice() and the introductory ba-native-notice paragraph. The existing final declarations help already explains moderation before publication; the success screen reiterates that submission is not publication. These final notices and all declaration requirements remain unchanged.

The footer link is conditional on the new page being published, preventing a draft/broken destination. The page uses the existing legal/information page stylesheet and skip-link support by adding its slug to the existing page classification. No global layout changes.

Source content is versioned in docs/wizard/segnalazioni-privacy-e-moderazione.html. WordPress content is managed through the native pages REST endpoint; no direct SQL or private option edits.

Guarded release: PASS (job 129808127554). All 93 JavaScript tests passed, alongside PHP syntax/publication and rollback checks. Five runtime files matched the pinned live baseline; backups and final remote SHA-256 readback passed. Both Identity functional CI jobs passed.
https://github.com/Ciborio71/BadAround/actions/runs/42809813670
Identity CI: https://github.com/Ciborio71/BadAround/actions/runs/42809813280
Page publication/readback: page 398 published at https://badaround.it/segnalazioni-privacy-e-moderazione/. Native REST readback verified the exact source content. Adopted private QA page 388 remains private and its content is byte-for-byte unchanged.

The authorized remote browser remains subject to maintenance/access restrictions, so no visitor visual certification is claimed. No reset, legal approval flag, private access, maintenance or indexing settings were changed.
