# GEO-PREC-01 — staging STOP and verified rollback

Candidate: `48bab2229c00fb28f01d54117dd1278074f10d69`  
Source: `feature/geo-prec-01-user-authoritative`  
[Deployment run 39874856966](https://github.com/Ciborio71/BadAround/actions/runs/39874856966): **FAIL — actual public consumer regression**.

## Current staging

The authorized seven Core files were uploaded and verified against the candidate.
Total runtime custody: 53/53 PASS, including Home, Native v2, Core bootstrap,
backend and frozen JSON. After the browser failure, all seven files were restored
byte-exact from the pre-upload backup; final custody again passed 53/53 against
baseline `a4ef16aa067afc94a96cacd55a46a62d1248ed93`.

GEO-PREC-01 is **not active/certified** after rollback. Native remains v2;
NF-FINAL-01 remains ON HOLD at `b4aca00fe7acea5b80aa8b45d423ab915fa071e3`.
No theme upload, application patch, production operation or merge occurred.

## Reproduction and confirmed defect

1. New Native v2 report #73 / event #352 requested exact_point.
2. Envelope persisted public_geo_governance = user-public-location/v1.
3. Whole-report approval persisted a sealed exact projection, radius 0, without
   a moderator geographic decision.
4. Real cloud Chromium loaded the published detail before rollback.
5. Expected: “Posizione esatta”, DOM data-ba-event-mode="exact_point".
6. Actual: “Posizione approssimativa”, DOM data-ba-event-mode="", radius "0".
   The map marker accessibility label also called the exact point approximate.

Screenshot: `GEO-PREC-01-event352-exact-rendered-approximate.jpg`.

Root cause is confirmed in the unchanged deployed
`wordpress/themes/badaround-child/template-parts/single-event-layout.php`.
Its approved-projection reader is gated only by a nonempty
`_ba_public_location_decision`. New governance intentionally creates no moderator
geo decision. The detail therefore bypasses the sealed projection's mode and
falls back to legacy public coordinate mirrors with an empty mode.

This is a consumer integration regression. The semantic suites did not render
this exact PHP template against a governed report without that historical meta.
No automatic fix was made: the template is outside the seven authorized files.

## Results observed before rollback

| Gate | Result |
|---|---|
| Cutover marker and Native v2 canonical persistence | PASS |
| Reporter choice read-only in actual moderation SSR | PASS |
| Moderator mode/radius/coordinate controls absent | PASS |
| Whole-report approval and rejection | PASS |
| Empty rejection reason denied | PASS |
| Crafted exact/approx/same-mode/unsupported decisions | BLOCKED |
| Injected proof metadata write | BLOCKED |
| Unknown marker validation | FAIL-CLOSED; in-memory test, no stored fixture rewrite |
| Ordinary moderation save preserves request/projection | PASS |
| Persisted exact -> exact, radius 0 | PASS |
| Persisted approximate -> approximate, existing system radius 1000m | PASS |
| Identical Native retry | PASS: same #73 / #352 |
| Public exact detail mode and label | FAIL |
| Private address/PII leakage | NONE observed |
| Private report row during moderation/publication | UNCHANGED |
| Historical report/event/meta/relationship/audit digests | UNCHANGED |
| DB schema and canonical taxonomy registry | UNCHANGED |

Initial custody covered all existing reports through #72 and events through post
ID 351, including protected historical GEO fixtures and #35–#71. Taxonomy registry
comparison excludes usage counts legitimately changed by the two authorized
publications. Schema hashes normalize AUTO_INCREMENT counters and cover every
prefixed table.

Historical #31/#303 and #32/#304 remain unmarked/published with identical snapshot
hashes. #68/#344, #69/#345 and #70/#346 remain pending. #71/#347 was already
published at this run's initial read and was preserved exactly; this task did not
publish it or rewrite its historical registry entry.

## New protected fixtures

| Case | Report | Event | Request | Stored state retained after rollback |
|---|---:|---:|---|---|
| A | 73 | 352 | exact_point | publish / published |
| B | 74 | 354 | approximate_area | publish / published |
| C | 75 | 356 | exact_point | pending / rejected |
| D | 76 | 358 | approximate_area | pending / rejected |

All are synthetic, Native v2, marked user-public-location/v1, no real PII/media,
Social declined. Do not delete, convert, re-moderate or reuse UUIDs. A/B publication
was necessary for the authorized proof. Rollback restored files only; snapshots,
projections and audit were retained, not converted or migrated. Their new proof
format is retained for investigation and is not certified under the restored
baseline. See the accompanying protected-fixtures register.

Outbound Sentinel scheduling was suppressed only inside the two synthetic QA
publication requests, then restored immediately. No persistent notification or
Social configuration changed.

## Regression evidence and limits

Pre-deploy run 39871909912 passed 134 Node tests, v1/v2/33 oracle paths,
2,805 matrix entries, 554 activations, damage, Altro, emergency, Smart Address,
media and consumer fixture tests. Frozen contract hash remains
`dc282153243ffb80f3bd4f2a222ea0f4c4f42806fba6f3a0bde0a76855bd8e3c`.
Post-deploy C.1 persistence (74 assertions) and C.2 recovery (500 assertions) passed
against the pinned sources. Live historical v1 validation and hosting media
fail-closed passed. None of these overrides the real public-detail failure.
Remaining live consumer QA stopped at that failure; it is not claimed complete.

QA-only run 39875721362 corrected an instrumentation error: the public REST response
intentionally omits private report_id, so the temporary probe was changed to resolve
the report by submission UUID. It reused #73/#352 without a replacement submission
or application change.

Existing Native copy still says moderators may reduce precision. It was recorded
and left unchanged under NO NATIVE UX CHANGE; separate copy alignment is needed
when governance is revisited.

## Cleanup and evidence

- Seven-file rollback BYTE-EXACT VERIFIED; final custody 53/53 PASS.
- Temporary administrator+token probe removed; FTPS absence verified.
- Endpoint verified 404 rest_no_route; temporary QA option verified absent.
- Local temporary token removed after cleanup verification.
- No permanent endpoint, security change, real media upload or credentials in artifacts.

Deployment artifacts (all downloaded and SHA-256 verified):

- 12468416011: pre-upload rollback.
- 12468471679: candidate upload hashes.
- 12467429275: rollback and C.1/C.2 evidence; SHA-256
  `d07f5f94162ce792b07c3d235fc3f42de07fd548042a6f96980ab4a67b1f50ec`.

## Verdict

**GEO-PREC-01 STOP — USER GEO GOVERNANCE NOT CERTIFIED ON STAGING**

No GEO-AREA-01, map-controls work or NF-FINAL-01 resumed. No merge or production.
