# NF-UX-02C.2 — interrupted native event completion

Baseline application: `38c82e3472fc4adb0ab80f8fd1281373aa1ab1e0` (dual-stack NF-UX-02B plus C.1). No staging deployment in this task.

## Root cause

The shared persistence service returned immediately for a linked event. Its recovery branch found an event via `_ba_primary_report_id`, linked it, recorded completion, and returned before subtype/territory assignment. Therefore a process interruption after `wp_insert_post()` returned, before taxonomy initialization, produced a stable but incomplete recovered event (NF02C-RECOVERY-01 / CI 39644301742).

## Completion model and patch boundary

Only `class-badaround-report-persistence-service.php` changes at runtime. Schema, conditional matrix, repository/C.1, taxonomy registry/mapper, Native UI, media, GEO, Publication, Social and SEO sources are unchanged.

For an initializing native event, completion means: the persisted canonical snapshot matches; post exists and is `ba_evento`, pending/new; source ownership/UUID and existing initialization metadata agree; canonical category+subtype terms are present; exactly the deepest verified territory term is assigned; report/event linkage agrees. Only then is success returned. Verification also runs on normal new initialization.

Existing pending/new events are inspected before repair. Missing terms are filled, correct terms/meta remain unchanged, unexpected terms/meta or inconsistent linkage fail deterministically. Empty event-type assignment uses the existing canonical mapper; a correct partial type set and missing territory use append of missing existing IDs only. Unique metadata add does not overwrite a competing value. All required values are reread, then linkage is completed and verified. No canonical report persistence occurs in this helper.

Territory remains the intake-selected `territory_term_id`; the existing resolver remains fallback for direct service callers. Normal Native assigns one deepest term, not an explicit ancestor hierarchy. An ancestor-only/extra-ancestor assignment is incompatible, not silently converted into a new hierarchy contract.

The repair domain is initialization, not moderation. Already-linked events with established moderation/publication states preserve their historical retry behavior without taxonomy/meta repair or state reset. Unlinked noninitial events, unknown moderation state, wrong post type, metadata conflicts and ambiguous recovered candidates fail safely. WPForms/legacy paths are unchanged, including their existing duplicate/recovery behavior.

Media promotion/binding/reconciliation remains with the existing native media adapter and persistence wrapper; no new media ownership or publication semantics. The no-media path never needs a media session. No new completion marker, schema column, migration, taxonomy term or public field.

Errors use existing `persistence_failed` result wrapping, with internal `ba_persistence_event_completion_<reason>` source codes and no private values.

## Tests

- Exact original NF02C-RECOVERY-01 reproducer retained byte-for-byte (SHA-256 `e2b8d2850562033c6ee3f380df6f24eccef583511da9e2d582bc6527621e4071`); expected assignment calls 1→2 and same event ID.
- Seven failure/receipt boundaries, nine explicit partial states and ten conflicting states for each of v1/v2; count report/event, pending state, canonical bytes, IDs and links; complete retries perform no term/meta rewrites.
- Real production taxonomy mapper: empty/category-only/subtype-only/territory-only/complete sets, conflict, missing and duplicate canonical mapping.
- Exact pre-C.2 persistence implementation compared to the patched service for normal v1/v2 creation and completed retries. Generated report title/primary report ID excluded; canonical content, metadata, taxonomy, state and receipt semantics compared.
- Original C.1 direct immutable snapshot gate and regression, v1 validator/intake differential, v2/cross-version, no-media, positive media binding/recovery/fence, GEO and Social suites.

Test-only WP adapters now model metadata presence/unique add and taxonomy read/append. Two fault doubles delegate successful assignment to their parent instead of returning true without materializing terms. No existing assertions removed.

Fault injection is at service boundaries after `wp_insert_post()` returns with recoverability metadata. It does not certify an internal WordPress core crash before such metadata exists, nor the remaining full NF-UX-02C concurrency matrix. No production/staging writes or protected-fixture fault injection. Stop at pre-deploy gate.
