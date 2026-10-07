# F1.7B — Native media backend foundation candidate

Base: `release/mvp-reconciled` at `bcc2c74ca3391f1d16f9b1eb9719650ea4d27952`.
This branch is a backend review candidate. No deployment, release integration,
F1.6 Step 5 changes, public cut-over or live records are authorized by this delivery.
Event 261 and the frozen F1.6 staging artifact are untouched.

## Contract boundaries

F1.2 schema/validator/normalizer, F1.3 intake/idempotency/repository, the F1.4
Golden Path and shared persistence implementation, and the entire Theme remain
unchanged. F1.5 has a conditional extension: absent, empty or malformed non-array
`media.items` goes directly to the existing Golden Path; non-empty arrays first
pass the frozen read-only validation before a media adapter is constructed.
The adapter repeats authoritative validation for direct internal callers.

Reports remain JSON with `X-BadAround-Intake: badaround-report/v1`.
Media-bearing reports additionally require `X-BadAround-Media-Capability`, never
the media marker. UUID descriptors remain canonical; numeric final media IDs
exist only in the internal private linker copy. No capability enters canonical
JSON or audit records.

## Routes

All media routes require `X-BadAround-Media: badaround-report-media/v1`, HTTPS,
same-origin checks and Fetch Metadata checks. Cookies do not prove ownership.
Responses have a singular `error`, private no-store caching, nosniff and request
ID headers. Rate limits return 429 with Retry-After.

| Route | Request | Result |
| --- | --- | --- |
| POST `/wp-json/badaround/v1/report-media-sessions` | JSON `submission_id`, `creation_nonce` | 201 first create; 200 exact nonce replay, same capability/deadlines |
| POST `/wp-json/badaround/v1/report-media-sessions/{session}/items` | Multipart `client_upload_id`, exactly one `file`, capability header | Verified descriptor; same key/bytes replays, changed bytes conflict |
| GET `/wp-json/badaround/v1/report-media-sessions/{session}` | Capability header | Owner-only states/descriptors/receipt, no originals, URL, path, EXIF or capability |
| DELETE `/wp-json/badaround/v1/report-media-sessions/{session}/items/{media}` | Capability header | Pre-pin tombstone; quota released only when the original is absent/deleted |

Session UUIDs and upload keys use lowercase canonical RFC UUID representations.
Creation nonce is 32 random bytes encoded as canonical unpadded base64url.
Capability is HMAC-SHA256 using a dedicated configured server key, domain and
operation separation, key ID, both UUIDs, nonce hash, independent server salt
and all fixed deadlines. Only verifier hashes are persisted. Knowing a
submission UUID cannot recover its capability.

## Migration and versions

Core **0.17.0 → 0.18.0** and installer schema **1.8.0 → 1.9.0** correspond to
four new native-only tables: `ba_native_media_sessions`, `ba_native_media_items`,
`ba_native_media_upload_keys`, `ba_native_media_limits`. Existing final media
`report_id` remains NOT NULL; preuploads never use report ID 0 or final rows.
UNIQUE constraints enforce submission/session identity, UUID/item identity,
session/client upload identity and UUID-to-final-row mapping. Row-version CAS
and named locks protect pin and completion receipt mutations.
The canonical report schema remains `badaround-report/v1`.
Only CI version assertions change. **Deployment workflows are unchanged**;
their version gates require independent review before any future candidate
deployment. This branch cannot simply be dispatched through today's launcher.

## Storage, decoding and quotas

Private storage must be the same real archive resolved by the existing moderator
inspector (`BADAROUND_PRIVATE_MEDIA_PATH` / existing private-path filter), outside
all configured document roots and WordPress roots. Root and child directories
must be owner-only, with no symlink components. Files use exclusive creation,
0600 permissions, actual stream byte counts and SHA-256. Deterministic final
paths under `report-{report_id}` permit recovery after rename but before DB
finalization. Failure to delete retains the reservation; there is no public URL.

Five canonical files, 5,242,880 bytes each, at most 25 MiB accepted per session.
Incoming streams reserve the full per-file ceiling before file creation.
Global temporary/archive capacities have **no assumed hosting default**.
IP/session attempts, failures, bytes, concurrency and session counts are
configurable centrally. Accepted images remain separate from invalid/removed
tombstones. Same-session identical content may alias upload keys; no global
deduplication or cross-submission content oracle exists.

GD decoding runs in a separate trusted PHP CLI worker with memory/address-space,
CPU, file-output, descriptor and parent timeout limits; network functions and
URL wrappers are disabled. There are no ImageMagick delegates. Probe actually
encodes/decodes JPEG, PNG and static WebP. APNG/animated WebP, multiple WebP image
chunks, SVG/GIF/XML/PDF/archives/executables, filename/type mismatch, malformed
containers, oversized dimensions and pixel bombs are rejected. Each side is
limited to 10,000 pixels and total pixels to 25,000,000 with overflow-safe checks.
**HEIC/HEIF is unsupported by this worker** and excluded from effective advertised
formats; its frozen canonical enum is not changed.

