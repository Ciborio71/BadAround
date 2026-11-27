# Classic admin publication and SEO finalization repair

## Incident and diagnosis

Report 93 / event 445 failed with `ba_publication_geo_state_failed` after approval. Live state was pending/approved, no public geometry, source unchanged. The first controlled B3 published the event, then finalized title/slug after signing public details. That second `wp_update_post` lacked the event-bound B3 authorization. Classic `admin-post` does not carry the public-fields editor nonce and is not REST, so real B2 preservation filters downgraded the event to pending. The final transaction assertion failed and rolled back correctly.

## Change

Keep an event-bound `badaround_allow_event_publish` filter only around the SEO finalization call, inside the existing validated B3 transaction. Remove in `finally`; other event IDs remain unauthorized. Media fence, source/projection checks, capability gates, signed public details, frozen URL validation, audit and rollback remain active.

Remove the redundant first-name helper in the wizard. Dynamic identity/privacy explanation and email helper retained.

Add maintenance-only POST `/badaround-ops/v1/publication-retry/{id}` for administrator recovery through the owning Publication Service. Requires manage_options, moderate, publish, private-report and edit-post capabilities. Validates maintenance state, CPT, approved or already-published state, expected primary report ID and SHA-256 of unchanged private source. Preview is read-only; execution requires literal `apply:true`. Does not approve reports or bypass any service validator. No raw SQL mutation.

## Verification

- Baseline runtime reproduces the exact state_failed error under real classic B2 moderation filters, for exact and approximate geography; rollback verified.
- Fixed runtime publishes both modes, retains valid signed geography/frozen SEO, removes the scoped authorization after completion, preserves rollback on commit failure and still blocks direct B2 publication.
- Recovery route covers capability denial, maintenance-only operation, changed report/source rejection, no-write preview, unapproved-state rejection, controlled publication and idempotent repeat.
- Existing SEO, GEO, structured-data, consent and wizard regression checks passed; 36 wizard/social DOM tests and five responsive fixture widths passed.
- Full Identity/MySQL: https://github.com/Ciborio71/BadAround/actions/runs/43311596898 PASS (same runtime; follow-up commits correct test fixture dependencies/IDs only).
- Guarded two-file release: https://github.com/Ciborio71/BadAround/actions/runs/43311965049 PASS. Source baseline c7be1af344a524bd07a2654bc05a10ece837b79e; released runtime 1eb36e6501ed57ef1b25cdac1842c7ff5536d145. Verified backup outside web root, TLS, atomic replacement and SHA readback.

## Live recovery outcome

Administrator preview confirmed pending/approved for event 445 and primary report 93. Explicit retry returned publish/published:
https://badaround.it/eventi/cane-smarrito-roma/

Independent reads confirmed publish/published, active SEO v2.2 with frozen canonical URL, one event_published audit, one public_details_approved audit and approximate_area with 100m public radius. Private canonical source SHA unchanged. No new report, manual state override, reset, maintenance/IP change or duplicate publication created. The direct public browser remains maintenance-restricted; public projection and backend state were verified through authenticated tools.
