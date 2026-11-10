# Public event detail policy — 10 November 2026

Authorized change: publish the reporter-supplied full address (including civic number) for exact-point events, publish the entered vehicle plate without masking, and display a responsibility/rectification box on every event detail.

## Contract

- The wizard explains both modes before entry, alongside the map, and in the final review. Exact point exposes the entered full address after moderation; approximate area does not expose that address or civic number.
- The plate field is explicitly labelled for publication. Partial plates retain only supplied characters; missing characters are never invented.
- The mandatory final declaration explicitly describes publication of address/plate and reporter responsibility. Its acknowledgement records the additive `consents.public_details_version=public-details-v1` marker in canonical private storage, alongside the existing declarations. The marker is omitted until all relevant declarations are true.
- Backend validates the marker and persists it through v2 normalization. It remains optional for compatibility with previous clients/reports; absence never grants expanded publication.
- B3 generates a signed public snapshot inside the existing transaction, validates it, audits it and commits it with publication. Any failure rolls back GEO, public details and publish status. Rendering reads only this public snapshot, never the private report.
- Plate authorization applies only to the signed dedicated field. Arbitrary copies in body text, legacy metadata or SEO remain rejected; identity, email and phone protections remain active.
- Full address requires the reporter's recorded exact-point request plus the effective verified exact-point public projection. An approximate projection suppresses any previously approved precise address immediately.
- Previous reports are not retroactively assigned a declaration or unmasked. Existing explicit address review remains available through the existing capability/nonce/attestation controls.
- Every detail has a rounded white responsibility box. Its rectification link opens email to the platform's published content contact, with event ID and canonical link prefilled; no message is sent by the application or deployment.
- Operational information page 398 is aligned with this policy; the adopted private QA notice and final legal texts remain untouched. Legal wording still requires the planned final legal review before public opening.

## Scope and release

No DB schema migration, report data migration, event reset, slug change, index-opening change, Identity authorization change, email send or media enablement.

Runtime commit: 926b089e08736864e0aaad6d3037df162d54ec8c.
Baseline: c1e2fa2cb6824b16ffa2aba189ba5eee5d672e0d.
Guarded release: https://github.com/Ciborio71/BadAround/actions/runs/42916329075
Functional Identity CI: https://github.com/Ciborio71/BadAround/actions/runs/42916328971

## Verification

Automated checks include: declared exact address/plate, approximate address suppression with plate retained, legacy absence of disclosure, tampered snapshot rejection, controlled-field restriction, transaction rollback, canonical policy validation/persistence, wizard declaration/review, existing GEO cases, event SEO/headings/gallery, OTP/access regression, Identity primitives and ephemeral MySQL.

Live rendering and file-readback evidence will be appended after deployment. Real-browser visual and real-mailbox E2E certification are not claimed by these automated checks.

Deployment completed successfully: all 10 runtime files were backed up, compared against the pinned baseline, replaced and read back with matching SHA-256 hashes. Release result PASS; legacy URLs, activation and data-reset flags unchanged.

- JavaScript regression suite: 107 passed, 0 failed.
- V2 canonical/validation/persistence suite: 3,411 assertions, 33 oracle cases, 2,805 matrix assignments; PASS.
- New publication-policy and existing public-details suites: PASS.
- Identity primitives and ephemeral MySQL jobs: PASS.
- Live authenticated event-body render for event 395: responsibility heading and event-specific rectification link present; no retroactive plate exposure. This is server rendering evidence, not browser visual certification.
- Page 398: single-match native content edit succeeded; fresh content search confirmed the new exact/approximate address and unmasked-plate instructions.
- Final public opening and real-browser/real-mailbox end-to-end certification remain outside this release.
