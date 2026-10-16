# SF.8 — National Territory Reconciliation (No deploy)

Baseline: SF.7 certified; source branch feature/f1-15b-sf7-aq-territory-alias.
Source of truth: current canonical `ba_territorio` registry, with 20 regions, 110 province/UTS and 7,894 municipalities at ISTAT 2026-02-21 baseline.
Original report v1 and public projection are immutable.

## Exact prior matching order
1. Require Google Places address component `country` (longText or shortText: Italia/IT).
2. `administrative_area_level_1` longText/shortText matched via Unicode NFD + case-insensitive text against global root canonical terms.
3. `administrative_area_level_2` longText/shortText; strip certain Province/Metropolitan City prefixes; hard-coded Lazio/Roma Capitale replacement and Abruzzo/AQ => L'Aquila replacement.
4. `locality` longText/shortText concatenated with `administrative_area_level_3`, looked up under canonical province.
5. `sublocality`, `sublocality_level_1`, `neighborhood` under canonical municipality; locality optional.
6. At every level 0 or >1 candidates fail closed except locality (0 means empty).
7. The input form retains private exact street and coords, without publishing these values.
8. Native server `BadAround_Territory_Resolver::resolve_path()` validates the canonical **exact-name+parent hierarchy**.
9. A separate legacy server `resolve()` still contains address-substring heuristics; the SF.8 Native Smart Address adapter never calls that method, and it is outside this scope.

## Root causes
- ISTAT `_ba_istat_region_code`, `_ba_istat_province_code`, `_ba_istat_municipality_code` exist in DB, but Google Places does not supply these ISTAT IDs.
- Step 2 UI's registry currently only includes `id,parent,name`, not authoritative abbreviation aliases.
- Google province `shortText` is an alphabetic sigla, while ISTAT `_ba_istat_province_code` is numeric (not a sigla).
- The former hard-coded AQ alias handled just one area and omitted most metropolitan/special-statute cases.
- Canonical names can be bilingual, hyphenated, include curly apostrophes, have definite articles, or differ from Google's administrative display labels.
- Multiple Google locality/admin-level components can disagree: never choose first match.
- Canonical `ba_territorio` is the only allowed source for valid public territory names and parent identities.

## SF.8 model
1. Reject foreign/missing country; carry `country:'IT'` in resolver output only (not a new F1.2 field).
2. Resolve region uniquely via normalized aliases against root canonical terms (bilingual alternatives are canonical slash-separated labels).
3. Normalize province Google labels and code, using a verified **region-scoped 110-row sigla crosswalk**. Reject obvious conflicting long/short hints. Require exactly one actual canonical province child of the resolved region.
4. Resolve municipality independently from `locality` and `administrative_area_level_3` under that exact province; reject conflicting or ambiguous candidates; do not infer municipality from Google street free text.
5. Resolve optional sublocality only under the exact municipality; ambiguous sublocalities fail closed.
6. Return the exact canonical names and IDs; the Native server independently validates the names and ancestry against live taxonomy.
7. On failure, retain existing UI manual-entry gate; no invented identity or coordinate injection.

## Normalization
Unicode NFD diacritics, case-insensitive Italian locale, Unicode apostrophe variants, hyphen-like characters, repeated whitespace, verified administrative prefixes and region-scoped province abbreviations. Slash-separated canonical bilingual denominations are alternatives only when already present canonically. Leading `l'` and `la` have deterministic article-free alternatives, still constrained under parent and still rejected if ambiguous. No edit-distance, fuzzy, global fallback or nearest-match mapping.

## Verified crosswalk sources
- ISTAT official denominazioni e sigle provinciali, 2017: https://www.istat.it/it/files/2011/01/Modulo-di-rilevazione-SEPARAZIONI-DIVORZI-ex-art-12.pdf
- ISTAT SITUAS update 2026-06-18: https://situas.istat.it/web/ ; Sardinia OT / VS / OG / SU.
- ISTAT 2026 territory revision: https://www.istat.it/classificazione/codici-dei-comuni-delle-province-e-delle-regioni/
- Exact name/region crosswalk checked against 110 current canonical province/UTS terms via read-only WP SQL. No term mutations.

## National QA
- 110 province/equivalent canonical entries across all 20 Italian regions.
- 64 real canonical municipality cases (includes Napoli and Rocca di Mezzo) + optional Torvaianica locality; fixture is a read-only canonical registry snapshot.
- 110 deterministic synthetic-child checks to validate abbreviation-to-existing-province identity only (never inserted into WordPress).
- Metropolitan, multilingual, mountain and autonomous, small, Sardinia 2026, region/province/name constraints.
- Negative tests: missing hierarchy, province-region conflicts, municipality/province mismatch, nonexistent name, ambiguous duplicate, locality-as-municipality, foreign country, stale Sardinia entity, conflicting Google long vs short.
- SF.6 legacy browser regression test preserved unchanged; SF.7 unchanged; Native DOM suite unchanged.
- CI: https://github.com/Ciborio71/BadAround/actions/runs/38964168419 — 67/67 pass including existing tests.
- PHP untouched; JS syntax PASS. Frozen-core scope guard PASS.

## Performance
The current Native config still serializes approximately 8,000 `ba_territorio` entries into browser HTML. SF.8 adds a compact province alias crosswalk but does not solve this transfer-size cost; defer to SF.10.

## Gates
NO staging deploy. NO production deploy. NO F1.2 changes, no DB or taxonomy mutations, no fixture mutations, no public coordinate/privacy changes.
The 64 live-registry component fixtures are synthetic Google payloads, **not** 64 independent live Google API calls. Real browser QA for all future special cases remains a separate staging gate after authorized deployment.
