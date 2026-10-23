# GEO-PREC-01 protected synthetic staging fixtures

This register supplements previous registries. Existing #35–#71, residual #57,
historical GEO fixtures and all existing rows through #72 were preserved.
Do not rewrite their history.

| Case | Report | Event | Schema | Governance | Request | Retained state |
|---|---:|---:|---|---|---|---|
| A | 73 | 352 | badaround-report/v2 | user-public-location/v1 | exact_point | publish / published |
| B | 74 | 354 | badaround-report/v2 | user-public-location/v1 | approximate_area | publish / published |
| C | 75 | 356 | badaround-report/v2 | user-public-location/v1 | exact_point | pending / rejected |
| D | 76 | 358 | badaround-report/v2 | user-public-location/v1 | approximate_area | pending / rejected |

Synthetic, no real PII/media, Social declined. Territory: Lazio / Roma / Pomezia /
Torvaianica. A/B publication was expressly required for this test. Files were
rolled back after a detail-mode regression; these snapshots, projections and audit
remain stored without conversion/deletion.

READ-ONLY. Do not delete, publish/unpublish, re-moderate, convert to legacy/v1,
change coordinates/mode or reuse UUIDs. Future mutations need explicit authorization.
Stored new projections are not certified under the restored baseline. See
GEO-PREC-01-staging-stop-report.md.
