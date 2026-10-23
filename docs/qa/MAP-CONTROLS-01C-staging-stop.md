# MAP-CONTROLS-01C — final staging certification STOP

Candidate: `56853ec079cbd9bdd1f466104790e07f54bb60d1`.
Source: `fix/map-controls-01a-classic-editor-reconcile`.
Deploy/evidence branch: `deploy/map-controls-01c-staging`.

## Blocking defect and reproduction

Actual staging Native page, actual Google Maps JavaScript SDK, 390 px iframe viewport / 375 px usable document width:

1. Select Spazi pubblici → Rifiuti abbandonati o discarica → Ho osservato la situazione nella zona.
2. Continue to location; select the genuine Google Places result Torvaianica RM, Italia.
3. Choose approximate_area, default 500 m.
4. Start ordinary Tab traversal from the GPS button, through Google map controls to the directional pad.
5. Inspect the actual control rectangles against clipping ancestors.

Expected: mandatory zoom and four directional buttons remain fully visible and usable, with full 44×44 px custom-button targets.

Actual:
- Zoom + / −: x=355..395, width 40; useful viewport ends at x=375 → 20 px clipped.
- South/east pan buttons: x=348..392, width 44 → 17 px clipped; only 27 px remain visible.
- .ba-native-smart-address: clientWidth 343, scrollWidth 406.
- Native map: clientWidth 404 (406 including border); containing Native layout remains 343 px wide.
- .ba-report-stage and workspace: useful width 375, internal content extent 422; workspace scrollLeft 16 after ordinary interaction.
- At 320 px, observed Native text/map clipping and a wider internal content track also prevent responsive certification.

The document root itself reports scrollWidth == clientWidth. Therefore a root-only overflow check, CSS display/visibility, or measuring the button's intrinsic 44 px size is insufficient. Earlier structural observations were superseded by the final clipping-ancestor audit. The raw responsive rows record CSS visibility, not the final certification verdict.

This is a required zoom/pan usability failure. It is not a fullscreen failure. The concrete overflow/sizing chain is observed; no source-level remediation or exclusive attribution to one CSS/Google component is claimed here. No application patch was made.

## DEPLOY

