# Approximate area default radius: 100 metres — 2026-11-10

Baseline: 828558909d64316899bedb887a0dbd13344ed282.
Runtime candidate: 233f75ae380a4980b80fd6b55d3a27d770d0e537.
Final test/workflow candidate: e09df3a1910639dc40b7d4dbb07a764887137226.

The wizard configuration and model fallback now use 100 metres when approximate_area is selected without a retained radius. Existing behavior clears the radius when switching to exact_point; returning to approximate_area therefore restores 100 metres. Available choices remain 50, 100, 250, 500 and 1000 metres. User-selected radii, snapshot locks, strict server validation, circle geometry and public moderation/projection contracts are unchanged. No existing report/event data is migrated.

Updated existing model, serialized request/snapshot, DOM review and browser-fixture default assertions from 500 to 100 metres. Tests for explicitly selected 500 metres and the full radius enumeration remain unchanged.

Guarded production release: PASS (run 42820598129, job 129844864366). All 97 JavaScript tests passed, alongside PHP syntax/publication and guarded rollback checks. Both runtime files matched the pinned live baseline; backups and final remote SHA-256 readback passed. Frozen Core entrypoint preserved; no activation, data reset or URL changes.
https://github.com/Ciborio71/BadAround/actions/runs/42820598129

Browser fixture expectations were updated; no live provider visual certification is claimed behind existing access restrictions.

Initial preflight runs stopped before upload until legacy 500m expectations in the composed full-review label and serialized request assertions were updated to the authorized 100m default. No production rollback was required.
