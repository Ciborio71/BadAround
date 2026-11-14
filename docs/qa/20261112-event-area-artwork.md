# Public approximate-area artwork — 2026-11-12

Presentation only. Scope: Home, geographic Home feed, search, event listings, territorial cards, map list/preview cards and event gallery fallback.

Approved exact_point retains category teardrop artwork. Approved approximate_area uses a category-coloured circle, white centre and the existing black pictogram. Server rendering obtains only mode/radius from BadAround_Public_Geo_Projection::public_view; JavaScript uses the validated discovery public_geo projection. No private source, requested precision, legacy radius or unverified metadata is used to infer approximate mode.

Uploaded images remain unchanged. Missing images use one of seven new passive category/neutral area SVG placeholders (1370–1577 bytes). Photos receive the corresponding small mode/category medallion. Approximate cards include the actual public radius in metres. Existing map circle geometry, public coordinate semantics, resolved marker behaviour, publication rules and URLs are unchanged.

Validation: XML parsing and JavaScript syntax locally. CI includes PHP card/asset fixtures across all categories, preserved uploaded photographs, exact and missing-proof cases, map/list/preview DOM fixtures, existing access/GEO/gallery/publication regressions and five Chromium viewports. Google Maps remains mocked in browser fixtures.

Candidate: 6cf0a7874be89ff484ae51fc2ed7fc9e067447ae. Guarded deployment workflow: 43321757735. Baseline c33f54f66c484419a23dcd8662238df75847938d. Final outcome: PASS. All 50 DOM/regression checks, PHP artwork fixtures and five Chromium viewport checks passed. Guarded 18-file upload and final remote hash comparison passed. Native live source readback confirmed shared circle artwork, card radius text and photo-preserving map fallbacks. Full Identity/MySQL checks also passed on the identical runtime candidate in run 43321453939.

