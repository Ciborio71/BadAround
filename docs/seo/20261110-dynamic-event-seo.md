# Dynamic event SEO — 10 November 2026

Scope: complete purpose-based dynamic Title, meta description, H1, H2/H3 for all 33 canonical event subtypes. Approved typography and frozen URLs remain intact.

Runtime changes: `class-badaround-event-seo.php` (editorial copy 1.2, URL contract stays 1.1), `functions.php` (matching concise Title in the WordPress fallback), `inc/event-gallery.php` (no orphan photo H2 for the empty state).

Metadata and sections use public taxonomy, structured public vehicle attributes and signed public detail facts only. Animal species and object found/lost state require public enum facts. Street extraction requires an already-public signed exact address and conservatively drops ambiguous numeric formats; SEO never includes the civic number or plate. Private source rows are never queried by the renderer.

The initial pre-upload tests found an identical Title/H1 in the missing-model theft fallback. The final candidate gives that case a distinct Title; the failed attempt made no runtime changes.

Final candidate: 51e43faf8104fe2d7b800a3033b5b7b5ce47c90f.
Baseline: 7d594b9b8b87f39b77e164faee54e736368ce2cf.
Release: https://github.com/Ciborio71/BadAround/actions/runs/42918759175
Identity CI: https://github.com/Ciborio71/BadAround/actions/runs/42918759182

## Verified results

- Guarded release PASS: all three runtime files matched the pinned remote baseline, were backed up, replaced and read back with matching SHA-256 hashes. No data reset, activation change or legacy URL change.
- New dynamic SEO suite PASS: 33 subtype templates, missing data, exact/approximate address boundary, conservative street parsing, full-plate/civic omission, read-only projection without private SQL, distinct Title/H1, terminal states, approved animal/object enums and long Unicode attributes.
- Existing public-detail/plate policy, SEO, section heading, gallery, H1 formatter, dates and WebPage/BreadcrumbList suites PASS. V2 suite: 3,411 assertions over 33 oracle cases and 2,805 matrix assignments; PASS.
- JavaScript: 116 passed, 0 failed. Guarded-release/rollback Python tests: 3 passed. Identity primitives and ephemeral MySQL CI: PASS.
- Live event 395: editorial revision 1.2 active; canonical unchanged at `https://badaround.it/eventi/veicolo-rubato-roma-honda-sh/`. Preview Title: “Furto Honda SH a Roma, in via Vincenzo Arangio Ruiz | BadAround”. H1 rendered with the approved pipe/capitalization; server-rendered body contains one H1, purpose-based H2 and geographic H3. No photo H2 for the empty gallery; responsibility box retained.
- Live event 381: URL unchanged at `https://badaround.it/eventi/hyundai-tucson-bianca-rubata-a-roma-zona-romanina/`. Section generator uses copy revision 1.2. Historical SEO activation remains false: metadata/H1 are not automatically migrated from the original page.
- Live structured data for 395: WebPage + BreadcrumbList, canonical unchanged, no Event node.
- Cache purge found no known page-cache plugin and successfully flushed the WordPress object cache.

These checks verify generator output, automated regressions and authenticated server rendering. Full visual browser certification and Google indexing/ranking certification are not claimed. QA and noindex stay unchanged; the planned beta/reset/hosting/legal gates remain separate.
