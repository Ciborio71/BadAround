# RADAR-UX-08 — Selective deploy instructions (admin-only preview)

**Target** `https://badaround.it`; deployed preview already enabled via `BADAROUND_RADAR_PUBLIC_COUNTS_PREVIEW_ENABLED`.

## Live findings (read-only, 2026-10-30)
- `httpdocs/wp-content/plugins/badaround-core/includes/class-badaround-radar-window-renderer.php` is currently the ORIGINAL 49-line RADAR-PUBLIC-06 renderer. It uses unformatted slugs and one inline paragraph for archive counts.
- The candidate branch `feature/radar-ux-08-summary-labels` changes this renderer to sentence-case labels and a semantic 3-metric definition list.
- Candidate also adds **scoped** styles in `page-radar-demo.php`, not yet present live.
- Mappa, toolbar, fixed-map positioning, sidebar overflow, category filtering and existing heatmap are **out of scope and must be preserved**.
- GitHub run for candidate PR #70 is not available as of this inspection. Do not claim CI pass. The original renderer remains operational.

## Minimal rollback-first order
1. **First phase: renderer only.** Back up the current live renderer as `class-badaround-radar-window-renderer.php.bak`. Extract candidate file at `wordpress/plugins/badaround-core/includes/class-badaround-radar-window-renderer.php` from the exact branch. Replace only same live path via Plesk. Do not upload other files.
2. Verify in the admin Radar page that `Torvaianica — Veicolo rubato` is now human-readable, counts remain the same and no PHP error appears. Since definition list CSS is not yet installed, initial layout may be plain.
3. **Second phase only after renderer QA:** preserve the LIVE `page-radar-demo.php`. Add ONLY CSS rules scoped to `.ba-radar-public-preview` inside its existing `<style>` block. Do not replace entire file with GitHub snapshot. Verify mobile and fixed-map/sidebar behavior, admin/anon differences.
4. On any anomaly roll back the single file from its .bak and leave preview disabled if needed.

## Stop conditions
Any new PHP error, missing Radar summary, map/toolbar changes, unexpected public preview, or event count divergence => immediate rollback.

No production writes by this document.
