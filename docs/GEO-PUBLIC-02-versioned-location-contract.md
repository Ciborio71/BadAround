# GEO-PUBLIC-02 — versioned private request and public geo contract

## Version and compatibility

F1.2 is extended additively: the root envelope stays `badaround-report/v1`.
The new optional extension has the explicit immutable version
`public-location-request-v1`; decision/projection use `public-location/v1`.
This follows the existing Social statement-version pattern, without changing
the meaning of any previous field. There is no schema migration or new table.

Private canonical location remains `location.exact_address`, `exact_lat`,
`exact_lng`. Device acquisition and subsequent manual pin movement are unchanged.
The final pair remains authoritative and is never generalized at intake.

## Four separate layers

| Layer | Storage / representation | Authority |
| --- | --- | --- |
| Private location | Existing `ba_reports` exact columns and canonical private JSON | Intake/final private pin |
| Publication request | `canonical.location.public_location_request` and `public_location_request_version` in `ba_reports.content_original` | Versioned user request only |
| Moderator decision | Reserved protected event meta `_ba_public_location_decision` | Future authorized moderation writer |
| Approved projection | Reserved protected event meta `_ba_public_geo` | Future B3 Publication Service writer only |

The two protected meta keys are not exposed through REST and no new write API
or moderation UI is added here. The contract constants define their shape;
GEO-PUBLIC-04/05 will implement the authorized writers. Existing public scalar
meta and their consumers retain their certified semantics in this phase.

## Request

Accepted request values: `exact_point`, `approximate_area`. Request and version
must both be present or both absent. Invalid type, unknown version, orphan
version and ambiguous legacy `point` are rejected. The generic normalizer and
legacy read paths never synthesize a request.

The newly served Native model explicitly serializes the default `exact_point`
and the extension version from its server configuration. These do not appear as
visible fields. Older client configurations/payloads remain compatible with
both fields absent, and old idempotent retries remain unchanged. No existing
report/event is updated or reinterpreted.

This default records a requested representation, not consent to expose exact
coordinates, textual address, civic number or any other private data. The final
visible choice and copy belong to GEO-PUBLIC-03.

## Decision

Fields: `version`, `mode`, `reason`, `policy_version`, `decided_at` (UTC RFC3339
`YYYY-MM-DDTHH:MM:SSZ`), `decided_by` (positive operator ID).

| Request | Allowed decisions |
| --- | --- |
| exact_point | exact_point, approximate_area, street, municipality, territory_only |
| approximate_area | approximate_area, municipality, territory_only |
| Legacy absent | legacy_generalized_point, approximate_area, street, municipality, territory_only |

Changing a new request to a less precise mode requires a nonempty reason. The
audit descriptor carries request, versions, decision, reduction, reason, time
and actor; it does not copy private coordinates/address. Recording through the
existing audit service is deferred with the authorized writer, not performed by
the pure contract validator.

## Projection and safety

Fields: `version`, explicit `mode`, optional complete `lat`/`lng` pair, optional
integer `radius_meters`. Unknown keys, including textual private address, are
not allowed. Territory-only forbids coordinates and a positive radius.
Municipality may remain coordinate-free, preserving the certified fallback.
Coordinate-bearing generalized modes require a radius of at least 100 m and
must not coincide with the private point. Numeric coordinate ranges are checked.

The side-effect-free proposal validator requires B3 context. Exact additionally
requires an exact request, explicit exact moderator decision, a complete private
pair, matching coordinates, and a positive server-side safety review. Neither
radius zero, coordinate equality nor decimal precision can infer exact mode.

`badaround_public_geo_exact_allowed` is the future server-only sensitive-place
hook; its default is false. No category automatically permits exact. A return
value from the validator is not a capability or publication operation.

## Activation boundary — important staging behavior

This phase does not implement GEO-PUBLIC-05. The real Publication Service
therefore **fails closed before preparation/validation** for any versioned new
request, decision or projection. It does not silently publish such a request
using the legacy area policy. Newly submitted versioned reports can be received
and remain pending; B2/B3 projection must wait for the later authorized writer.

All legacy preparation, minimum-radius checks, private-coordinate rejection,
textual-address/PII/plate protections, municipality rules, recovery guards and
published-save lifecycle remain in force. No exact private coordinate is copied
to public storage in this task, even with a valid proposal.

The existing legacy server-side generalization remains persisted and reused.
Tests verify a second preparation retains the same center and private pair.
The new approximate projection generator/persistence belongs to GEO-PUBLIC-05;
it must generate on authorized projection creation/update, never on public reads.

Discovery, maps, detail pages, Social and SEO are unchanged. They do not consume
the private request as a public projection. Social must continue to use approved
public data only. Exact point never authorizes civic/address/SEO disclosure.

## Verification

New tests: `geo-public-02-contract.php`, `geo-public-02-publication-gate.php`,
`geo-public-02-native.cjs`. CI also runs F1.2–F1.6, publication/privacy lifecycle,
Social, Smart Address, national reconciliation and controlled Chromium/WebKit
desktop/mobile private-pin regressions. Tests use isolated synthetic storage.
No staging report creation/publication is needed for contract certification.

Staging fixture contents, statuses and metadata hashes are captured before and
after deployment for #294, #290, #269, #260, #252, #298 and #300. No recovery,
normalization or migration is allowed. Deploy must compare actual remote bytes
against the certified baseline and upload a byte-exact rollback first.
