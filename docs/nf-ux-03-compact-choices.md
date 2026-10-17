# NF-UX-03 — Compact completed choices and related field pairs

Requested by owner after discussing usability of the Native card wizard. Staging-only follow-up to NF-VISUAL-02; no database writes, real submissions, media enablement, backend changes or Smart Address changes.

## Interaction

- A committed single-choice card collapses its alternatives. The selected card itself remains visible and keeps its radio input, preserving focus and the answer's context.
- “Modifica” reopens alternatives and focuses the existing selected option. There is no automatic step advance or scroll to the next question.
- Arrow-key exploration stays open; leaving the group compacts the completed answer. Space/click/Enter can commit explicitly. The edit control has an accessible name, aria-controls and expanded state.
- A changed parent invalidates a no-longer-valid compact child. Field error links reopen a group before focusing its control. Locked submission snapshots disable edit controls; a new report resets compactness.
- Only existing single-choice enum card groups collapse. Text input, checkboxes, multi-selects and legal confirmations stay open. No domain membership, required rule, condition or payload field changed.

## Layout

- At desktop/tablet widths >=768px: first/last name, vehicle make/model and range start/end share a row in natural reading order. At narrower widths they stack; a sole active conditional field spans the row.
- Email, description and address retain their full row. Phone, numeric values and private plate have proportionate maximum widths, bounded by their parent.
- On narrow mobile the edit button follows the compact card, avoiding cramped answer text.

## Validation

Five new DOM tests cover compaction/edit focus and payload stability, arrow-navigation preservation, parent/child invalidation and paired-field semantics. The complete gate contains 139 JS/DOM tests, plus existing PHP suites and syntax checks. Browser checks all seven steps at 320/390/768/1440px and include compact/edit interactions, keyboard flow, overflow and desktop/mobile name-field alignment. Visual screenshots reviewed.

Deployment scope: native-report.css and wizard.js only, with rollback to independently read-verified NF-VISUAL-02 staging source. Backend, F1.2 and Google reconciliation remain unchanged.
