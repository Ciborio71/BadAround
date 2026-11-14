# Public location choice cards — 2026-11-10

Baseline: bac958160059e2bfe22d1fb1c5f1504007c00728.
Runtime candidate: db28eb91ed65cf27982e15443b284e1e4a03c2e6.

The two public-location options now use a horizontal card layout. The existing decorative SVG occupies the left column, scaling between 80px and 144px. The right column contains the native radio beside the title, with the description underneath. Below 360px the illustration reduces to 64px with tighter spacing to retain readable text. No fixed card height is imposed.

The radio was moved inside the copy span, keeping the entire card as its enclosing native label. Existing radio names, values, checked/default state, keyboard interaction, selected-only bold titles, focus feedback, circle preview and conditional radius behavior are preserved. No custom checkbox behavior or separate click handler is added. No generated image or remote asset is required.

Changes are limited to the native wizard stylesheet and step template. The map/recognized-address card, stable address search slot, page heading/timeline and data/security contracts are unchanged.

Guarded release: PASS (run 42819569562, job 129841312589). All 97 JavaScript tests passed, alongside PHP syntax/publication and guarded rollback checks. Both runtime files matched the pinned live baseline; backups and final remote SHA-256 readback passed. Frozen Core entrypoint preserved; no activation, data, reset or URL change.
https://github.com/Ciborio71/BadAround/actions/runs/42819569562

Visual confirmation with the real provider remains subject to the existing authorized-browser restriction. Automated regression and deployed file integrity are the verified scope.
