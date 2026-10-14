# GEO-IT-01 — Verified official municipality dataset (21 February 2026)

Source: user-provided original ISTAT `Elenco-comuni-italiani.xlsx`, sheet `CODICI al 21_02_2026`.
Official registry reference: https://www.istat.it/classificazione/codici-dei-comuni-delle-province-e-delle-regioni/

SHA256 original XLSX:
`83842076860450f7e482daecea6b7a769f5f93d0bf5b0d48802b44896d7a26d5`

Converted CSV (7 columns, UTF-8, semicolon-separated, LF, preserving leading zeroes):
`GEO-IT-01_ISTAT_2026-02-21.csv`

SHA256 converted CSV:
`d33642d067b70060dd4e03a8504742e784908591e1e54b67cd7e486daeeb50f7`

Validated inventory:
- Municipalities: **7,894** (7,894 distinct 6-digit ISTAT codes)
- Regions: **20**
- Current supermunicipal statistical units: **110** (includes the 2026 Sardinian reorganization)
- Missing required canonical columns: **0**
- Duplicate municipality codes: **0**
- Roma municipality `058091`; Pomezia municipality `058079`.
- Lazio province `Roma` currently uses ISTAT territorial-unit code **258** (not the deprecated historical provincial code).
- Both province and municipality may legitimately have name `Roma`, at different hierarchy levels.

Security/data governance:
- Never extract municipality codes from numeric Excel values; preserve the original six-character `Codice Comune formato alfanumerico` field.
- Do not use `Codice Provincia (Storico)` for current provinces; use the supermunicipal column B.
- Do not delete/rename/reparent existing `ba_territorio` terms or mutate #260/#269/#277.
- Import remains **NOT AUTHORIZED** until source hash and staging dry-run are verified, full DB backup is confirmed, and a rollback/recovery strategy is approved.
- Standalone CSV is generated as a conversation artifact and **is not committed to the GitHub repository**; upload it to a controlled server location before explicitly authorized WP-CLI execution.

## Follow-up compatibility checks
- New region values `Trentino-Alto Adige/Südtirol` and `Valle d'Aosta/Vallée d'Aoste`, and province `Bolzano/Bozen`, may not match Google Place address-component aliases. Add explicit, audited locale alias handling before claiming fully nationwide **automatic** Smart Address reconciliation.
- The current public form inlines all `ba_territorio` terms into `smartAddress.territories`; benchmark payload size/time and consider on-demand registry before production deployment.
- The current WP-CLI importer has dry-run limited to validating the file and reading existing term count, not a complete impact/collision plan. Do not represent it as a certified import plan.
