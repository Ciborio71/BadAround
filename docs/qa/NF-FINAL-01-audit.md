# NF-FINAL-01 — UX consolidation boundary and audit

Baseline application: a4ef16aa067afc94a96cacd55a46a62d1248ed93 (certified Home); Native v2: d0ac50bf4866b326943f3f4487b49fbe3d878d7b. Core bootstrap and frozen contract unchanged.

The certified wizard already has SIX navigation screens: Evento, Dove e quando, Dettagli, Immagini, Contatti, Riepilogo. Time is part of location; the five declarations are in Contatti. References to seven steps are historical. This change preserves the actual step order/configuration and does not invent a seventh screen.

The actual staging baseline was inspected in the authorized cloud browser through category/subtype, Google suggestion confirmation (Pomezia), time, details, no-media and public identity/contact. No consent was accepted and no report was submitted there. Review and the declaration layout were audited through source-backed isolated rendering of the same certified templates/config; then the candidate was exercised in Chromium across all six screens.

| Area | Finding | Presentation change |
|---|---|---|
| Category | Tall cards; copy differs from certified Home | Same Home copy/icons, reduced height, responsive 3/2/1-column presentation |
| Subtype/choices | Narrow text squeezed by selection indicator; instantaneous collapse | Dedicated indicator column, one subtype column at 320, 140ms confirmation before collapse; edit/arrow navigation retained |
| Long selects | Altro appears but focus does not progress | Focus dependent field after a visible select choice; no auto-next for free text |
| Location/time | Repeated instruction density; GPS result focus stops at confirmation | Existing explicit GPS and privacy copy retained; confirmed address anchors scroll and next time question receives focus; no focus theft after user moves on |
| Progress | Current dot has no number, mobile labels hidden | Numbered remaining/current steps, completion tick, explicit “Passaggio N di 6” and screen-reader state |
| Details/reward | Flat list obscures related fields | Titles follow existing field groups, disappear with inactive groups; reward remains explicitly chosen and contract-governed |
| Media | Existing fail-closed/no-media behavior certified | No media runtime changes; compact spacing only |
| Contact | Existing name/email/phone pairs need stable density | Same pair/keyboard order, 768+ paired contacts and mobile stack; identity/Social grouping; no defaults added |
| Declarations | Mandatory copy dominates and justified text creates gaps | Separate shaded area, left-aligned copy; exact legal wording and five canonical flags retained |
| Errors | Generic summary duplicates one field error and takes focus | Single local error focuses actual control, retains associated field message; multiple/global errors retain summary; unchanged backend codes |
| Review | Input instructions repeated after values | Same semantic keys/values and grouping; redundant non-privacy input hints omitted, privacy/identity/contact/Social guidance retained; declaration row visually separate |
| CTA | Inconsistent space and focus positioning | Existing labels retained, 48px tap targets, unified safe-header/CTA scroll calculation |

No model, evaluator, canonical config, serializer, backend, Home or map engine logic is modified. Location changes are limited to focus/scroll after existing successful acquisition, with request-ticket checks. No additional Google loading is introduced.

The dedicated gate compares 33 baseline payloads and review-key sets, 2,805 matrix cells and 554 dependency activations, all eight specifications, damage/emergency/stale rules, five consents, Social and GEO requests. Browser submissions are intercepted in memory only. Read-only FTPS custody verifies staging before/after browser QA; no probes or remote writes are installed.
