# Radar A — public observatory demo

Radar counts published community reports, each with weight one. It is not a measure of crime, objective risk, distinct episodes or population-adjusted prevalence. Mappe remains the event-level view. The demo remains noindex.

## Data and privacy

The server consumes only `BadAround_Discovery_Query` public projections and public taxonomy labels, gated by the existing publication contract. It never queries report tables or private coordinate metadata. The browser calls `/badaround/v1/radar`, not `/discovery`, and receives totals, series, administrative features and aggregate cells, without event IDs, titles, links, media or point coordinates.

Counts include records without public geometry; `geographic` separately reports mappable coverage. The date basis is explicitly episode or publication. Missing, invalid and future episode dates do not enter date-filtered periods or charts; they are accounted for separately in the all-period totals. Days/weeks/months with no dated records are zero-filled. The all-period chart has an explicitly displayed maximum 24-month interval. There is no inferred date or time, severity weighting, trend percentage or risk score.

Local geography uses a fixed regular Web Mercator hexagonal grid, base circumradius 1500 projected metres. It is **not H3**. Cells are enlarged by powers of two until the entire published uncertainty circle fits within the cell. Ground dimensions vary with latitude; larger privacy cells may overlap smaller cells. Each report contributes to exactly one cell, and the UI describes counts per area rather than normalized spatial density. Cells with fewer than three reports are omitted. This is a display threshold, **not a formal k-anonymity guarantee**; source coordinates were already public. Public administrative counts below three are not colored. Geography without usable counts is shown as insufficient/unavailable, never safe.

Territory filters use the public hierarchy; nearby/radius and explicit viewport analysis use public geometry only, and include an approximate location only when its whole uncertainty circle fits inside the selected area. Dragging or zooming alone never changes the analytical filter. Geolocation is requested only following the user's button click and is not persisted by Radar. Server/access-log retention remains subject to the site's existing configuration.

## Boundaries

Administrative boundaries: ISTAT generalised 1 January 2026 edition, through `guglielmo/geojson-italy` commit `ba3347df3a53352891109f8198c597a0273684c0`. https://github.com/guglielmo/geojson-italy/tree/ba3347df3a53352891109f8198c597a0273684c0 — CC BY 4.0, ISTAT attribution retained in the map.

Regions/provinces are bundled in `radar-geography.php`, gzip/base64 JSON, simplified at 0.015 degrees for display. No event location is derived from these shapes. Municipality boundaries are loaded on demand from the pinned source through an allowlisted numeric province endpoint, cached seven days; failures are explicitly reported and never replaced with invented boundaries. Public taxonomy labels without a matching shape remain navigable in rankings/search.

## Caching and limitations

A bounded public snapshot (maximum 10,000 queried records; completeness flag if capped) is cached for one hour, with invalidation on event save/meta/terms/trash/restore/delete and territorial/type term changes. Filters run server-side over this public snapshot. No expensive GROUP BY or raw private-table scan is added. Performance must be measured on production before setting an SLA. Duplicate reports are not automatically treated as unique episodes. Small sample sizes, incomplete dates, unmatched geography and missing coordinates remain visible limitations.

## UI contract

Full-width paired analysis/map dashboard; breadcrumb Italy → region → province → municipality → locality, category and period preserved. Original six category pin artworks. Single selected category or clearly labeled all-category view; monochromatic petrol count scale. Empty state explains coverage and links to reporting. Google Maps retained, with zoom/fullscreen and no directional pad. Hover/click also have a keyboard-accessible ranking/search alternative. Map deep link carries the selected viewport; current Mappe filters remain independent.
