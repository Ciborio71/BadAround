# NF-UX-02B — Report v2 dual-stack backend foundation

Candidate implementation only. Native UI still emits v1. No staging deploy, real v2 submission, DB migration, taxonomy/GEO/Social/SEO/publication-policy change.

## Architecture

`BadAround_Report_Schema::VERSION` remains v1. Native intake and the media adapter explicitly dispatch by `schema_version`; the original normalizer/validator remain the v1 path. Existing private validation/normalization primitives were made protected, with no v1 algorithm change, so v2 can reuse time, GEO request, media metadata and consent validation. Results and storage echo the accepted version rather than a global latest constant.

V2 rules are read from the exact approved artifact in `contracts/nf-conditional-v1.0.json`, SHA-256 `dc282153243ffb80f3bd4f2a222ea0f4c4f42806fba6f3a0bde0a76855bd8e3c`. Runtime verifies the hash and uses a generic recursive evaluator for activation/requiredness. Unknown keys are rejected before strip; known inactive values are omitted from the final normalized snapshot. Active fields retain their meaning; invalid enum punctuation is not sanitized into a valid answer. All eight specification keys are kept private in storage/result metadata; candidates require a separate moderation decision before any public text use.

### Explicit technical envelope overlay

The frozen question matrix inventories `schema_version`/`submission_id` as hidden technical entries and contains the old v1 enum in the baseline metadata. They cannot be stripped from the canonical transport. NF-UX-02A's explicit discriminator and global UUID invariant apply as a technical overlay: both are required canonical envelope fields and v2 permits only the explicit v2 marker. The frozen artifact is unchanged. No other field state is overridden. The automated oracle includes all 2,805 assignments and separately applies this transport/identity overlay; it also preserves the certified coordinate-pair invariant when testing optional coordinates.

## Version binding and the certified media boundary

The F1.7 tests explicitly require durable media pin **before report reservation**. Creating an early report just to record a pin version would violate that certified boundary. A minimal infrastructure registry therefore uses the **existing WordPress options table**, with immutable `ba_report_version_<SHA256(UUID)>` rows, `autoload=no`. This is not a DB schema change and stores no report content/PII: version + full UUID, and a v2 canonical hash. The canonical snapshot remains in the existing private report JSON or pinned media intent. Reads/writes use direct SQL under the existing native UUID lock; no options cache API owns these private infrastructure keys.

This is an explicit storage-detail adjustment to NF-UX-02A's recommendation to use a report row at pin. It preserves both concrete certified constraints: pin-before-report and **zero media service/ledger lookup from no-media intake**. It does not add a new media saga or change descriptor/capability/ownership policy. Media pin acquires the same UUID lock after its existing media locks; native report code never acquires media locks in reverse. Failure after a durable v2 version/hash binding cannot free that UUID for another payload/version. New v2 report reservation inserts version and normalized canonical snapshot atomically into the existing report row. V1 reservations/recovery retain their canonical behavior; unmarked historical reservations remain exclusively on legacy v1 recovery.

Each v1/v2 report retry is compared with the existing canonical JSON equality mechanism. V2's deterministic field ordering and stale strip make stale-only/raw-object-key-order changes equivalent. Version change or active v2 value change after binding produces `duplicate_submission`, HTTP 409, no version overwrite. A busy lock remains `submission_in_progress`, HTTP 409/retryable. Media intent/receipt hashes still include the report version and cannot be repinned across versions.

## Historical gate and fixture

Read-only staging audit on 21 October 2026 found **0 rows** in `ba_native_media_sessions`; there are no historical media sessions/intents to repair in that audited state. Recheck immediately before any deployment/cut-over because sessions can be created later. A durable legacy intent without a shared version binding is a STOP condition for rollout, never an automatic data repair.

Report #35/event #312 read-only baseline: schema v1, pending, animal_missing/dog, damage=not_applicable. `SHA2(content_original,256)=ba4672ec075306b23eb2b6f9c1f68f15676d8c613d8884d87e1aa01db9a72197`. Recheck before handoff; never normalize, migrate or save it under v2. Public classification, URL, territory and GEO projections are unaffected.

## Verification

Dedicated CI runs the relevant current v1 suites, exact-baseline differential, new v2/cross-version tests, media tests on MariaDB and GEO/Social regressions. It does not use the obsolete F1.7 exact-file freeze workflow as authorization for a new backend feature. The historical `native-report-f1.php` references a WPForms mapper removed from this certified baseline; current canonical contract coverage is `native-report-f12.php` plus the Native intake/Golden Path/API suites.

The SQLite test harness now implements the certified GEO JSON existence probe, matching MariaDB semantics. Before this adapter correction, F1.7B/C fail at the Publication assertion even on the unchanged baseline because SQLite lacks `JSON_CONTAINS_PATH`; production Publication/GEO code was not changed. Tests retain their original assertions, including pin-before-report, fence, privacy and no-media ledger isolation.

Local targeted runs: 18 suites PASS; 3,404 new v2 assertions / 2,805 matrix assignments / 33 oracle cases / 8 specifications; 88 exact-baseline v1 differential cases and 3 HTTP/persistence/Golden Path flows; 324 frozen transport no-media scenarios; media F1.7B 169 assertions and F1.7C 74; 28 new media coexistence/pre-report version checks. Hosted CI uses real MariaDB constraints and must also pass before staging authorization is requested. No claim of browser v2 QA or live B3 certification is made.

## Deployment/rollback gate

Return candidate SHA, changed files, hosted CI identity/results, fixture/media audit and any risks before deployment. No staging deployment is authorized by passing tests alone. A later UI v2 launch is a separate task. Rollback after any persisted v2 must keep a dual-stack backend/readers and immutable UUID bindings; never rewrite v2 records to v1.
