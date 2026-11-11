# Unified BadAround pin system — 2026-11-11

Eight passive SVG assets: six category pins plus neutral and resolved. Colored silhouette with white keyline, white medallion and black original pictogram. Each standalone SVG is 624–831 bytes. assets/pin-designs.json is the shared registry consumed by the PHP helpers, native wizard config and public map config. Static and inline SVG parity is asserted. No raster embeds, remote fonts or third-party dependencies.

Coverage: Home category links/search/legend, shared event-card thumbnails and map previews, Mappa category filters/legend and marker renderer, detail location symbol/map, Radar search-center marker, wizard category cards/private draggable marker/step heading/public-position illustration, How it works and Sentinelle illustrations, and all branded placeholders. The eye and secondary badge are removed from placeholders. Utility crosshair buttons, cluster counts and the user-position dot remain function-specific controls rather than event category pins.

Exact projection anchor remains (28,64), including hover/selection; approximate projection remains a circle-shaped category medallion centered at (24,24), accompanied by the approved geographic meter circle. Approximate location is not relabeled exact. Legacy pin anchors align with the new physical tip, including the enlarged selected halo. Public/private GEO values, radius choices, taxonomy IDs, moderation, endpoint and filter semantics remain unchanged. Placeholder cache version is 4. Existing screen layouts and fonts remain.

Verification: shared registry/passive SVG tests; public GEO renderer and all radii, selected anchor, cluster redraw, resolved and exclusion behavior; actual wizard PHP config and browser pin layout at desktop/tablet/mobile; existing form, gallery and metadata regression suites. No production records or provider settings are changed.

## Verified production release

Runtime commit: `41cd77d0e89c0674d1685f439e4f0f320cc3af20`.

Guarded release [43115886501](https://github.com/Ciborio71/BadAround/actions/runs/43115886501) completed successfully on 2026-11-11 at 23:03:41 UTC. All 37 runtime files passed final byte readback/SHA-256 comparison. Pre-upload baseline custody and outside-webroot backups passed; no rollback required. No data reset, activation change or legacy URL change.

All PHP syntax checks and registry/native form/gallery/publication policy suites passed. Conditional form matrix: 3411 assertions, 2805 assignments, 33 oracle cases. Public GEO/map asset/form/proximity JavaScript regression suites passed. Chromium pin geometry fixtures passed at 1440, 1024, 768, 390 and 320 CSS pixels. Release-guard Python tests passed (3 tests). Full Identity/MySQL CI [43115886357](https://github.com/Ciborio71/BadAround/actions/runs/43115886357) completed successfully.

Authenticated production source readback confirmed the shared pin catalog helper, functions.php map configuration, new map body with exact anchor (28,64), and all Home legend category/resolved helper calls. Production browser visual certification was not performed: responsive browser checks above use actual source in controlled fixtures. No production reports, submissions, email or provider configuration were mutated.
