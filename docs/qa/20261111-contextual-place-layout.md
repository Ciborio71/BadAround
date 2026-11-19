# Wrapped contextual place labels — 2026-11-11

The desktop place/danger pair previously aligned wrapper tops, so a two-line place label displaced its select relative to the danger cards. The wizard now measures the two visible labels and assigns their shared maximum minimum height. It recalculates after rendering, viewport resize, parent width changes and completed font loads. Below the existing desktop breakpoint the height is reset and stacked layout is retained. ResizeObserver is disconnected on teardown. No payload values, focus order, font family or font size are changed.

Contextual item prompt is shortened to “Dove è stato perso o trovato?” and property prompt to “Tipo di immobile o luogo”. Labels remain free to wrap; no clipping, ellipsis or nowrap is introduced. Scoped minimum widths and card text wrapping protect the row bounds.

Verification uses the actual PHP-rendered wizard and Chromium, forces the previous long item question, and checks control top alignment and absence of row overflow at 2048, 1440, 1280, 1100, 1025, 1024, 768 and 390 CSS pixels. These are isolated browser fixtures with all network requests intercepted; not a certification of the user's live browser session.

Runtime 804d2b7a5a19fd56a0c57f1bfd2591c7feae98da. Guarded production release https://github.com/Ciborio71/BadAround/actions/runs/43062922114 PASS; three candidate file hashes verified after installation. 43 JavaScript tests plus PHP/SEO/publication regression suites and all eight Chromium geometry checks PASS. Identity CI https://github.com/Ciborio71/BadAround/actions/runs/43062548953 PASS for identical runtime. Native live source readback confirms the label sizing rule and shortened prompts.
