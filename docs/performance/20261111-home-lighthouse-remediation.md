# Home Lighthouse remediation — 11 November 2026

Input: user-provided desktop Lighthouse 13.5.0 report captured 2026-11-11 00:56 Europe/Rome. Scores in that report: performance 84, accessibility 92, best practices 92, SEO 58, agentic browsing 50. It contains WordPress administrator/Query Monitor UI and Chrome-extension assets; it is not an anonymous/mobile or field-CWV baseline.

## Shipped

- Shared missing-image fallback now uses a passive local 407-byte SVG instead of the PNG whose transfer was about 1.24 MB in the report. Both PHP cards and JS map fallback use the shared helper; the old uploaded file is preserved. Existing real image attachments are unchanged.
- Dedicated home meta-description emitter, gated to is_front_page and suppressed with Yoast/Rank Math ownership. Event metadata, canonical and robots owners are unchanged.
- Homepage search/GPS/Sentinella buttons use darker teal for white text. Category card badges/links mix 55% category accent with navy for readable text; decorative pins and category accents are unchanged. Numerical WCAG contrast tests cover every category's link and badge backgrounds and normal/hover buttons.
- Places autocomplete binds immediately but imports the library only on focus/typing. Maps initialization and SSR results remain independent of Places. Tests cover delayed import, selection, one import per search widget, stale pending suggestions after clearing and failure/retry. The Native wizard's Smart Address module is unchanged.

## Verification

Baseline: bf24f2021d5f53769daf45d397e8c0be04123f4f.
Runtime: 2eb8df1c2f6280d38301c834e66c68a938abfc5a.
Guarded deployment: https://github.com/Ciborio71/BadAround/actions/runs/42929199728
Identity CI: https://github.com/Ciborio71/BadAround/actions/runs/42929199514

Guarded release PASS: five runtime files matched the pinned remote baseline, existing files were backed up, candidate files were installed and read back with matching SHA-256 hashes. The new SVG and metadata include are installed before functions.php references them. Rollback is verified by the existing release tests.

128 JavaScript tests passed, 0 failed; home metadata tests passed with no external SEO owner, Yoast and Rank Math. Existing GEO/publication, event SEO/structured data, date, gallery, v2 validation/persistence and OTP/wizard checks passed. Identity primitives and ephemeral MySQL CI passed. One older source-order assertion named a superseded renderList signature; it was aligned to the already-approved visibleItems signature without changing rendering order.

Live authenticated discovery/home render for Pomezia: 9 public cards, the new SVG appears, no old placeholder PNG URL appears. Read-only wp-content inspection confirmed the home metadata include and hook in the deployed child theme. Default theme-source read initially targeted an unavailable non-live scope; explicit read-only wp-content inspection resolved it. No draft theme was modified or published.

External get_page_html still receives the maintenance page with noindex,nofollow,noarchive. No bypass or QA/Identity activation change was attempted. A new clean visitor Lighthouse score, full home head DOM and visual layout are not certified by these source/unit/server-component checks.

## Remaining report items and boundaries

| Finding | Disposition |
| --- | --- |
| Home meta description | Implemented and PHP ownership/scope tested; live source read back. |
| Large placeholder | Implemented; native public feed render confirms new fallback. |
| Reported public text contrast | Implemented; numerical AA checks pass. Full browser accessibility rerun remains. |
| Eager Places library | Deferred to explicit search use; behavioral regression tests pass. |
| Server response ~1.92 s | Profile/retest on the planned hosting migration; no claim that this CSS/asset release improves TTFB. |
| Static asset TTLs | Hosting configuration task for the new hosting. No global caching/no-store override applied. |
| Protected page/session back-forward cache | Preserve no-store protections; reassess public-page policy separately at opening. |
| ARIA/agent tree faults in WP admin bar/Query Monitor | Repeat clean authorized visitor audit; do not patch public content for administrator-only markup. |
| Unminified JS / most unused JS | Report points to browser extensions; retest without extensions. Google Maps still owns its necessary runtime modules. |
| Google Maps transparent 1x1 image, tile compression and Roboto display | Provider-owned resources; do not rewrite provider DOM, imagery or branding to satisfy diagnostics. |
| Small unused shared Astra CSS / render-blocking CSS | Reassess after clean responsive QA; no unsafe theme stylesheet removal. |
| noindex/nofollow | Deliberately retained until approved opening after beta/reset/legal gates. |

No event/report writes, reset, file deletion, slug changes, taxonomy changes, publication/Identity changes or real-email sends. No new Lighthouse score or ranking improvement is claimed.
