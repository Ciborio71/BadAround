# RADAR-DATA-04 — Privacy aggregation gate, isolated prototype

**Status:** synthetic-only, NOT privacy-certified; no production endpoint or deploy.

This facade is deliberately fail-closed. It accepts only a fixed registered-area identifier and rejects arbitrary latitude/longitude, date, radius or category queries that enable direct differencing. The 30-day period is fixed; internal candidate cohorts require at least five trusted episodes. Neither exact counts nor cells, event IDs, or positions are returned. The number five is a **test parameter only** and must not be treated as a privacy guarantee.

The facade does not derive areas from geometry. The future adapter must verify that each public geometry can be safely assigned to an aggregate region without reducing the disclosure precision of an approximate event. Current `area_assignment_audited` and `geometry_precision_verified` flags are only synthetic test assertions and **must never be populated from client input**.

## Release blockers
- Build an authoritative server-side registry of stable, nonoverlapping territorial areas; validate adjacency/differencing across multiple areas and successive snapshots.
- Review whether even a presence/absence threshold permits inference (membership and differencing attacks).
- Specify snapshot cadence, delay, cache invalidation, publication withdrawal, and cumulative-query protections.
- Validate exclusion of test events, canonical episode identity, date semantics and published-only ingestion.
- Define aggregated display granularity, minimum cohort after filters, suppression policy and all UX fallback states.
- Perform adversarial privacy review and quantified performance testing with fixtures.
- No heatmap or preventive alert is enabled by this prototype.

Run: `php ops/radar-01c/radar-privacy-gate-test.php`.
