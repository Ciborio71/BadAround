# BA-RADAR-01C — Offline technical foundation

**Branch:** `feature/radar-01c-foundation` (no production deploy).

This first prototype defines a minimal `radar-analysis/v1` response using **synthetic fixtures exclusively**. It is not a registered WordPress endpoint and cannot inspect real reports.

Run: `php ops/radar-01c/radar-prototype-test.php`.

## Fail-closed admission rules
- Publication status must be `published`.
- Authoritative provenance `real_verified` is required; missing provenance means excluded. The live database currently lacks an authoritative Radar eligibility field, so this deliberately excludes real-data operation.
- ISO incident date, within selected window; never substitute publication date.
- Only upstream-approved coarse public cell identifiers with an explicit safety flag; the adapter that assigns them does **not exist yet**.
- Trusted episode IDs deduplicate; never merge by title or approximate distance alone.
- Cells with fewer than 3 eligible episodes are suppressed. **Three is a conservative prototype fixture threshold, not an approved privacy guarantee**; privacy review must set release thresholds, granularity and anti-differencing protections.
- No individual event locations, source IDs, coordinates, risk scores or alerts leave the aggregation response.
- Category and radius parameters are validated. Radius selection is a contract placeholder: actual distance filtering must be added only in a privacy-reviewed adapter.

## Not yet implemented / release blockers
1. Authoritative test/real provenance and QA exclusions (RAD-DATA-02).
2. Backfill/quality contract for event occurrence dates without inventing missing dates (RAD-DATA-01).
3. Trusted duplicate episode governance (RAD-DATA-03).
4. Privacy-reviewed mapping from public geometries to coarse cells, query differencing defenses, category/radius/temporal constraints, suppression and cached aggregates (RAD-DATA-04).
5. REST API handler, permissions, rate limiting, cache invalidation, map integration, user interface, monitoring and non-regression QA.

The current prototype must NOT be used for production heatmaps or public warnings.
