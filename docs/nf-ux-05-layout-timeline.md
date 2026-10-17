# NF-UX-05 — compact layout and connected progress

Native presentation only. No database writes, contract, Smart Address, media enablement, or publication changes.

- Reuse legacy connected circles and captions for the seven-step timeline; active and complete states remain semantic (`aria-current`, live step announcement). On narrow screens all seven dots remain visible and the active caption accompanies the full live step name.
- Remove the heading outline after pointer-driven step changes; retain visible keyboard focus.
- Three immediate-danger options share one row from 600px; stack on small screens. Selected choices retain existing compact/edit behavior.
- Pair email and phone at 768px, preserving DOM order and mobile stacking.
- Retain required damage status under the frozen contract. For vehicle theft, clarify additional material damage and the existing “Da verificare” answer. Other event labels/help restore on subtype change.
- Reuse existing category SVGs and approved category colors; no new raster marker assets or map changes.

Validation: full Native/Smart Address DOM gate, backend suites, JS/PHP syntax and real local Chromium source fixture at 320/390/768/1440px. No real report submission. Staging active files must be compared after deployment.
