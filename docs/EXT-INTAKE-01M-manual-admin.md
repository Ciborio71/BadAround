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

