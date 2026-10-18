# SOCIAL-PREF-01 — staging evidence, 2026-10-18

Application: `7fb46ba212ab5ed1673d01bec9193c0d6e520637`.
Staging release: `16b4c67180f6b768b1bc2fedfe4efbd9dad223a2`.
Baseline: `3a133c75310095c16920679140e342a801ddf183`.
Run: https://github.com/Ciborio71/BadAround/actions/runs/39148510939

## Result

- Local and CI JavaScript/DOM: 159 passed, zero failures, including optional social request tests.
- Nine PHP suites passed, including real normalizer/validator/intake/repository and private moderation rendering against a simulated database; no certified fixture changed.
- All Core/theme PHP syntax and Native JavaScript syntax passed.
- Chromium UI checks: 320, 390, 768 and 1440 px; six steps, focus/keyboard, no overflow, touch targets and validation; GPS/map/marker regressions passed.
- Full flows at 390 and 1440 px: vehicles/112/anonymous with social request, vehicles/pseudonym with refusal, property theft, missing animal and lost documents with omission. Ten scenarios on Chromium plus ten on WebKit; source-backed fixtures and simulated Google/GPS/HTTP 201, not physical-device or live-provider certification.
- Required declaration, contact preference and optional social request remain independent. Reset has no social default. Exact retry creates no duplicate; different preference under the same UUID is rejected without overwriting intake evidence.

## Transfer and active verification

Native job `117737191141`: success. First deploy job `117738068380`: frontend FTPS ECONNRESET; automatic rollback transfers also failed. This attempt was not certified. The same deploy job was rerun without changing reviewed code or rerunning the already successful test gate.

Final deploy job `117738340481`: success, both Core and frontend uploads succeeded. Four active sources were read through authenticated wp-content access and compared against the reviewed release, matching exactly after trailing-whitespace normalization:

- `plugins/badaround-core/includes/class-badaround-report-schema.php`
- `plugins/badaround-core/includes/class-badaround-moderation-admin.php`
- `themes/badaround-child/inc/native-report.php`
- `themes/badaround-child/assets/js/native-report/model.js`

Public staging GET `/segnala-un-evento/?social_pref_qa=16b4c67` returned the Native configuration with the Italian social question, `required=false`, `requested/declined` choices, `social-preference-v1`, six steps, and no visible `consents.social_visibility_version` field. No request was submitted to the live intake endpoint.

## Scope and limits

Only four runtime files transferred; no production deployment, DB schema migration, live database mutation, media backend change, Smart Address logic change or Publication Service change. New fields remain private canonical JSON and are absent from public projection. Existing reports without a preference imply no request.

No social accounts connected, jobs created or posts sent. Changes/revocation after submission and execution-time enforcement remain prerequisites of the future Social posting service; this patch only records an intake request for moderator review.
