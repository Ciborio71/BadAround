# EVENT-HERO-03 — 2026-11-07

User requested alignment of breadcrumbs through place metadata to the content below, unchanged breadcrumb text and more geographic context alongside status/type.

Changed only single-event-layout.php and appended event-specific pages.css:
- hero container now uses the same min(100% - 32px, var(--ba-container)) width as the body;
- at <=820px both use the same 24px total gutter;
- H1 retains max-width:none and natural wrapping within the aligned full content row;
- existing status/category/subtype remain; public territory path supplies region/province/municipality/locality labels. No private report reads or capital-city inference.

Candidate 91bd2f269fc1b93864b1167cbfc41fb5802d2ca1, guarded run42358183419, job128360747176 PASS. PHP template syntax, deployment guard tests, source comparisons, private backups, swap/readback and frozen Core guard PASS.
Identity primitives/MySQL run42358183345 PASS.
Live authenticated server-render check, event381:
- breadcrumbs exactly unchanged;
- chips Regione: Lazio / Provincia: Roma / Comune: Roma;
- title unchanged;
- gallery and deferred map controls retained.
Romanina remains in the existing public place/title; it was not invented as a canonical territory node.
No metadata, content, URL, privacy, Identity, media or activation changes.
Full real-device visual responsive certification remains part of the agreed final UI pass.
