# NF-FLOW-04 — Six-step wizard, explicit preferences and compact related fields

Baseline: verified active179cb3a772e8c3b4c2a4df9f084df9812cdd8f24. Current active wizard, config, shell and CSS were read before changes. Work isolated from the older original checkout.

## Product behavior

Six presentation steps: Evento → Dove e quando → Dettagli → Media → Contatti → Riepilogo. Time fields are grouped with the existing Smart Address interface, outside the hidden territorial fields. They disclose once a private address is present; canonical location/time paths, enums and validation are unchanged. Both are validated before advancing. Time errors retain their own field messages/focus, and review includes time in the combined section.

Related desktop groups: vehicle make/model/color in three columns; animal breed/name, property entry method/alarm, date/time knowledge, time-range endpoints, names and contact details in two. Mobile stacks them in semantic order. Hidden conditional group members do not reserve a column. Longer descriptions and unrelated fields stay full width.

The Native UI explicitly clears the reward and public-identity fallback selections on boot and on each new report. Frozen Model defaults are unchanged outside this presentation adapter. Requiredness remains canonical and an explicit selection is needed. Existing dormant answers are preserved when changing branches.

Contact copy invites useful sightings/testimony through BadAround, with three unchanged canonical values community/badaround_only/none. No option promises direct user contact or disables necessary case-management communications. Existing policy links, one checkbox, successful confirmation and GPS behavior remain present.

## Scope and QA

Runtime: inc/native-report.php; template-parts/native-report/shell.php and step.php; assets/js/native-report/wizard.js and errors.js; assets/css/native-report.css. No core/schema/Model/API/provider/map/media backend/publication/SEO or fixture changes. No real submissions/DB mutation or production deployment.

Tests updated to use six actual sections and explicitly answer formerly defaulted required choices. Additional tests verify blank choices on boot/reset, temporal validation/focus in the combined step, hidden required controls and unchanged private address. Source-backed browser paths cover vehicle112/anonymous, pseudonym, property theft, animal missing and lost documents at390/1440px, including desktop triple/pair alignment, mobile stacking, policy-link data retention and mocked201 submissions. Existing320/390/768/1440 smoke and GPS tests remain in the gate. Google/transport are mocked; no physical-iPhone or new live-provider session is claimed.

The social preference is specified separately in social-preference-next-task.md. It is absent from the current frozen canonical report model and is not silently displayed without durable capture. That proposal requires the dedicated contract/persistence decision before an operational question is released.
