# Recognized address and map in one card — 2026-11-09

Baseline: e0a167c8ebfcb538a641e11800e3609d09b5aa41.
Runtime candidate: 54fd9d6fe57b0aaba59a550a7e4075041fab108c.

The left location column is now a single pale teal card containing, in order, the recognized-position title, verified address/privacy note, and editable map/help. The public precision fieldset remains in the right column on desktop and follows the card on mobile. Both titles use the same inner top padding and line height; the fieldset legend is displayed inside its card rather than cutting through its border. The verified card stays hidden during manual fallback when there is no confirmed provider address.

The wizard preview no longer mounts the shared directional pad. Native provider zoom/fullscreen, keyboard shortcuts, cooperative gestures and marker correction are retained. The shared map-navigation adapter and the Home/Map/Radar maps are unchanged. Radius, private coordinates, public projection, moderation and validation semantics are unchanged.

Local validation: all 58 Smart Address DOM tests passed, including recognized-card containment/order, stale-address hiding, and native zoom/fullscreen/keyboard controls without the custom pan pad. Release/rollback unit tests passed (3). The source-backed browser fixture was updated for the new DOM and absence of the pad; its syntax was checked, but browser visual execution was not available through the authorized surface. No alternate browser access or maintenance bypass was attempted.

Guarded release workflow additionally runs all wizard/GEO/Smart Address tests, PHP syntax/publication checks and release/rollback tests before upload.

Production release: PASS (run 42631609938, job 129209048505). All 93 targeted JavaScript tests passed, alongside PHP syntax/publication and release/rollback checks. The four runtime files matched the pinned live source before replacement; backups and final SHA-256 readback passed. Frozen Core entrypoint, private configuration, activation and data preserved. No reset or URL changes.
https://github.com/Ciborio71/BadAround/actions/runs/42631609938