RUN: [39945370252](https://github.com/Ciborio71/BadAround/actions/runs/39945370252)

RESULT: FAIL — required Native mobile navigation controls clipped

REMOTE HASHES: PASS — 5/5 candidate targets; 59/59 complete custody before and after upload

ROLLBACK: USED — byte-exact verified; three existing files restored, two newly added assets removed; final baseline custody 59/59 PASS

Only authorized runtime files were uploaded:
- wordpress/themes/badaround-child/functions.php
- wordpress/themes/badaround-child/assets/js/map-navigation.js
- wordpress/themes/badaround-child/assets/css/map-navigation.css
- wordpress/themes/badaround-child/assets/js/map.js
- wordpress/themes/badaround-child/assets/js/native-report/location-map.js

Rollback preserves approved Classic Editor functions.php SHA-256:
693068d807119ebd1691dd3a6dd7401d9d97363ed5cfc799dcbdde5ab141ce6c.

Artifacts independently downloaded and SHA-256 verified:
- 12498575290 before: 5663e506b6eb014a4013be5ce35e4c9aef49475144427dc4369b9688cd8f349a
- 12498560985 uploaded: c75c78be6dfced41d68a5760ad8f03ffab6d677f5f120425eb6fa68580f3817a
- 12499029709 final: e2cff36c4abf2a71ef46b7b1f6e03fa14f542e6f9eea87cfcffff48644d8d1c1

Rollback verified at 2026-10-23 11:56:41 UTC / 13:56:41 Europe/Rome.
Probe absence verified at 11:56:45 UTC / 13:56:45 Europe/Rome.

## REQUIRED MAP CONTROLS

ZOOM +: FAIL overall — functional, clipped on Native mobile

ZOOM -: FAIL overall — functional, clipped on Native mobile

VISIBLE PAN: FAIL overall — south/east full targets clipped on Native mobile

DRAG PAN: PASS — real mouse drag tested on all four map kinds

KEYBOARD: PASS activation/traversal mechanically; full usability gate FAIL where targets clipped

FOCUS: FAIL overall — visible focus outline present, but target containment fails on Native mobile

All four pan directions execute one viewport pan per user action. Standard Google SDK fitBounds can make an additional startup pan; this is distinguished from custom-control actions. No repeating/stuck movement observed.

## MAPS

NATIVE: FAIL — mobile containment/target clipping

HOME: PASS

PUBLIC MAP: PASS

DETAIL: PASS

Moderation: NO MAP in unchanged source. No new Google Maps instance discovered.

## FULLSCREEN

CONTROL PRESENT: PASS on all four candidate map kinds

TRANSITION: UNKNOWN

BLOCKING: NO

Activation attempted on Home, public Mappa, detail and Native. The control was retained, never replaced. No further fullscreen root-cause work was performed.

## RESPONSIVE

320: FAIL — Native clipping; other map kinds pass tested checks

390: FAIL — Native clipping measured precisely above; other map kinds pass tested checks

768: PASS tested navigation/layout checks

1024: PASS tested navigation/layout checks

1440: PASS tested navigation/layout checks

OVERLAP: NONE observed between pan pad, legend, attribution and CTA; Native mobile clipping is a separate mandatory failure

Fresh actual staging iframe viewports (same deployed HTML/config/assets/Google SDK), not synthetic local DOM:
- Native: five widths.
- Home: five widths.
- Public Mappa: five widths.
- Detail 500 m: five widths.
- Detail 100 m, 1000 m and exact: 390 px each.
Total 23 browser scenarios.

44×44 px intrinsic custom-button dimensions verified, but this is not a mobile PASS where clipping reduces the visible area. Normal Tab from GPS was explicitly tested to avoid relying only on automation-injected focus. Ordinary page wheel scrolling remains usable with cooperative Google gesture handling.

Physical touch/pinch: NOT EXECUTED — available browser reports maxTouchPoints=0. Actual Google gesture policy is cooperative; no claim of physical mobile gesture certification is made.

## GEO / PUBLIC FIXTURES

Actual Native Google circles: 50, 100, 250, 500, 1000 m verified.
Across each radius, zoom and directional pan preserve:
- request mode/radius
- private/source coordinates
- report marker position
- canonical territory
- circle center and meter radius.

Real Native background drag moved map camera while all report geometry and location payload remained identical.
Exact mode clears active radius and removes approximate circle.
Back/forward retained one map and one custom pad.
GPS permission denial preserved the Google-verified location.

Read-only detail checks:
- report77/event360 → circle 100 m
- report78/event361 → circle 500 m
- report79/event362 → circle 1000 m
- report80/event363 → exact marker, no approximate circle.

Home/public clusters expand and legend toggles; controls remain available. Public projections and persisted fixture state did not change.

## PERFORMANCE

NEW HEAVY DEPENDENCY: NO

SECOND GOOGLE LOAD: NO — one bootstrap script per tested rendered map page

SECOND MAP INSTANCE: NO — one observed instance per page, including Native back/forward

POLLING: NO in navigation helper/observer

DUPLICATE CUSTOM CONTROL: NONE

## REGRESSION

GEO-PREC: PASS — production-class quick regression and protected projection/state readback

GEO-AREA: PASS — live radius/geometry invariants and production-class regressions

ALL RADII: PASS — all five live Native radii

NATIVE V2: PASS semantics/version; FAIL required mobile control presentation

SMART ADDRESS: PASS — genuine Places selection by pointer and keyboard

GEOLOCATION: PASS tested denial path; successful hardware acquisition not available in this browser

HOME: PASS

DISCOVERY: PASS pinned differential and unchanged protected public projections

DETAIL: PASS

CLASSIC EDITOR: PASS runtime WordPress selection — use_block_editor_for_post_type returns false for ba_evento/post/page; both approved filters priority100. Actual admin editor screen opening was not executed because the browser admin tab was unauthenticated. No editor save or moderation operation was performed.

C.1: PASS quick workflow tests

C.2: PASS quick workflow tests

Pinned semantic/public-consumer workflow suite: 80 tests PASS, 0 FAIL.

DB: UNCHANGED — schema digests and protected-state digests equal before/after

TAXONOMY: UNCHANGED

PROTECTED FIXTURES: UNCHANGED — report/event/meta/audit/relationships/public projection/content hashes equal before/after, also matching previous certified read-only evidence

New reports/events: 0.
No moderation/publication, production, merge, GEO contract/radius/taxonomy/DB schema changes, NF-FINAL-01 or PUBLIC-DETAIL-01 activity.

## CLEANUP

Temporary staging-only expiring token-protected read-only observer removed.
FTPS absence VERIFIED.
POST /mapcontrols01s-qa/v1/snapshot → 404 rest_no_route after cleanup.
Raw temporary token file removed.
No persistent QA endpoint or security change remains.

Current staging: certified rollback GEO-PREC + GEO-AREA + Classic Editor baseline. MAP-CONTROLS candidate is not active.

## Evidence

- MAP-CONTROLS-01C/live-evidence.json — observations, 23 responsive scenarios, Native canonical/radius comparisons, normal Tab sequence and final blocking clipping audit
- MAP-CONTROLS-01C-state.json — before/after protected-state snapshots and artifact custody
- MAP-CONTROLS-01C/native-clipping-390.jpg — decisive clipped mandatory controls
- MAP-CONTROLS-01C/native-320.jpg — narrow Native state
- MAP-CONTROLS-01C/native-390.jpg — circle/controls
- MAP-CONTROLS-01C/home-focus.jpg — desktop controls, legend and focus
- MAP-CONTROLS-01C/public-390.jpg — public map
- MAP-CONTROLS-01C/detail-exact.jpg — exact fixture
- MAP-CONTROLS-01C/detail-100.jpg — approximate fixture

## FINAL VERDICT

MAP-CONTROLS-01 STOP — REQUIRED MAP NAVIGATION CONTROLS NOT CERTIFIED
