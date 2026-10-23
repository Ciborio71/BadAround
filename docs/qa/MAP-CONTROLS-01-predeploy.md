# MAP-CONTROLS-01 — global map navigation

Branch: `feature/map-controls-01-global-navigation`.
Runtime base: certified GEO-AREA composite `879f55853fbb99c20ad85bc522e03f19f93bc3c6`. No NF-FINAL-01 changes. Pre-deploy only: no staging request, upload, mutation, submission or publication is made by this task or its CI.

## Complete map inventory

| Surface | Actual canvas/template | Initializer/config |
|---|---|---|
| Native reporting | `template-parts/native-report/step.php`, `[data-smart-map]` | `assets/js/native-report/location-map.js`, `renderMap()`, `importLibrary('maps')` |
| Home | `front-page.php`, `#ba-home-google-map`, context home | `assets/js/map.js`, `initMap()` common non-detail branch |
| Public Mappa | `page-mappa.php`, `#ba-google-map`, context full | `assets/js/map.js`, same non-detail branch |
| Public detail | `template-parts/single-event-layout.php`, `#ba-event-google-map`, context detail | `assets/js/map.js`, detail branch |
| Moderation/admin | `class-badaround-moderation-admin.php` | NO MAP: read-only geographic text; no Google constructor/iframe/loader |
| Other | Territory/search/list views, Smart Address autocomplete | NO additional Google Map; autocomplete is a Places widget, not a Map instance |

Repository audit finds exactly three Map constructor call sites (Native, shared Home/Mappa, detail). Both existing Google loaders and all projection/marker/circle creation code remain unchanged.

## Five runtime files

- `wordpress/themes/badaround-child/functions.php`: enqueue shared helper/CSS only on map/reporting pages; explicit dependency ordering.
- `wordpress/themes/badaround-child/assets/js/map-navigation.js`: shared options and one reusable accessible directional control.
- `wordpress/themes/badaround-child/assets/css/map-navigation.css`: navigation rail, focus/tap targets, safe spacing beside existing legend/preview.
- `wordpress/themes/badaround-child/assets/js/map.js`: apply same options/control to both existing constructor paths.
- `wordpress/themes/badaround-child/assets/js/native-report/location-map.js`: apply options/control to existing Native map.

No Core, backend, contract, model, wizard, errors, map geometry, radius, moderation, persistence, publication, Native copy or taxonomy change. Home template/CSS untouched. No application dependency/framework/map loader added.

## Policy

Explicit zoomControl/fullscreenControl/draggable/keyboardShortcuts TRUE; disableDefaultUI FALSE; cameraControl FALSE so the explicit +/- controls are not replaced by the newer expandable camera UI. `gestureHandling: cooperative` across surfaces preserves page scrolling and supports Google two-finger touch pan/zoom. Mouse drag and keyboard remain enabled. Native marker dragging remains independently governed by its unchanged logic.

One compact 2×2 directional pad provides four 44px semantic buttons: “Sposta mappa verso nord/sud/est/ovest”. Each calls only existing `map.panBy()` by one quarter of the current canvas dimensions, recalculated on every click (also after resize/fullscreen). No canonical/projection/marker/circle read or write, second map, fetch, new SDK load, timer or polling. WeakMap mounting prevents duplicate controls on map reuse.

Google-owned fullscreen and zoom plus custom pan share RIGHT_TOP (right-edge vertical flow). Existing public legends use the left side, with a reserved right-hand rail for controls even when a preview is open. Native/detail maps have minimum340px height to fit controls and attribution on mobile. Marker center and geographic radius remain unchanged by camera actions.

Google platform exception: Google does not display its fullscreen control on iOS. Do not claim an iOS hardware test or force a replacement fullscreen mechanism. Reference: https://developers.google.com/maps/documentation/javascript/controls . Google UI/platform behavior is validated live only after separate staging deploy authorization.

## Evidence model and results

All evidence is **pre-deploy**. Fresh Chromium runs use actual PHP templates and unchanged candidate application assets with an explicit offline Google API/control seam; screenshots label the provider simulation. Browser Fullscreen API enter/exit is exercised in Chromium. This is not a claim of candidate Google SDK execution on staging. Existing GEO-AREA live Google certification is the unchanged geometry baseline.

- Native: 320/390/768/1024/1440, exact and all five radii, +/- and four pan actions, fullscreen enter/exit, keyboard/focus and no trap; byte-identical payload/marker/circle geometry across viewport actions; mode switching, GPS denial and radius stale clearing PASS.
- Public: 30 browser scenarios, all five widths × Home/Mappa/detail exact/100/500/1000. Zoom, four pan directions, keyboard map pan, mouse drag, fullscreen restore, cluster interaction, legend toggle and geometry invariance PASS. Control/legend/preview/attribution rectangles and center-point hit testing prove no control overlap/clipping/obscured hit target in these fixtures.
- Public renderer unit differential still covers all five radii on Home/Mappa/detail and existing legacy/territory-only behavior.
- Actual WordPress asset registration is executed by the PHP Native fixture; helper loads before model/Native map and before public map.js, without builder or second Google dependency.
- Native semantic suites: 33 oracle,2805 combinations,554 dependencies, damage/Altro/emergency, payload/summary/stale, Social, Smart Address and public privacy remain unchanged.
- GEO-PREC/GEO-AREA production-class tests, v1/v2,C.1/C.2,moderation read-only radius, no-media and privacy gates run in isolated CI. Historical staging reports77–80/events360–363 and all earlier protected fixtures are untouched.

New tests/fixtures and existing test-provider updates model the new normal script dependency and Google controls collection; application semantics are not stubbed away. CI has read-only repository permission, no staging/production credentials or HTTP access to staging, and uploads evidence only.

## Gate

Dedicated workflow: `.github/workflows/map-controls-01-navigation.yml`.
Only a successful run on the candidate closes this pre-deploy gate. No deploy/merge or next task is authorized by a green run.
