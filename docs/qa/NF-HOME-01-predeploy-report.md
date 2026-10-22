# NF-HOME-01 — Home category cards pre-deploy report

Date: 2026-10-22 (Europe/Rome). Staging-only implementation; **NOT DEPLOYED**.

## IMPLEMENTATION

BRANCH: `feature/nf-home-01-category-cards`

CANDIDATE: `a4ef16aa067afc94a96cacd55a46a62d1248ed93`

RUNTIME FILES CHANGED:

- `wordpress/themes/badaround-child/front-page.php`
- `wordpress/themes/badaround-child/assets/css/home.css`

Only six category descriptions change in the PHP template. Existing labels, order, slugs, link expressions, icons and all non-category markup remain unchanged. CSS uses equal-height rows, 3/2/1 columns, larger descriptions, a 3px visible focus outline and scoped 100px scroll margin so focused titles settle below the sticky header. No Core, Native, map JS, GEO, media, Social, SEO URL or publication logic changed.

## CARDS

| Card | Frozen canonical category | Descriptive copy | Result |
|---|---|---|---|
| Veicoli | `vehicle` | Furti, danni e veicoli sospetti o abbandonati. | PASS |
| Case e attività | `property` | Furti, tentativi di intrusione e danni ad abitazioni, negozi e uffici. | PASS |
| Pericoli | `hazard` | Ostacoli, rischi stradali e situazioni di pericolo ambientale. | PASS |
| Spazi pubblici | `public_space` | Rifiuti abbandonati, guasti e danni a strade e aree comuni. | PASS |
| Animali | `animal` | Animali smarriti, ritrovati, feriti o in pericolo. | PASS |
| Oggetti e documenti | `item_document` | Chiavi, documenti, dispositivi e altri oggetti smarriti o ritrovati. | PASS |

COPY CONSISTENCY: **PASS**. Meaning checked against the unchanged Native category/subtype presentation and approved 33-subtype taxonomy. Examples are representative, not exhaustive. Category names remain identical to Native, Home legend and public terminology. No response/dispatch/publication promise.

ICON CONSISTENCY: **PASS**. All six SVG shapes are identical to the baseline render, with the approved pin colors and shape family retained. Icons remain decorative (`aria-hidden`), with explicit text labels. No new asset or icon library.

### Exact click behavior — preserved

All six cards remain whole-card semantic links to the existing filtered listing:

| Category | Destination |
|---|---|
| Veicoli | `/segnalazioni/?categoria=veicoli` |
| Case e attività | `/segnalazioni/?categoria=case-e-attivita` |
| Pericoli | `/segnalazioni/?categoria=pericoli` |
| Spazi pubblici | `/segnalazioni/?categoria=spazi-pubblici` |
| Animali | `/segnalazioni/?categoria=animali` |
| Oggetti e documenti | `/segnalazioni/?categoria=oggetti-e-documenti` |

This is the current supported routing intent. There is no Native preselection behavior on these links, and none is added by NF-HOME-01. The general “Segnala un evento” CTA continues to the Native page unchanged. Six actual Chromium clicks capture the exact existing listing path/query. The isolated destination fixture is a routing-intent check, not a new listing implementation or a claim of live filtering QA on an undeployed candidate.

## RESPONSIVE

| Viewport | Columns | Layout | Equal card height | Result |
|---|---:|---|---:|---|
| 1440 × 1000 | 3 | 3 × 2 | 132 px | PASS |
| 1280 × 1000 | 3 | 3 × 2 | 132 px | PASS |
| 1024 × 1000 | 3 | 3 × 2 | 132 px | PASS |
| 768 × 1000 | 2 | 2 × 3 | 132 px | PASS |
| 390 × 1000 | 1 | 1 × 6 | 120 px | PASS |
| 320 × 1000 | 1 | 1 × 6 | 120 px | PASS |

1440: PASS; 1280: PASS; 1024: PASS; 768: PASS; 390: PASS; 320: PASS.

DESKTOP 3×2: **PASS**. MOBILE STACK: **PASS**. HORIZONTAL OVERFLOW: **NONE**.

At every width, both document overflow and category-region overflow are checked. Cards have fitting icons, consistent rows, readable wrapping and comfortable click targets. The mobile horizontal category carousel is replaced by a vertical grid. Other Home carousels/layout remain untouched.

## ACCESSIBILITY

| Gate | Result |
|---|---|
| KEYBOARD | PASS — all six cards reached in order using Tab |
| FOCUS | PASS — 3px outline and focused title visible below sticky header after smooth scroll settles |
| CONTRAST | PASS — titles at least 17.30:1; descriptions at least 6.01:1 against the white card; focus at least 3:1 |
| SEMANTICS | PASS — existing anchors, no nested buttons/links/controls, text conveys category independently of color |

