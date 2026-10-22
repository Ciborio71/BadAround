# NF-UX-05C — Responsive evidence closure

Date: 2026-10-22 (Europe/Rome). Staging only.

**NF-UX-05 COMPLETE — 33-SUBTYPE NATIVE V2 REGRESSION CERTIFIED**

This closes only the three responsive viewport gaps recorded by NF-UX-05B. Its accepted semantic/actual-staging certification is carried forward unchanged; those suites and submissions were not repeated.

## CUSTODY

STAGING = CANDIDATE: **PASS**

5 NATIVE FILE HASHES: **PASS — 5/5**

APPLICATION CHANGES: **NO**

Application candidate: `d0ac50bf4866b326943f3f4487b49fbe3d878d7b`.
Native: `badaround-report/v2`.
Core/backend/frozen contract: **UNCHANGED**, full 33/33 custody PASS. Another 15 rendering dependencies (templates, shared CSS and supporting JS) also match the candidate bytes. No deployment or application/security changes.

Contract SHA-256: `dc282153243ffb80f3bd4f2a222ea0f4c4f42806fba6f3a0bde0a76855bd8e3c`.

| Native file | Remote SHA-256 = candidate SHA-256 | Result |
|---|---|---|
| inc/native-report.php | `22276d4ab2c7c62caeea2ee2342880eeab20261ce6e09976a4f4e3455e4915e7` | PASS |
| assets/js/native-report/model.js | `958f59b29ebc3e5ace7a45c9a7f3df0acd5532a760d3d4d0bfae4f649c87487d` | PASS |
| assets/js/native-report/wizard.js | `45b1e1e0304fa698f57ecdbf617f30ff02fac9cdea477b9f130e8eecd2a4bb83` | PASS |
| assets/js/native-report/errors.js | `18c1df66d6ceae24bfd52c2417c6c5280c8137d6bb6c001e41831b0e0495ef42` | PASS |
| assets/css/native-report.css | `fad035f7cf31763536ec4e5aa1ab58cca14ee33516e10608d30e4b0e9d4f3810` | PASS |

## RESPONSIVE

| Profile | 320 × 1000 | 390 × 1000 | 1440 × 1000 |
|---|---|---|---|
| SIMPLE | PASS | PASS | PASS |
| COMPLEX | PASS | PASS | PASS |
| ALTRO | PASS | PASS | PASS |
| EMERGENCY | PASS | PASS | PASS |
| CONDITIONAL | PASS | PASS | PASS |

Profiles: SIMPLE = civic_waste; COMPLEX = property_theft with stolen-item/access specifications and damage; ALTRO = animal_missing → animal.type=other; EMERGENCY = hazard_fire_or_smoke with actual danger-card change to unsure; CONDITIONAL = vehicle_stolen with fixed reward (amount, conditions, expiry, confirmation) and authority other specification.

## GLOBAL RESPONSIVE

| Gate | Result |
|---|---|
| HORIZONTAL OVERFLOW | NONE — scrollWidth equals viewport width in all measured states |
| CTA | PASS — reachable, inside viewport when focused, back/next do not overlap; summary submit reachable without activation |
| FOCUS | PASS targeted — dependent input inside viewport and not covered by fixed elements |
| KEYBOARD | PASS — native select Tab reachability, dependent entry/exit, CTA traversal |
| SCROLL | PASS — existing auto-next and ordinary scroll reach relevant controls; no blocking jump observed |
| EMERGENCY NOTICE | PASS — readable during actual danger-card auto-focus at all three widths; one contextual notice |
| ALTRO DEPENDENT FIELD | PASS — animal specification and authority specification fit and are usable at all widths |
| Progress navigation | PASS — actual forward/back handlers and current-step display |
| Labels / active controls | PASS — no clipped control or unreadable label observed |
| Summary | PASS — geometry/overflow and final CTA reachability at every profile/width |

The native animal select retains its existing focus behavior; Tab reaches the newly visible specification. Automatic dependent focus is checked on the existing authority adapter. No new auto-next behavior was introduced.

Emergency notice viewport geometry after the existing auto-focus: 320px top 84.625, height 121.5625; 390px top 84.859375, height 99.671875; 1440px top 93.03125, height 58. Header bottoms were 69/69/77 respectively. The notice stays below the header and within the 1000px viewport.

## EVIDENCE / RUN

[Run 39853910680](https://github.com/Ciborio71/BadAround/actions/runs/39853910680): **SUCCESS**.

QA source commit: `353eb6d95e7b673dee4f848b2cf985334602266e` on `qa/nf-ux-05c-responsive` (QA files only, candidate application tree unchanged).

[Artifact 12458048135 — nf05c-responsive-evidence](https://github.com/Ciborio71/BadAround/actions/runs/39853910680/artifacts/12458048135): 15 original screenshots, responsive.json (15 PASS, 57 recorded checks), rendered HTML, custody and exact Astra dependencies/provenance. Retention: 90 days. Screenshot PNG widths were independently checked as exactly 320/390/1440. All fifteen images were visually reviewed via per-width overviews in addition to geometric assertions.

Screenshots are named `{width}-{simple|complex|altro|emergency|conditional}.png`, one per combination. They are full-page captures: fixed/sticky header and skip-link positions may appear at the saved scroll offset in the full-page composite. This is capture behavior, not a claim that the live header occurs midway through the form; viewport focus/occlusion checks are made before capture.

The evidence combines exact remote byte identity, NF-UX-05B actual deployed-page certification, and fresh isolated Chromium rendering as explicitly authorized by NF-UX-05C. The unchanged candidate PHP templates render the form. Actual deployed config, inline CSS and parent-theme responsive hooks are captured read-only. Only isolated REST endpoint origins and the external Places key are adjusted outside rendering scope; fields, labels, steps, conditional and emergency configuration remain identical. No replacement Native markup/CSS/wizard/model/errors is used. See the method note for the full scope and initial harness corrections.

All page requests were intercepted locally, fulfilled from verified bytes or aborted; no request was forwarded to staging. There was no GitHub-runner staging HTTP/browser retry, security change or temporary staging probe.

## NEW SUBMISSIONS

**0**.

No DB/schema/taxonomy/GEO/Social/media/publication changes. No moderation or publication action. Existing protected reports/events remain read-only, including residual #57, #68/#344, #69/#345, #70/#346 and #71/#347. No new fixture is added; existing protected registry remains applicable.

## Cosmetic notes for future NF-FINAL-01 consideration

No blocking cosmetic finding. High-field-count profiles necessarily remain long on narrow screens; density/spacing can be reviewed separately later. This task does not start NF-FINAL-01 and applies no polish change.

## Final verdict

**NF-UX-05 COMPLETE — 33-SUBTYPE NATIVE V2 REGRESSION CERTIFIED**

Stop. No NF-HOME-01, no NF-FINAL-01, no merge, no production deployment.
