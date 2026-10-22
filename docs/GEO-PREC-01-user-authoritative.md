# GEO-PREC-01 — reporter public-location authority

Pre-deploy implementation only. No staging/production deployment, schema migration,
fixture write, moderation, publication, security change or NF-FINAL-01 reconciliation.
Base: `a4ef16aa067afc94a96cacd55a46a62d1248ed93` (certified Home baseline).
The held NF-FINAL-01 branch is not included.

## Exact cutover

The existing `content_original` JSON envelope gains the **server-owned**, top-level
`public_geo_governance: "user-public-location/v1"` signal. It is NOT a canonical
report field, Native payload field, database column, date threshold or event ID rule.
The existing v1/v2 schema versions cannot distinguish historical v2 fixtures from
new v2 submissions, so they are insufficient for this cutover.

- New Native v2: stamp the envelope in the initial atomic canonical reservation.
- New Native v1: stamp the first canonical persistence into a bare reservation.
  A bare v1 reservation has no existing canonical request to reinterpret.
- Existing nonempty snapshots, including an interrupted v2 reservation: preserve
  their original bytes on retry; never stamp or migrate them.
- Missing marker: legacy policy, including existing moderator decisions, audits,
  seals, public projections and territory-only behavior.
- Present unknown/null marker: fail closed, never fall back to legacy.
- Marked snapshot without a valid persisted explicit request: fail publication;
  do not infer a mode. Native's existing exact-point default is unchanged.
- Caller-provided envelope metadata cannot opt out or select a governance policy.
- WPForms/other non-Native intake remains legacy.

Canonical hash/version binding, conditional contract and Native payload are
unchanged. The existing envelope payload hash includes the new storage bytes.
The immutable canonical request remains the only source of mode.

## Moderation and publication

New reports display “Posizione pubblica richiesta” as read-only context. They have
no precision select, exact confirmation, reduction reason, independent geo form,
or editable public coordinate/radius inputs. Whole-report approval/rejection and
mandatory rejection reasons keep the existing B2/B3 lifecycle.

The geo-decision service rejects all new-report geo saves, including same-mode
and crafted POSTs. Ordinary public-field saves cannot write request/governance,
moderator decision, projection or compatibility coordinate mirrors.

B3 reads the primary report under its existing transaction/row lock. For a marked
report it derives a coordinate-free authority descriptor from the persisted
request. It does **not** create a moderator decision (including a fake automatic
moderator decision). Any pre-existing/injected decision on such a report blocks
publication. Conversely a legacy report cannot inject a user-authority descriptor
without its persisted governance marker.

New public proofs contain the signed coordinate-free authority descriptor rather
than depending on moderator decision metadata. Public readers verify the existing
HMAC and public mirrors without querying private reports. They retain legacy seal
verification for historical projections. No consumer, Social, SEO, taxonomy, map,
Native or Smart Address implementation is modified.

Exact publishes exact; approximate publishes approximate using the unchanged keyed
offset and system radii (150/300/750/1000/3000m). Legacy precision metadata cannot
silently turn the reporter's mode into municipality/territory-only. The existing
exact safety hook can veto publication as a whole; it cannot select another mode.
No user radius selector or map-circle/control change is introduced.

The existing media provenance fence runs before either geo policy and remains
inside the versioned transaction as well. No media acceptance/binding/fence rule
changes. This preserves its fail-closed error precedence for new marked reports.

## Audit and historical compatibility

Whole-report moderation still records approved/rejected through the existing
moderation audit. The canonical request remains in its immutable snapshot. New
publication audit records user request, authority policy and actual public mode;
it omits `moderator_decision`. No new `geo_location_decision_changed` entry is
written for a marked report. Historical audit rows and old projections are never
rewritten.

## Verification

Dedicated CI uses only synthetic isolated SQL/WordPress fixtures, MariaDB 10.11,
and actual service/admin/frontend sources. No staging credentials, HTTP access,
probe, real report/event, deployment step or security modification is involved.

New suites cover both modes, all four approve/reject cases, crafted geo saves,
request immutability, ordinary/published saves, unknown governance, unsupported
mode, private-source mismatch, injected decisions/proofs, atomic failure,
idempotency, radius preservation, SQL primary-report selection, read-only admin
markup, v1/v2 snapshot cutover and historical retries.

The two historical v1 differential tests allow **only** the explicitly asserted
server-envelope marker in new records. Canonical data, validator/normalizer,
HTTP result, event identity/count, retry/conflict and all other storage fields
remain compared exactly. Existing C.1/C.2 tests are unchanged. The shared SQLite
adapter recognizes the added JSON-presence path; CI additionally executes actual
MariaDB SQL. Legacy publication fixture now loads the real moderation service
instead of a constant-only substitute; its historical assertions are preserved.

The CI boundary asserts exactly seven runtime files changed; Native/Home,
conditional JSON and taxonomy/installer/consumer files must remain byte-identical.
It checks 33 profiles, 2,805 matrix entries and 554 conditional dependencies.
Protected staging fixtures #35–#71, residual #57 and related historical GEO fixtures
are outside all test execution and receive no writes.

Deployment remains separately gated. Deploying this patch requires the complete
seven-file governance change together; do not deploy NF-FINAL-01 as part of it.
