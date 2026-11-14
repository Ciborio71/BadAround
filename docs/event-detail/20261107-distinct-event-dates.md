# Distinct report receipt and occurrence dates — 7 November 2026

Released source e60a1a8a5b32fff431ebd6a60848672b81f7c62a.
Guarded production release run 42361774461 / job 128371484045: PASS.
Identity functional CI run 42361774436: PASS.

## Behaviour

The location line displays the original report receipt from ba_reports.created_at (UTC), formatted with the site's Europe/Rome timezone. Native reports say “Segnalazione inviata il”; external imports say “Segnalazione acquisita il”. This is the server receipt, not the time the visitor started filling the form, publication, or last modification.

A separate “Data/periodo dell’evento” field displays moderated public occurrence metadata or the approved wizard snapshot. Exact dates, time ranges and indicative periods remain distinct. Relative periods are anchored to the original receipt date. Missing occurrence is explicitly “Non specificata dall’autore”; no date is inferred from publication, title or description.

A signed event/report-bound receipt snapshot is projected by a capability-checked transactional service after publication. Frontend rendering never reads private reports. The admin route accepts no caller-provided timestamp. Publication source locks, audit, verified persistence, atomic rollback and an idempotent repeat operation protect the projection. No report, GEO, URL, identity or original content changes.

## Verified

- PHP lint, 14 targeted receipt/occurrence cases, existing public-details/GEO regression fixtures and 3 guarded release tests passed before upload.
- UTC/local midnight, summer DST, imported/native wording, tamper/binding rejection, permission denial, commit rollback and missing source tested.
- Three runtime files installed with prior-source comparison, private backups and hash readback. Core entrypoint remains frozen.
- Existing published events 381 and 395 received their original timestamps through the owning admin projection service.
- Live server-rendered 381: “Segnalazione acquisita il 26/10/2026 alle 17:12”; occurrence absent in public fields, explicitly unspecified.
- Live server-rendered 395: “Segnalazione inviata il 06/11/2026 alle 17:46”; occurrence unspecified in its public wizard snapshot.
- Event381 breadcrumbs, H1 and public details snapshot compared against the pre-release render. Gallery and canonical territory labels retained.

Visual browser/responsive certification remains pending; these checks validate authenticated server rendering and source, not a new browser screenshot or Lighthouse score. Maintenance/private QA and deferred upload-service status remain unchanged.