Existing heading hierarchy and category navigation landmark are retained. Native card input semantics are not touched. Initial screenshot review led to the scoped scroll-margin and settled-focus assertions. The capture code uses document-space component clips so the screenshot tool does not reposition a long grid under the sticky header; it does not alter application CSS/markup for screenshots.

## REGRESSION

| Gate | Result |
|---|---|
| HOME | PASS — candidate SSR executes; six categories present; layout tests at six widths |
| CARD LINKS | PASS — all hrefs and clicked path/query match baseline; no routing redesign |
| NATIVE V2 | PASS — unchanged Native runtime bytes and config explicitly emits badaround-report/v2 |
| MAP | PASS pre-deploy scope — map template/subtree/JS/CSS unchanged; existing targeted public/private map regression passes |
| LEGEND | PASS — unchanged labels/icons/colors; actual disclosure open/close checked in Chromium |
| GEO | PASS — no GEO code/data change; targeted existing map/privacy test passes |
| JS CONSOLE | PASS in candidate isolated browser — pageerror list empty across six widths |
| DB | UNCHANGED — no DB/schema command or real submission by this task |
| TAXONOMY | UNCHANGED — no canonical name/key/slug/registry change |

No new report/event, moderation, publication or Social action. Protected reports #35–#71 and their related events receive no mutation.

### Staging baseline and QA boundary

Fresh strict-TLS FTPS read-only custody: **33/33** certified Core/backend/Native/contract paths, **15/15** rendering dependencies, and **4/4** Home baseline paths (front-page.php, home.css, map.css, map.js) match their expected certified baseline bytes. Staging remains on the pre-deploy baseline.

The ordinary authorized cloud browser reached the live Home, showed the six expected link destinations and an open legend, and loaded the Google Maps engine/tile scripts with substantial rendered map DOM. The execution environment then disconnected before further browser interaction; no security or application change was used to work around it.

Pre-deploy visual QA therefore uses fresh isolated Chromium on the actual candidate PHP templates/CSS, the verified deployed Astra assets and previously captured global Astra inline context from NF-UX-05C, with the Home body class/style stack observed live. Discovery uses the empty-state fixture; no Google engine is mocked or called in isolated screenshots. The empty map area in the keyboard screenshot represents this intentional isolated test scope, not the live map. Real composite candidate staging/map interaction and live LCP measurement remain post-deploy checks after separate authorization.

## PERFORMANCE

NEW HEAVY DEPENDENCIES: **NO**.

OBSERVED HOME REGRESSION: **NONE in the pre-deploy layout/function checks**.

No new runtime JS, map dependency, external icon bundle or image asset. Existing home.css grows by **193 bytes**, front-page.php by **189 bytes** (UTF-8). Hero, map markup, script enqueue paths and asset dependencies remain unchanged. No live LCP timing delta is claimed before the authorized staging deployment.

## CI

RUN: [39858504119](https://github.com/Ciborio71/BadAround/actions/runs/39858504119)

RESULT: **PASS / SUCCESS**.

Includes PHP lint, diff whitespace check, existing `tests/map-02r-priv-01.php`, explicit Native v2 config check, only-two-runtime-files boundary, exact six-copy-change guard, baseline SVG/link/label comparison, six widths, real keyboard/focus, WCAG contrast calculations, six link clicks per width, legend and mobile-menu interaction, and empty JS error list.

The separate general “PHP Syntax” workflow remains red at the known legacy `Test recovery golden-state invariants` step, which references the already-absent `assets/js/report.js`. The dedicated NF-HOME-01 workflow passes. The unrelated legacy workflow is not modified.

## SCREENSHOT EVIDENCE

Exactly five final screenshots, visually reviewed:

- [Desktop 1440](nf-home-01-evidence/cards-1440.png)
- [Tablet 768](nf-home-01-evidence/cards-768.png)
- [Mobile 390](nf-home-01-evidence/cards-390.png)
- [Mobile 320](nf-home-01-evidence/cards-320.png)
- [Keyboard focus on a category card](nf-home-01-evidence/keyboard-focus-1440.png)

Component screenshots show the category region rendered at the named viewport width; the keyboard screenshot retains surrounding Home context. Original evidence, logs, rendered candidate HTML and custody JSON: [artifact 12461016663](https://github.com/Ciborio71/BadAround/actions/runs/39858504119/artifacts/12461016663), retention 90 days. Final screenshots and structured results are also retained in this repository documentation branch.

## PRE-DEPLOY VERDICT

**NF-HOME-01 READY FOR STAGING DEPLOY — HOME CATEGORY CARDS ALIGNED**

Stop here. No staging upload, no merge, no production deployment, no NF-FINAL-01. A separate explicit deployment authorization is required by the task before changing staging.
