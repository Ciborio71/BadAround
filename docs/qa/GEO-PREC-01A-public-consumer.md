# GEO-PREC-01A — pre-deploy public consumer compatibility

Base: `48bab2229c00fb28f01d54117dd1278074f10d69` (GEO-PREC-01).
This branch complements the governance candidate; it does not activate governance on staging. Staging remains rolled back. No deployment, moderation, publication, fixture writes or temporary endpoint is performed by this task.

## Cause and remediation

`template-parts/single-event-layout.php` previously entered the sealed-projection reader only when `_ba_public_location_decision` existed. Governed publications intentionally have no moderator geographic decision. Their exact projection therefore fell through to public mirrors with an empty mode and an approximate label.

The template now recognizes projection, proof or legacy decision metadata and consumes `BadAround_Public_Geo_Projection::public_view()` exclusively for geometry. Invalid/missing projections clear coordinates rather than reuse mirrors. A missing reader also fails closed.

The audit found a related discovery fallback: a retained proof with a missing projection and absent moderator decision could reuse legacy mirrors. The public discovery consumer now fences that case too. This is the only additional runtime file; no governance, moderation, Publication Service, contract or projection-verification logic changes.

If all public versioned metadata is missing, both consumers reuse the existing `BadAround_Publication_Service::uses_versioned_geo()` classifier. Its database result is a boolean existence check for request/version/governance keys (including unknown governance values), not report content or coordinates. It does not supply geometry or infer mode. SQL errors classify conservatively as versioned. Private report rows are never fetched by these consumer paths. Existing legacy records with no versioned signal retain their historical mirror rendering.

The sealed reader validates authority descriptor, source-bound signature and public mirrors. The writer rejects unknown envelope markers and source mismatches before publication; the consumer does not reopen private source data or regenerate a projection. Altered source proof/authority/seal or mirror mismatch returns no map. A later unauthorized edit of the private source is outside a public reader's authority; C.1/governance persistence guards remain unchanged.

## Consumer audit

| Consumer | Classification | Evidence / path |
|---|---|---|
| Event detail and its map configuration | NEEDS REMEDIATION → fixed | `template-parts/single-event-layout.php`; actual PHP rendering tests |
| Public discovery | NEEDS REMEDIATION → fixed | `class-badaround-discovery-query.php`; proof/missing-all fallback tests |
| Home / public map | SAFE | `functions.php` supplies `/badaround/v1/discovery`; `assets/js/map.js` consumes public geometry only; existing renderer tests |
| Territory | SAFE | `template-parts/territory-layout.php` uses published/moderation-published posts and public labels; no geographic decision/coordinate reader |
| Social | SAFE / no separate geographic adapter implemented in this repository | No Social geo consumer or moderator-decision gate found; current Social preference is private and excluded from public projection. Existing preference/privacy tests retained. No external delivery tested or changed. |
| SEO/meta/schema | SAFE | `functions.php` event/territory meta derives from public post content/taxonomy; no coordinate, private request or moderator-decision source; no geographic schema adapter found |

Repository-wide decision-reference audit is asserted by `tests/geo-prec-01a-boundary.cjs`. Core contract, writer, reader, moderation and audit references were reviewed as internal governance components and remain byte-identical to the base.

## Test provenance and limits

`tests/geo-prec-01a-consumers.php` executes the complete PHP detail template and actual sealed-projection reader; it also invokes the production discovery geo method. Public geometry was read from staging without writes on 2026-10-23:

| Protected report / event | Mode | Public radius |
|---|---|---|
| 73 / 352 | exact_point | 0 |
| 74 / 354 | approximate_area | 1000 |
| historical 303 | exact_point | 0 |
| historical 304 | approximate_area | 1000 |
| historical 269 | territory-only | no geometry |

These are isolated replays of public geometry, using locally signed test proofs (`wp_salt=test`), not exported production secrets. No claim is made that the undeployed candidate currently renders correctly on staging. Full historical HTML is compared byte-for-byte against the pre-remediation template. The same harness reproduces the original exact→approximate copy/empty-mode defect on that original template and passes with the patch.

Negative cases cover missing projection, unknown public authority, source-proof mismatch, mirror mismatch, injected moderator decision, missing all public metadata, unknown-envelope classification without projection, and unpublished status. Each must produce territory-only display with no map and null discovery geometry. Fixture/meta/audit state must remain unchanged during every render. Legacy territory and pre-versioned mirror output remain identical.

The dedicated CI also retains GEO-PREC authority/publication/moderation/persistence/SQL tests, legacy GEO lifecycle, v1/v2, C.1/C.2, media/no-media, Social, Smart Address and Native matrix/dependency tests. No staging HTTP access or secrets are required.

## Deferred copy (not changed)

`wordpress/themes/badaround-child/template-parts/native-report/step.php:36`:
“La tua scelta sarà valutata dalla moderazione, che potrà ridurre la precisione per tutelare la privacy. Indirizzo completo e numero civico non vengono pubblicati automaticamente.”

This is intentionally left for the separate authorized copy alignment task.
