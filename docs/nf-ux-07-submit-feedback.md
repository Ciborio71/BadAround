# NF-UX-07 — visible submission progress

Two presentation files only: wizard.js and native-report.css. No API, backend, contract, database, media, publication or Smart Address change.

- Immediate spinner and “Invio in corso…” on the disabled submit button, plus an inline live status outside the form's aria-busy subtree.
- After six seconds, show reassuring processing copy without percentage or completion claims.
- Keep the same in-memory submission snapshot, API timeout and retry semantics. Existing busy guard prevents duplicate requests.
- Clear the waiting timer and spinner on success, recoverable/nonrecoverable errors, or teardown. Success message retains focus.
- Respect reduced-motion preference: progress text remains visible while spinner animation stops.

Validation: two deferred-response DOM tests check immediate status, delayed status, double-click guard, completion, error/retry snapshot and teardown. Full Native/Smart Address regression gate and source-backed browser checks cover the seven steps at four widths. No real report submitted.
