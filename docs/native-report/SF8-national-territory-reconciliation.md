# F1.15B-SF.8 — National Territory Reconciliation

## Scope

Branch-only Smart Address reconciliation and regression suite. **No staging or production deployment**, no taxonomy/DB writes and no F1.2 / REST / privacy / publication edits.

## Before SF.8: observed matching order

1. Country component must be IT / Italia.
2. Google `administrative_area_level_1` longText/shortText is compared to root `ba_territorio` names.
3. Google `administrative_area_level_2` longText/shortText is compared to canonical province children, with basic prefix stripping and only a special `AQ -> L'Aquila` alias.
4. Google `locality` and `administrative_area_level_3` names are compared to children of the resolved province.
5. Google `sublocality`, `sublocality_level_1`, `neighborhood` are compared to child localities.
6. Multiple or missing required matches fail closed. The current manual fallback is untouched.
7. On success only canonical names go to frozen Native Report fields; private exact address/coordinates remain separate.

Old JS serialized all ~8,025 canonical terms with ID/parent/name, **not** their ISTAT codes. Casing and accent normalization, partial province prefixes, and apostrophe normalization were present. No general abbreviation reconciliation or international mapping.

## New deterministic matching order

1. Validate Italy via the Google country component; set ephemeral internal resolver result `country:'Italy'` (no new report field).
2. Resolve exactly one canonical **region** under parent zero using conservative orthography and canonical bilingual variants.
3. Resolve exactly one canonical **province/UTS** under that region. Match either:
   - canonical name / known bilingual name / normalized administrative prefix;
   - province code `code` (existing WP `_ba_istat_province_code`) using Google `administrative_area_level_2.shortText` and a **verified 2026 UTS-code → vehicle sigla lookup**; or
   - verified institutionally equivalent alias attached to a particular UTS code (currently `258 -> Roma Capitale`).
4. Resolve exactly one canonical **municipality** under the selected province, only from Google `locality` or `administrative_area_level_3`. If these identify different real municipalities, reject.
5. Resolve an optional existing canonical child **locality**, from Google sublocality/neighborhood or, if level_3 confirms the Comune, a differing Google locality. A locality is **never promoted** into the municipality slot by address text.
6. Reject any unresolved/ambiguous required level. No global first-name match, no fuzzy match, no nearest location, no geocoding fallback.
7. Emit unchanged canonical `region/province/municipality/locality` strings and existing exact private location fields. Existing WordPress `resolve_path()` remains authoritative downstream.

## Sources and identity boundary

Canonical names and parents: live `ba_territorio` taxonomy. ISTAT codes: already stored `_ba_istat_region_code`, `_ba_istat_province_code`, `_ba_istat_municipality_code`. WordPress presentation now includes **only 110 province UTS codes** alongside existing serialized province terms (no new municipality codes to browser). The server still checks exact canonical strings.

External sigle: `guglielmo/geojson-italy`, commit `ba3347df3a53352891109f8198c597a0273684c0`, `geojson/limits_IT_provinces.geojson` (`prov_uts_code` / `prov_acr`). Before embedding, **110/110** code rows were joined read-only to the staging canonical province term metadata and validated by region. This includes 2026 Sardinian UTS codes `312/318/113–119` and autonomous regions.

The only institution-specific name alias is `Roma Capitale` for province UTS code `258`; official entity: https://www.cittametropolitanaroma.it/homepage/la-citta-metropolitana/. It is not a global suffix-removal rule.

No extra aliases are invented when canonical data or official mappings lack sufficient evidence. The Italy-only resolver does not support international expansion.

## Normalization boundaries

Case, diacritics, typographic apostrophe, dash/hyphen and repeated whitespace are normalized. Administrative prefixes such as `Provincia di`, `Provincia autonoma di`, `Città metropolitana di`, `Libero consorzio comunale di` are removed only at province matching; articles of canonical names (e.g. `Provincia dell'Aquila` → `L'Aquila`) are retained. Canonical region/province labels containing slashes expose their existing bilingual components as strict alternatives. All matching is parent-scoped; if normalization makes two children coincide, both match and the result is rejected. A source abbreviation is not treated as a canonical ISTAT identifier: it can only select an existing province code under the independently confirmed region.

## Tests

`tests/f1-15b-sf8-national.cjs` contains 61 canonical real municipality cases, all 20 Italian regions and 52 UTS codes in the positive matrix, plus institutional/bilingual, Naples/SF.6 and Rocca di Mezzo/SF.7 regressions and negative identity-conflict tests. Names, IDs, parents and existing province codes were queried read-only from the staging registry on 2026-10-16.

The workflow `.github/workflows/f1-15b-sf8-national.yml` runs JS syntax, PHP syntax for the single touched presentation file, this matrix, existing Smart Address unit and DOM tests, Native wizard DOM regressions, and a frozen-core diff guard.

## Performance / SF.10 fence

The **existing full ~8k term registry** is still serialized into the Native form. SF.8 does **not** solve payload weight. Addition is restricted to a 110-entry compact UTS code-to-sigla map (~2 KB) and a `code` attribute only for canonical province entries. Any larger optimization or lazy lookups belong to SF.10.

## Out of scope and residual risks

Fixtures model explicitly specified Google component variants; **61 simulated locations do not prove live Google behavior in all municipalities**. Browser-real regression and a later staging integration gate are required before deployment. Google sometimes omits administrative components; those remain manual/fail-closed by design. No new public fields, geolocation exposure or modified privacy options.
