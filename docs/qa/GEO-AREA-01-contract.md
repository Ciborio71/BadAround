# GEO-AREA-01 — pre-deploy contract and evidence

Branch: feature/geo-area-01-user-radius. No deployment or staging fixture writes.

## Additive cutover

Report schema stays `badaround-report/v2`. Server governance marker stays
`user-public-location/v1`. Only the location request is versioned:

* `public-location-request-v1`: historical scalar exact/approximate request and existing system radius behavior remain unchanged.
* `public-location-request-v2`: object `{mode, radius_m?}`. Approximate requires a JSON integer in 50, 100, 250, 500, 1000. Strings (including `"500"`), floats, booleans and other values fail validation. Exact accepts absent/null radius; canonical normalization omits null. Non-null exact radius fails.
* Unknown request versions fail closed. No migrations, schema bump, new database column, or historical projection rebuild.

The v2 validator uses a temporary scalar view solely to evaluate the frozen conditional field registry, after validating the versioned object. The original radius-aware request is restored by normalization and persisted immutably. Frozen conditional JSON and its 2,805 field states/554 dependencies are unchanged. V1 intake is unchanged.

## UX and geometry

Exact remains default. Entering approximate initializes 500; leaving clears radius, errors and overlay. Summary shows a human label. Native Circle uses Google Maps meter radius and current Native marker position; dragging updates the center, zoom never changes meter radius. Radius changes fit the new bounds. No additional location source or Maps load.

Publication binds the sealed projection to the authoritative persisted radius. Public readers validate that radius against the sealed authority descriptor. Existing public detail/discovery/Home/map consume the projection without new consumer logic. No moderation radius controls exist; the service change only decodes the versioned object for read-only context.

## Automated evidence

Dedicated workflow: `.github/workflows/geo-area-01-user-radius.yml`.

Production-class tests cover all five radii, strict raw validation, canonical persistence, exact null stripping, retry identity, C.1 immutability, publication seal consistency, public detail/discovery rendering, and public-map meter circles. Existing regression suites cover Native v1/v2, 33 oracles, 2,805 combinations, 554 dependencies, damage, Altro, emergency, Smart Address, C.2, Social/privacy and media compatibility.

Fresh isolated Chromium executes the actual SSR markup/CSS/JS at 320/390/768/1440. Google provider methods are isolated through a meter-preserving provider test seam (not live Google tiles). Tests assert circle API radius, redraw, zoom independence, source-center following, stale removal, keyboard selection, focus/scroll, GPS denial and responsive layout. Screenshots are workflow artifacts. No staging HTTP/browser access is required or attempted.

Protected staging fixtures remain untouched. No real submissions. No application security changes. No heavy dependencies added to application runtime; Playwright is test-only.
