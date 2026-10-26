# EXT INTAKE 01M Manual screenshot intake

Scope: administrator assisted capture from chat, not automated Facebook crawling.

An authenticated administrator sends a reviewed extraction to POST /badaround/v1/external-reports. Input is validated by the external source-specific contract (badaround-report/external-v1). Native v1/v2 remain unchanged and cannot accept this profile.

The service stores original text, source URL and reference, capture hash, import actor/time, private address/plate and uncertainty about date ranges in the reserved report. No author identity, native consent flag, consent timestamp or IP is synthesized. Only public candidate text and a masked plate enter the event projection.

Creation and adoption commit a real report, the bidirectional primary link, existing canonical taxonomy/territory, audit and a pending event together. Named source lock, unique identity and full identity/fingerprint comparison make retries idempotent and reject changed sources. Adoption additionally requires an unlinked draft/pending event, an exact event revision and matching private provenance note. An already approved orphan can be adopted only with an explicit adoption_reset_approval acknowledgement: its prior approval is retained in audit, the event returns to new/pending, and fresh moderation is required. Published or linked events cannot use this recovery. Reports and canonical snapshots are immutable.

First version uses explicit territory_only GEO, no remote downloads and no public originals or attached images. Social is not requested. The existing Publication Service remains the only publisher. An independent, nonce-protected editorial review records the documented reuse basis and binds the decision to source and public content hashes. Editing content invalidates that review. B2 approval is still required. The new module never publishes.

Private source details and review controls appear in a dedicated metabox. The existing private report box shows the reserved text, address and plate. Review and source information are restricted to administrative private-report capabilities.

The date expression “night between two days” retains both dates in the external snapshot and editor text, without inventing hours or a single exact timestamp. The public time precision is approximate; unknown dates remain unknown.

No DB migration, theme change, native declarations, schema loosening, fake form entry or manual SQL patch is involved.

## Validation

Dedicated CI runs PHP syntax, ten Native/GEO/privacy regression suites and an isolated WordPress 7.1.3 / MariaDB golden path. Assertions cover invalid and anonymous input, source identity, privacy segregation, null consents, retry/alias idempotency, changed source rejection, stale adoption, transactional rollback on audit failure, public-review invalidation and normal B2/B3 publication of synthetic data only.

Real external fixtures remain pending. Production publication of #374 is not performed by this task.

## Deployment

Initial baseline: release/production-mvp-01 at 84ae8fc6d172c5409e4b3a93f522ca2e41c32074. Recovery update baseline: the verified four-file deployment at 1d5e4bb9c1420022416867ea1a71f3e8dd2ada1f.

Only four Core files may be deployed. The job verifies the remote baseline and passive Core dependencies, saves exact rollback copies before upload, uses verified TLS/host identity, stages each file via rename, activates hooks last and verifies remote bytes. A failed transfer triggers reverse-order byte-exact rollback. The theme, maintenance mode and fixtures #372 and #252 are outside the mutation scope.

This manual implementation is a subset of the broader External Intake contract. Automatic source polling, source updates, public media derivatives, bulk import and retention deletion jobs remain outside this release.


## GEO and photograph enrichment

POST /badaround/v1/external-reports/{event}/enrich is a private administrative path for an existing external report. It requires an exact event revision, explicit reopening acknowledgement, an independently sourced geographic anchor and its uncertainty note, the actual capture matching the immutable provenance hash, and validated integer crop/masking rectangles. No remote download or AI image reconstruction occurs. The source capture is retained byte-exact outside the web root. GD crops the supplied photograph and applies an opaque mask; only that re-encoded, metadata-free derivative enters the existing private Media Repository pipeline. Source URLs, author identity and full plates never enter public filenames.

Enrichment first withdraws the event through the Publication Service into pending/new, clears the previous public geographic projection under its existing write guard, and invalidates prior review. All database changes and audit commit together, with private-file cleanup on rollback. It preserves the event/report IDs, original source snapshot, URL identity and taxonomy. A retry returns the same media; a different enrichment requires future versioned review rather than overwriting the first one.

The report's private coordinate columns hold an explicitly documented approximate geocoded anchor, not a verified incident point. The original contract snapshot remains immutable; the enrichment manifest records source, actor, time, coordinate semantics, transform rectangles and hashes. A 1000 m approximate public area is generated and sealed only by normal B3 publication. No centroid fallback is used.

The private metabox shows the candidate image and original screenshot behind capability/nonce checks. A fresh, explicit editorial assessment of photo reuse and redaction is mandatory in addition to source review. No rights or original-reporter consents are invented. Existing B2 approval, media privacy controls and Publication Service remain required. Native media fences remain unchanged.


### Screenshot-only source with an unavailable post URL

An administrator may supply a Facebook capture without a permalink. In that case source_url is empty, source_url_status is not_supplied, source_access is supplied_capture_only, and source_reference is capture:sha256:<exact-primary-capture-hash>. This identifies the supplied evidence, not a verified Facebook post or author. No platform URL is fabricated; the admin UI makes the limitation explicit. A later permalink requires a versioned source update, not mutation of the original snapshot. Byte-identical captures deduplicate by hash; resized/cropped variants still need human duplicate review.

The optional supporting_evidence_sha256 binds a second screenshot (e.g. the text) to the immutable provenance. Enrichment then requires and privately preserves both original files. The primary photo capture may be up to 5 MiB; the supporting screenshot up to 2 MiB. Originals never enter the public media library. Existing captures without the optional supporting evidence retain their previous retry fingerprints.
