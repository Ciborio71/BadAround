# Passive location precision legend — 2026-11-13

User-approved labels: teardrop = Punto esatto; circle = Area approssimata. Neutral petrol #087f8c artwork avoids confusing location precision with the category palette.

A shared passive template is placed beside the Home's Ultime Segnalazioni heading (below it on narrow screens), in the Mappa result heading and inside the existing expandable category legends on Home/Mappa. The expandable placement also makes the explanation available when mobile results are closed. No buttons, links, input controls or extra JavaScript are introduced by the component.

Preserved: existing headings, see-all link, category legend, category colours, marker geometry, approximate radii, photo-only medallions, discovery, filters, SEO and event publication.

Checks: PHP validates exactly two labelled passive SVGs. Existing Chromium card/category fixtures additionally exercise legend alignment/containment, mobile placement and passive semantics at 1440/1024/768/390/320px. Existing PHP and public map/wizard regressions remain in the guarded release.

Candidate f0cf190822e9977d5e0be9a05636d9b060870c17; baseline 0b11ae7cacaca20cf3166b969aac8c44fa7876d3; guarded run 43416413426. Four runtime files, installing the shared component before its callers. Outcome: PASS. PHP semantics, 50 public UI/DOM checks and five Chromium widths passed. Guarded four-file upload and final remote hashes passed; native live source readback confirmed the shared component and Home placement.

