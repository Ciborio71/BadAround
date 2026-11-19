# Wizard text width and desktop short-answer layout — 2026-11-10

Baseline: d948653bb926d5c84d253d8ac37301debc0f86cc.
Runtime candidate: 612a2bcf26e2e4a782ce0db621132b165997c921.

All Native wizard textareas now fill their existing question column rather than being capped at 80ch. Input limits, heights, validation and help-copy widths are unchanged.

Three-option single-choice groups with short labels (at most 32 characters each) use three equal columns on desktop (1025px and above). This covers damage status, witness status and similar compact answers. Category and event-subtype groups are excluded from this automatic classification; longer labels retain their previous layout, and collapsed selected cards retain their existing editable presentation. Tablet/mobile rules remain unchanged.

The adjacent place-type and immediate-danger questions are grouped in one presentation row, split approximately 30/70 on desktop. Their controls have aligned label spacing and a 64px minimum control height. The pair stacks below the desktop breakpoint. Hidden/inapplicable fields retain full-row behavior. Existing other logical field pairs remain unchanged.

The emergency notice is inserted after the entire presentation row, never inside it. This preserves full-width warning presentation and the progressive disclosure logic, which expects the last field in each pair to complete the group. Auto-focus scrolling recognizes this row-level notice. Added one regression test covering field order, short-option classification, emergency placement and progression to the next step.

Release: PASS (run 42828204068, job 129870971482). All 98 targeted JavaScript tests passed, alongside PHP syntax/publication and release/rollback tests. Both Identity functional CI jobs passed (run 42828203092). The two runtime files matched the pinned live baseline; backups and final remote SHA-256 readback passed. Frozen Core entrypoint, activation and data preserved; no reset or URL changes.

https://github.com/Ciborio71/BadAround/actions/runs/42828204068
https://github.com/Ciborio71/BadAround/actions/runs/42828203092

No live visual certification is claimed behind the existing authorized-browser restriction. The verified scope is automated behavior, source-defined responsive layout and installed file integrity.
