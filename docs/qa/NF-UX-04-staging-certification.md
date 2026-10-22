# NF-UX-04 — staging certification and protected QA fixtures

- Application candidate: `d0ac50bf4866b326943f3f4487b49fbe3d878d7b`.
- Dedicated CI: [39756049141](https://github.com/Ciborio71/BadAround/actions/runs/39756049141), PASS.
- Certified staging deployment: [39762760152](https://github.com/Ciborio71/BadAround/actions/runs/39762760152), PASS.
- Environment: staging.badaround.it only. No merge or production deployment.
- Only the five authorized theme runtime files were deployed. Core NF-UX-03A and backend dual-stack/C.1/C.2 remain byte-identical.
- Frozen JSON SHA-256: `dc282153243ffb80f3bd4f2a222ea0f4c4f42806fba6f3a0bde0a76855bd8e3c`.

## Protected QA fixtures

| Report | Event | Version / status | Protection |
|---|---|---|---|
| #35–#67, including residual #57 | Existing associations unchanged | Historical versions unchanged | Do not mutate, migrate, delete or publish as part of subsequent QA. |
| **#68** | **#344** | **badaround-report/v2 / pending (Da moderare)** | **New protected NF-UX-04 fixture. Do not mutate, convert to v1, delete or publish. Preserve it even if the frontend is rolled back.** |

Exactly one new report and one event were created. This is synthetic QA with no real PII or uploaded media and Social preference declined. Subtype animal_missing exercises hidden damage and animal.type=other with animal.type_other_detail. The same Native payload was retried unchanged: duplicate=true and the same event ID. Stored envelope and canonical schema_version are both v2.

## Certification evidence

- Runtime frontend/PHP differential: 33 oracles, 2,805 field/subtype combinations, 554 dependency activations (587 payload cases), PASS.
- Live browser QA: 365 checks; 320/390/1440 px, all eight specifications, stale clearing, summary/payload, keyboard/focus/CTA, all nine emergency triggers and danger yes/unsure, PASS.
- Smart Address, device geolocation at a synthetic test position, private exact point, canonical territory and both public-location requests, PASS.
- Real Native v2 REST submission and identical idempotent retry: PASS; event pending, no publication.
- Actual stored v1 canonical preparation and historical version reads: PASS.
- Post-upload isolated C.1 persistence: 74 assertions PASS; C.2 recovery: 500 assertions PASS.
- Historical reports/events/metadata, protected #35–#67, canonical term registry and normalized table schemas: identical before/after.
- Staging media remains fail-closed; positive media binding stays covered by dedicated CI.
- Temporary token-protected read-only probes were removed after every invocation.

[Byte-exact rollback artifact](https://github.com/Ciborio71/BadAround/actions/runs/39762760152/artifacts/12418917186) and [final certification evidence](https://github.com/Ciborio71/BadAround/actions/runs/39762760152/artifacts/12418492674) contain the five remote hashes and complete checks.

Two prior attempts exercised byte-exact theme rollback because of test assertions (coordinate string/number comparison and HTML single-line input normalization). Two intervening pre-upload runs stopped on HTTP 403 for the diagnostic path. The probe was moved to normal WordPress REST bootstrap without weakening security. No report/event was created in those attempts, and no application candidate changes were made.

NF-UX-04 COMPLETE — NATIVE CONDITIONAL V2 CERTIFIED ON STAGING
