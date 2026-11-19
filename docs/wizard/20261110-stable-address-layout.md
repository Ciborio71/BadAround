# Stable address search layout — 2026-11-10

Baseline: f38602ba0e21004d7610fa654b37a9dcfbd260f5.
Runtime candidate: 5f43bb32c01c1c9987cce01f316aa62cf7230577.

Reported defect: focusing the initial address input caused the privacy copy to move above the active Google widget. The initial input was before the privacy paragraph, but the widget holder was after it. On activation, hiding the input and populating the holder reordered the visible form.

Both inputs now occupy one shared search slot before the privacy paragraph. The holder is initially hidden, revealed only when the provider widget is ready, and cleared/hidden on reset. Loader failure retains the initial input in the same slot. Provider description references both privacy copy and status.

The slot/input/provider host share full width and 52px control height; border, radius, background, font and color scheme are set through the provider's supported host CSS properties. No shadow-DOM injection or private provider styles are used. Dropdown contents, branding, canonical location resolution, lazy loading and explicit-only GPS request behavior are unchanged.

Provider styling reference: https://developers.google.com/maps/documentation/javascript/reference/places-widget#PlaceAutocompleteElement-CSS-Properties

Local validation: all 60 Smart Address DOM tests passed. Two new cases verify the shared input parent, privacy order, successful activation, reset, and loader failure. The guarded workflow runs the full wizard/GEO regression set and PHP syntax/publication plus release/rollback tests before upload.

Production release: PASS (run 42815057444, job 129825925961). All 97 JavaScript tests passed, alongside PHP syntax/publication and guarded rollback checks. The three runtime files matched the pinned live baseline; verified backups and final remote SHA-256 readback passed. Frozen Core entrypoint and activation preserved.
https://github.com/Ciborio71/BadAround/actions/runs/42815057444

No data reset, privacy consent binding, security/access, maintenance, public projection or URL changes. Live provider visual confirmation remains subject to the existing authorized-browser restriction; DOM behavior and deployed file integrity are the verified scope.
