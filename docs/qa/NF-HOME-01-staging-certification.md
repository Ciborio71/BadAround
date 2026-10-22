# NF-HOME-01 — Staging certification

Candidate: `a4ef16aa067afc94a96cacd55a46a62d1248ed93`  
Repository: Ciborio71/BadAround  
Staging: https://staging.badaround.it/  
Deploy run: [39859999432](https://github.com/Ciborio71/BadAround/actions/runs/39859999432)  
Operations commit: `fa3843435c6133e7e9a5c51225343fb356be62f7` (QA/deploy tooling only; runtime files pinned to candidate).

## Deploy and custody

Only `wordpress/themes/badaround-child/front-page.php` and `wordpress/themes/badaround-child/assets/css/home.css` were uploaded. Strict FTPS identity/certificate verification remained enabled. No production deployment or merge.

Byte-exact rollback was acquired and uploaded as artifact **12461776041** before the upload step. Both backups matched the previous certified baseline `d0ac50bf4866b326943f3f4487b49fbe3d878d7b`. Artifact **12461167451** contains post-upload hashes and custody.

| Runtime file | Remote SHA-256 = candidate |
|---|---|
| front-page.php | a9dc0b994e9426a29bc7921b74e61af04aece93f8ef4934ec6964f9efc617f4b |
| assets/css/home.css | 8369538c0b6ed50ac8650fa6774e648a1ef7cc82cbd20e2e7b73db424e34f442 |

Before and after upload, custody passed for 33 Core/backend/Native/frozen-contract files, 15 rendering dependencies and the four Home/map paths. The two authorized Home paths switched from baseline to candidate; map.css/map.js remained at baseline. Core NF-UX-03A and the dual-stack backend were not uploaded or modified.

## Real staging browser QA

The authorized cloud Chromium executed the deployed Home and its real assets. Screenshot raster viewport was 1348×936; the DOM reported innerWidth 1363 including browser scrollbar behavior. This browser was **not** claimed to have been resized to the six requested widths.

- Exactly six fully clickable anchor cards, in order: Veicoli; Case e attività; Pericoli; Spazi pubblici; Animali; Oggetti e documenti.
- All descriptions match candidate exactly.
- SVGs and destinations matched the pre-deploy browser capture byte-for-byte.
- Each card was clicked on the actual staging page. All six listing destinations loaded with heading “Segnalazioni pubblicate” and the expected `categoria` slug. No Native preselection was added.
- All six anchors were reachable by keyboard; focus outline was solid 3px, rgb(8,127,140). The last focused card occupied y449.72–581.72, below the sticky header ending at y77.
- No nested interactive controls; semantic navigation/anchor structure preserved.
- Google map rendered real public markers and approximate areas; the existing geolocation button was enabled. No device location, private coordinate, submission or publication was requested.
- Legend matched baseline, closed and reopened correctly.
- Native was freshly reloaded after deployment and its server-rendered config explicitly declared `badaround-report/v2`.
- No new application console errors. Existing Google Marker deprecation warning and browser-extension metadata errors were already present before deployment; they are not application regressions.

## Fresh responsive evidence

Fresh pinned Chromium 1.56.1 executes the exact candidate frontend bytes, now verified equal to staging. It uses server-rendered Home/header/footer templates and deployed Astra CSS/JS read via FTPS. The existing isolated renderer omits Google engine; it does not certify map tiles at those widths. The real deployed map is covered by the live browser check above. No staging HTTP from GitHub Chromium is required.

| Width | Columns × rows | Result |
|---|---|---|
| 1440 | 3×2 | PASS |
| 1280 | 3×2 | PASS |
| 1024 | 3×2 | PASS |
| 768 | 2×3 | PASS |
| 390 | 1×6 | PASS |
| 320 | 1×6 | PASS |

Fresh execution verifies no document/card overflow, equal card heights, fitting icons, readable labels/descriptions, large tap targets, six exact links, keyboard focus, sticky-header title exposure after smooth scrolling, no nested controls, legend toggle, mobile-menu toggle at 768/390/320, and no JS page errors. Text contrast minimum: titles 17.30:1; descriptions 6.01:1; focus at least 3:1.

Fresh responsive artifact: **12462251072**. All five images were visually inspected in this certification run.

Screenshot files: cards-1440.png, cards-768.png, cards-390.png, cards-320.png, keyboard-focus-1440.png. These are fresh responsive-equivalence screenshots, distinguished from live-cards-focus.jpg (actual staging cloud-browser capture).

## Regression and scope

Native v2, map, legend and targeted GEO no-change regression: PASS. No Native, Core, backend or map runtime changes. Mobile navigation: PASS in fresh responsive execution.

DB/schema and taxonomy: UNCHANGED by this deployment. The deploy has only two explicit FTPS target paths; browser QA performed read-only navigation. No SQL, REST mutation, taxonomy write, moderation or publication was issued. No fresh DB digest was required or claimed for this presentation-only task.

New reports/events: **0**. Existing protected fixtures were not touched.

## Performance

New heavy dependencies: NO. Runtime JavaScript and asset dependencies unchanged. UTF-8 source deltas versus baseline: PHP +189 bytes; CSS +193 bytes. No evident Home loading/rendering regression observed. The cloud-browser read-only API does not expose Performance entries; live LCP is unavailable and no quantitative LCP delta is claimed.

## Result

DEPLOY RESULT: PASS; run 39859999432 concluded SUCCESS, including final remote re-read.  
ROLLBACK: READY, not used. A 30-minute QA acknowledgment watchdog would restore both byte-exact backups on failure or timeout. Final acknowledgment is issued only after live QA and fresh responsive evidence pass.

NF-HOME-01 COMPLETE — HOME CATEGORY CARDS CERTIFIED ON STAGING
