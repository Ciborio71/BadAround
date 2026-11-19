# Active wizard step in page heading — 2026-11-10

Production source baseline: e788eae09a83485e09f7a64fdc307abc787f851b.
Runtime candidate: f2cb16825071419835113bdf805e43163192e699.
Final test/workflow candidate: 182be3dad7e5eef1a14edbf45b3b2a9c0714faec.

The report page starts with “Cosa è successo?” and the existing first-step introduction. With the outer page header present, the wizard copies the current trusted step title and introduction into that header on every render, and hides the corresponding duplicate H2/intro inside each section. The timeline and questions follow. The textual step status remains available to assistive technology without visually repeating the active title. Emergency notices are independent and remain visible when triggered.

On forward/back/review-edit navigation, focus moves to the visible page H1 with a visible keyboard outline; scrolling uses its header rather than the hidden in-section heading. Each step section references the page heading via aria-labelledby. Standalone/legacy embeddings without the page header retain existing heading/focus behavior. Completion updates the header, hides the duplicate success H2, and new-report reset restores the first-step copy. No user content is used as heading HTML; the copy is written with textContent.

Primary question labels are 1.1rem (about 18px at a 16px root), including the address question and matched map-card/public-choice titles. Nested option labels, help text, input font sizes and font family remain unchanged. The page heading retains the former section-heading scale. Stage top spacing is reduced to put the timeline after the introduction.

Added a real page-header fixture option and two DOM tests covering all steps, back navigation, hidden duplicate introductions, heading focus, completion and reset. The first candidate stopped before upload due to a test assumption that the intro must immediately follow H2; a contextual emergency notice can legitimately occupy that sibling position. The assertion now checks the hidden original paragraph by its copied text. Production code did not need changing for that test correction.

Identity functional CI: https://github.com/Ciborio71/BadAround/actions/runs/42812278908
Final guarded release: PASS (run 42812479021, job 129817123980). All 95 targeted JavaScript tests passed, including the new page-header/navigation/completion tests. PHP syntax/publication and guarded release/rollback checks passed. Both Identity functional jobs passed. Four runtime files matched the pinned live baseline; verified backups and final remote SHA-256 readback passed. Frozen Core entrypoint preserved.
https://github.com/Ciborio71/BadAround/actions/runs/42812479021

Browser visual confirmation remains subject to existing maintenance/access restrictions. No security gates, schema, model requirements, public projection, data, URL or publication settings are changed.
