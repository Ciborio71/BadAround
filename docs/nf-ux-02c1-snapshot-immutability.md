# NF-UX-02C.1 — reserved native snapshot immutability

Baseline: `1f9e1cc56f120dec6f634fc2ac6233e803623b88`. No deploy in this task.

## Root cause and callers

`persist_canonical_private_data()` previously updated by report ID alone. It did not consult the native reservation envelope, UUID source identity, or version binding. The intake guard could reject HTTP conflicts, but direct repository calls could replace canonical content and version (NF-UX-02C CI 39604391785).

Callers are native intake, shared report intake, and `persist_normalized_private_data()` for historical WPForms. The latter still constructs its historical payload and delegates; non-native rows retain their existing storage behavior. Native media persistence reads the canonical snapshot after intake; it does not require replacing it. No intake, media, frontend, GEO or Publication algorithm is changed.

## Slot boundary

- Bare native row: historical v1 reservation; first v1 canonical persistence allowed, implicit upgrade to v2 refused.
- V2 reservation: canonical envelope inserted atomically at reservation. Canonical identity is already committed, while private SQL projections still need their first materialization.
- Native canonical envelope present: same version, UUID and approved normalized fingerprint required. Identical calls can materialize/refresh private projections, but preserve `content_original` bytes and its hash. Different active data/version/UUID is refused with boolean false before any write.
- V2 direct input is prepared through the existing registry, so key ordering and inactive known values normalize before comparison; unknown keys stay rejected. V1 normalization rules are not modified.
- A supplied native storage envelope must agree with canonical/version/UUID. Once reserved its alternate metadata is not serialized over the existing envelope. The legacy writer cannot replace a native snapshot by omitting version.

Comparison uses the same SHA-256(JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) normalized canonical identity already used by native idempotency, factored into a shared private helper. Generated request IDs/timestamps/SQL IDs are not part of equality.

## Atomic update and retry

The update compares the observed `content_original` and `payload_hash` in addition to row ID. NULL values remain SQL NULL for an empty slot. If another writer changes the slot, a zero-row update succeeds only if rereading proves the same native canonical identity and version; a different winner conflicts. It does not depend on a direct caller holding the intake lock.

Identical repository retries retain the existing metadata update behavior for `updated_at`, `consented_at` and IP hash/private SQL projections; canonical envelope bytes never change. HTTP completed retry remains handled by the frozen service before this persistence call. There is no new audit event/error envelope. Public HTTP errors remain those of the existing intake checks.

## Verification scope

Retained NF-UX-02C direct gate (with UUID lock held); focused SQL repository/recovery/HTTP tests; v1 differential against exact NF-UX-02B and prior v1 baseline; existing v2, no-media, positive media integration, GEO, Social and publication regression. Test-only GEO/Social DB mocks now expose their initial native slot and array reads, without removing assertions.

No schema migration, historical rewrite, native v2 UI switch, staging write, deployment or merge. Full NF-UX-02C crash/concurrency certification is not resumed by these focused tests.