## Intent and recovery

Frozen validation → capability/ownership/file/descriptor preflight → short
immutable canonical+ordered-manifest pin → release all media locks → F1.3 →
F1.4 native lock → session lock → media UUID locks in sorted order.
Upload writers have separate lease locks. Decoder work does not hold session
locks. Admission and quota locks are never taken from the persistence adapter.

The adapter journals promotion targets, recovers existing final rows by the
deterministic basename, translates UUIDs into numeric linker IDs, and always
reconciles after shared persistence, including its existing-event early return.
Success requires one report/event, pending moderation, the exact pinned private
set, verified descriptors/checksums, `pending_native_review`, no public attachment,
and a durable receipt. Extra/missing/cross-report rows fail closed.

Recovery A–E covers completion, partial binding, promotion failure, rename/DB
failure and existing event/receipt failure. The completed path validates the
verifier, pinned canonical fingerprint and stored receipt/mappings without
calling F1.3/F1.4, reading original bytes, changing moderation or extending TTL.
It can return 200 duplicate after write expiry. An intent admitted before another
worker completes is also safely recognized under the native lock.

Expired clients cannot start/restart incomplete writes. Internal recovery can
replay only a durable pinned canonical intent; it does not create a new grant.
Hourly maintenance has bounded batches and state/lease/version rechecks under
locks. Open orphans expire at 24h; interrupted writers use 15-minute leases.
Pinned/binding/bound originals are never orphan-collected. Failed deletion or
corrupt intent needs operator attention and retains evidence/capacity.

## Publication fence

Native provenance is taken from server-side reports, media rows and event meta,
not a client assertion or review-state name alone. Native originals cannot use
legacy direct approval, batch approval, raw materialization, first publication,
already-published recovery, or the WordPress publish override filter. Missing or
inconsistent provenance fails closed. This fence is active independently of
upload enablement. WPForms and native no-media events retain their old paths.
Native moderation/redaction/derivative publication is deferred to a separate task.

## Operational preflight — PENDING

`BADAROUND_NATIVE_MEDIA_CONFIG` defaults disabled. Explicit enablement requires:

- A dedicated server-only >=256-bit key source, active key ID and retained replay
  keys; no secrets are committed or copied into report JSON.
- Verified private archive/document-root routing, owner-only permissions, atomic
  rename, capacity and backups. **Code contract PASS; hosting leak proof PENDING.**
- Confirmed temporary and permanent budgets, disk reserve, edge/PHP request
  buffering budgets and intended IP attribution. No automatic 1 GiB assumption.
- Trusted CLI PHP, GD and POSIX extension paths, functioning process isolation,
  effective codecs, and parent PHP fileinfo. Web requests also require effective
  upload/post limits at least 5 MiB / 5 MiB plus multipart overhead.
- A reliable hourly scheduler; WP-Cron registration is conditional on enablement.
  **Scheduler operationalization PENDING**, including alarms and retention planning.
- Independent deployment-gate/version review before a future staging candidate.

Staging read-only site information confirmed PHP 8.3.35, but did not prove its
decoder, PHP upload limits, process permissions, private routing or scheduler.
No server, Cloudflare, nginx, PHP, cron, secret or staging configuration was changed.

## Automated evidence and coverage

`tests/native-media-f17b.php`: 108 local assertions using production classes and
SQL constraints (SQLite local fallback; full CI uses MariaDB 10.11). Covers
session replay/nonce/capabilities/expiry, stream limits and actual GD decode,
ownership, upload deduplication, immutable pin, A–E injected failures, completed
read-only retry, expired durable-intent recovery, cleanup/lock races, constraints,
publication fences, actual WPForms field-63 ingestion/approval and legacy
already-published recovery.

`tests/native-media-no-media-differential.php`: 144 comparisons against the
exact Git object of frozen F1.5, with production F1.3/F1.4 and SQL repository.
Absent/empty/malformed shapes, every availability enum, validation, retries,
completed retry, changed payload, concurrency, persistence failure and recovery
retain HTTP/envelope/canonical/count/moderation behavior with zero media ledger
or coordinator access. Only generated identities/timing are normalized.

Existing F1.1–F1.6 PHP/SSR, 20 production JS/DOM tests, D1–D3, E1–E5,
Golden State and Recovery invariant checks remain mandatory in full CI.
These automated tests do not establish a live endpoint or hosting privacy proof.

Deferred: Step 5 UI, native moderator/redaction workflow, verified public
derivatives, HEIC codec qualification, live hosting/decoder/scheduler/abuse
preflight, browser/media QA, and any merge/deploy/public cut-over.
