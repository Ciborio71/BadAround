# EVENT-GALLERY-PERF-01 — verified production release, 2026-11-06

## Scope and implementation
User authorized image/performance improvements, a compact gallery with thumbnails and a smaller no-image state.
- New read-only gallery selects already approved/published public attachment copies, retains the explicitly published legacy cover, deduplicates and caps at five photos.
- Pending, deleted, redaction-incomplete, unsupported or native-fenced media cannot be surfaced through an old cover. No originals, private storage names, materialization or upload activation.
- Responsive main image with explicit intrinsic width/height, high fetch priority and eager loading; thumbnails lazy-loaded. Only the selected full-size image is loaded into the main stage.
- Compact stage 240–360px (260px small screens), arrows, thumbnail selection, keyboard Home/End/arrows, touch swipe and full-size public image link. No autoplay or third-party slider library. Without JS, thumbnail links still open approved public photos.
- Desktop overview pairs gallery with structured facts; narrow screens stack. Empty state is a small inline SVG/text panel, with no placeholder bitmap request.
- Detail map uses only already-projected public DOM geometry. No national Discovery download. Google SDK initialization waits until map nears viewport (200px margin), or explicit button. Failure/retry keeps address/details accessible. Home/full-map behavior retained.
- Admin-only no-store performance observer returns timing/counter/host summaries; never SQL, response bodies, request URLs, credentials or profile data. No persistence or settings changes.

## Evidence
Initial commit 5582322bcb2d80eb7ccd5b4f6e22daba5887475d, run42300933731, job128178222549: PASS.
Diagnostic clarification 50c5db2015e7f67f0727bb881a55adf2233a7e0d, run42301582826: PASS; follow-up transferred only changed observer.
Identity primitives/MySQL functional CI run42300933720: PASS; unrelated release jobs skipped.
Source comparisons, private backups, permission-preserving swaps and final readbacks PASS; frozen Core entrypoint unchanged.

Targeted tests:
- eleven PHP gallery privacy/render cases PASS.
- seven gallery/lazy-map DOM tests plus four Home permission/race regressions PASS.
- private performance observer permission/redaction test PASS.
- event public details, SEO, GEO precision/area and Identity community review suites PASS; guarded release tests PASS.

Real production server-rendered HTML:
- event381: approved-photo gallery, responsive image, width1024/height510, eager/high priority; compact overview and map loader controls present.
- event395: compact no-photo state, no old bitmap placeholder; approved address and witness badge retained.
- multiple-photo interactions verified with synthetic public-attachment fixtures; current event381 has one approved photo, so thumbnails appear only when multiple approved photos exist.
Native upload and true multi-upload acceptance remain deferred to hosting/pipeline completion as agreed.

## Performance diagnosis
Four authenticated diagnostic observations:
- elapsed PHP request time at diagnostic callback: 1549.6, 1809.6, 1976.9, 1504 ms;
- body generation, including authenticated preview overhead: 17.2, 37.7, 13.1, 14.7 ms, 14 queries each;
- no outbound WP HTTP requests recorded within these observed requests.
These are not public-page TTFB, Lighthouse, browser LCP or visitor field metrics. They identify time before body rendering as the main observed component in this diagnostic context.
Query Monitor/SAVEQUERIES active; external object cache false and WP_CACHE flag false for observed requests. No cache was enabled for Identity/personal pages.
OPcache extension loaded and ini enabled; runtime statistics unavailable. Do not misreport it as disabled merely because get_status is unavailable. Check hosting/runtime configuration on the new host, then remeasure public desktop/mobile pages without admin tools/extensions.
No claim of a new Lighthouse score or measured LCP/CLS improvement.

## Limits and next acceptance
Full visual responsive/browser acceptance remains OPEN. Local Chromium was unavailable under runtime IPC restrictions; the cloud browser rejected local-file previews by URL policy. No bypass/workaround was attempted after that rejection. DOM tests and authenticated server rendering supply functional evidence, not screenshots or visual certification.
No data reset, slug/ownership mutation, private QA/IP change, media enablement, cache activation or public opening occurred.
