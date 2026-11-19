# HOME-PROXIMITY-01 — production release 2026-11-06

User authorized an immediate contextual invitation to use geolocation and territorially relevant latest Home reports.

## Behavior
- First entry explains the benefit, then browser permission is requested only on explicit click. Already granted permission can be reused; no first-entry permission prompt is triggered automatically.
- Manual Places selection updates Home map and reports immediately. The existing search submit still navigates to /mappa/ with the selected area.
- Latest published reports are bucketed: whole published position within 5 km, municipality, province, region; at most nine, with unique event IDs and explicit per-card scope. Within each bucket chronological publication ordering is deterministic by date/ID.
- Approximate positions use only their public circle, not private coordinates. Territory-only events can be returned in territorial fallback.
- No national refill after a location is selected. Empty/failed/unknown territory results are explicit; initial unlocalized national cards are labelled as such.
- Canonical taxonomy resolution is exact and hierarchical, with an explicit official Roma Capitale normalization; ambiguous paths are not guessed.
- Precise browsing position is transient client memory and a POST body, never URL parameters, cookies, localStorage, sessionStorage, identities, reports, or application DB records. Response is private/no-store. Reverse geocoding uses the existing Google browser provider; its request language/importLibrary interface was checked against official Geocoder reference.
- GPS exploration never writes an intake location.

## Evidence
Candidate 6b4f4abd7750c12717c21fa87036c22088b81841; guarded release run 42263076191, job128047789067 PASS.
Six runtime files backed, source-checked against d2328c0d3e8dc4ff17ddc1a4da26dac24f15385e, swapped, read back; Core entrypoint frozen, no data/settings/reset/public activation changes.
New tests: nine PHP selection/validation/pagination cases and four JS permission/race/failure cases PASS locally and CI. Existing guarded-release tests 3 PASS.
Identity functional CI run42263075662: primitives and MySQL PASS; unrelated release jobs skipped.
Historical GEO-copy/map audit suites retain pre-existing baseline failures tied to old frozen source; baseline comparison reproduced failures. No claim these archived tests passed.

Live admin POST checks:
- Rome point + Lazio / official province name / Roma: count9, nearby+municipality, canonical IDs recognized.
- Remote point without territory: count0, no HTML or unrelated filler.
- Remote point with only Lazio context: count9, scope region explicitly.
- Invalid latitude91: HTTP400.

## Remaining acceptance and limits
Physical-device browser permission, Google key's reverse-geocoding entitlement, and full visual responsive acceptance remain to be observed with the authorized tester. Existing maintenance/IP protections remain.
If reverse geocoding fails, exact/public-area proximity can still work; the interface discloses that territory could not be recognized.
Current MVP feed paginates canonical public Discovery until nine nearby events or end of catalog. It avoids first-100 national truncation; a dedicated public spatial index should replace scans if publication volume grows substantially.
Images remain deferred to hosting migration, per user. No reset performed.
