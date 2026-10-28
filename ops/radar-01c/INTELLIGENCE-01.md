# INTELLIGENCE-01 — Shared aggregation foundation (v0.1)

**Status:** isolated PHP prototype, synthetic-only tests. Not production deployed, not a REST API, not a validated public statistic.

## Contract

The input is a normalized, **server-trusted snapshot** after publication; it is not a browser payload or a raw report. Each record must carry:
- publication_status = published
- source_quality = verified_real, assigned by a future trusted provenance adapter
- canonical_episode_id, from a future verified episode identity process
- category, phenomenon, region, province, municipality from canonical taxonomies
- occurred_date, optional and never substituted with the post/publication date

Outputs: totals of canonical episodes, dated/undated split, category/phenomenon counts, hierarchical geographic totals, monthly counts, and excluded-record diagnostics. All aggregates are deterministic for the supplied fixture and snapshot date.

## Explicit non-goals / release gates

- No H3 cells, heatmap, public data exposure, alerts, risk scores or anomaly thresholds.
- No existing production source currently guarantees source_quality, canonical_episode_id or the normalized geography inputs.
- The prototype must **not** infer certified episodes from public Discovery IDs; one report is not necessarily one unique incident.
- Real-world duplicate decisions can be ambiguous; missing trusted identity is excluded rather than counted as a separate episode.
- Real geographic aggregation must respect GEO-PUBLIC approved precision and suppression policy, including differencing across filters.
- A future adapter needs a trustworthy full snapshot, validation of pagination/withdrawal, and clear time semantics.
- Current synthetic tests exercise aggregation logic, not a live browser, real data, volume or privacy certification.

Run `php ops/radar-01c/intelligence-aggregator-test.php`, or the branch-only GitHub Actions suite.
