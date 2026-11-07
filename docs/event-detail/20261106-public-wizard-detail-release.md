# EVENT-DETAIL-02 — production verification, 2026-11-06

## Authorized scope
Improve event detail using publishable wizard data, full-width H1, type/vehicle/territory-specific headings and explicit witness requests. Owner authorized the proposal and publication of address/civic data for his own test event395. No blanket authorization was applied to other historical reports.

## Implementation
- New BadAround_Event_Public_Details owns a signed, event-bound public snapshot; public templates read only this snapshot and existing public metadata. No report/private address reads occur while rendering a page.
- Candidate and approval endpoints /badaround-ops/v1/event-details/{id}: moderator + private-report + publish + edit capabilities; no-store. GET render=1 provides authenticated server-rendered template inspection without disabling maintenance.
- Structured public enums, temporal precision and the seeking-witnesses flag are projected after normal publication. Explicitly reviewed descriptive fields include vehicle distinguishing features, animal details, object description and damage description. Unreviewed private free text, contact details and full plates are excluded.
- A normal moderation metabox provides a separate nonce-protected approval action. Textual address publication requires explicit confirmation that the author designated it for publication, plus an existing valid exact-point public projection. Approximate locations cannot expose the exact address. No address is copied by default on publication.
- PublicationService owns the atomic detail write: InnoDB preflight, post/report row locks, source hash comparison, existing publication/GEO/privacy checks, audit and commit; rollback cleans caches. Original intake, geometry, slug, status and identity ownership are untouched.
- Explicit address authorization is scoped to the signed structured address. Existing prose/title/SEO private-address rejection remains in force; author identity/contact/full plate checks include new detail values.
- H1 uses full available viewport width with responsive side margins and natural wrapping; public color enriches vehicle H1/title without changing slug. CSS changes are event-scoped.
- Dynamic detail/location/vehicle/contribution headings. One existing contribution section is reused for witnesses, avoiding duplicated calls to action.
- Temporal ranges are retained; relative periods are labelled against submission date, not silently treated as today's event. Missing dates are explicit.

## Tests and guarded releases
Six targeted PHP suites PASS: new public details, SEO, GEO precision, GEO area, territory-only publication, Identity community review.
New assertions cover capabilities, stale source, no implicit address, explicit exact address authorization, no address escalation in prose, snapshot tampering, failed-commit rollback, immutable GEO/source, approximate-address denial, shared witness heading and color/frozen URL.
Existing guarded release tests also PASS.
Identity primitives/MySQL CI PASS at each release.

Release chain:
1. 7759688cdf7033e7b7798856e2630e636a492ea7 — run42295489631, job128159515671 PASS.
2. 12dad5863dae7864752ac1e0886a6789e8ebf0b5 — run42296069615, job128161511090 PASS.
3. 9c3e4c9ef65f794af342e6e5da8d09cfa1470a95 — run42296495058 PASS; matching Identity functional CI run42296494942 PASS.
FTPS baseline comparisons, private backups, mode-preserving swaps and readbacks PASS. Core entrypoint remains frozen. Final follow-up transfers only changed runtime files.

## Live acceptance evidence — event395 / report86
Reviewed snapshot applied via owning authenticated service, including this owner's explicitly requested address, seeking-witnesses flag and reviewed distinguishing feature.
Real server-rendered HTML:
- one H1: Honda SH, colore bianco — veicolo segnalato rubato a Roma;
- dynamic H2s for theft, location in Roma, Honda SH characteristics and a single witness/contribution section;
- approved address present;
- wizard vehicle type, damage status, place type and reviewed distinguishing feature present in Italian;
- missing date/time explicitly labelled;
- witness badge present; private snapshot/source hash absent from HTML.
Original report source hash unchanged; URL remains /eventi/veicolo-rubato-roma-honda-sh/ and frozen.
Live requests without address attestation or with a stale source are rejected HTTP409; prior approved data retained.
No unrelated historical backfill, reset, migration, media enablement or public opening.

## Remaining acceptance
HTML/service behavior is verified through authenticated server rendering. Full visual and responsive checks on real devices remain in the agreed final UI pass. Images remain deferred to the new hosting.
