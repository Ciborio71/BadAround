# NF-QA-ACCESS-01 — Access diagnosis and QA path

Date: 22 October 2026 (Europe/Rome). Staging only. Application candidate `d0ac50bf4866b326943f3f4487b49fbe3d878d7b`.

## 403 origin

**LAYER: UNKNOWN** for the unexpected historical NF-UX-05 HTTP 403.

The historical denial was HTML with title `403 Forbidden` and text mentioning permission denial and an additional failure handling ErrorDocument. The previous runs did not preserve response headers, edge/request identifiers or hosting/WAF logs for that denial. This signature alone cannot distinguish a web-server access rule from a hosting WAF/security rule. No Cloudflare/WAF, web-server, WordPress, application or probe root cause is asserted without evidence.

The unexpected 403 did **not reproduce** in the current diagnostic matrix. Normal access now works without any security/runtime change. The only 403 currently observed is the deliberate WordPress JSON error `nfqa_unauthorized` returned by the temporary probe permission callback without its token. This expected control denial is distinct from the historical HTML denial and is not used to reclassify its origin.

Per task instruction, UNKNOWN means STOP, no remediation patch.

## Diagnostic request matrix

Run [39772841018](https://github.com/Ciborio71/BadAround/actions/runs/39772841018), QA commit `872b857e857c04202a4b853700aaca3cc75c05af`.

Full sanitized response headers, request method/User-Agent, origin/referer, cookie-presence metadata and body signatures are in `nf-qa-access-01-evidence/request-matrix.json` and the [diagnostic artifact](https://github.com/Ciborio71/BadAround/actions/runs/39772841018/artifacts/12422589943). Token values and cookie values are not included. The successful public REST body is recorded only as a top-level key signature.

| Client | Endpoint | Method | Authorization | HTTP | Body |
|---|---|---|---|---|---|
| browser-navigation | home | GET | none | 200 | html |
| browser-navigation | native | GET | none | 200 | html |
| browser-navigation | wp-json | GET | none | 200 | public-json |
| browser-navigation | public-discovery | GET | none | 200 | public-json |
| browser-navigation | protected-probe | GET | none | 403 | wordpress-json-error |
| same-origin-browser-fetch | home | GET | none | 200 | html |
| same-origin-browser-fetch | home | HEAD | none | 200 | other |
| same-origin-browser-fetch | native | GET | none | 200 | html |
| same-origin-browser-fetch | native | HEAD | none | 200 | other |
| same-origin-browser-fetch | wp-json | GET | none | 200 | public-json |
| same-origin-browser-fetch | wp-json | HEAD | none | 200 | other |
| same-origin-browser-fetch | public-discovery | GET | none | 200 | public-json |
| same-origin-browser-fetch | public-discovery | HEAD | none | 200 | other |
| same-origin-browser-fetch | protected-probe | GET | none | 403 | wordpress-json-error |
| same-origin-browser-fetch | protected-probe | GET | temporary-secret-header | 200 | protected-probe-json |
| same-origin-browser-fetch | protected-probe | HEAD | none | 403 | other |
| same-origin-browser-fetch | protected-probe | HEAD | temporary-secret-header | 200 | other |

All current responses: `Server: nginx`, `X-Powered-By: PHP/8.3.36`. No Cloudflare-related response header was observed. Their absence does not prove that Cloudflare or any WAF was absent from the historical failing path.

Current authorized probe response: HTTP 200, `Content-Type: application/json`, `Cache-Control: private, no-store, max-age=0`, `X-NFQA-Bootstrap: wordpress-rest-callback`; Core loaded, conditional class available without manual include, definition has 33 rows, Native v2, frozen contract SHA exact:

`dc282153243ffb80f3bd4f2a222ea0f4c4f42806fba6f3a0bde0a76855bd8e3c`.

## Working versus previously blocked access

| Property | Current GitHub runner | Working cloud browser | Historical blocked runner |
|---|---|---|---|
| Real Native page | HTTP 200, real runtime document | Reload succeeds, real Native v2 document | HTTP 403 HTML |
| Method | GET navigation, GET/HEAD same-origin fetch | Ordinary browser reload GET | GET |
| Public-page authentication | None | No login flow used; no admin bar present | No login flow |
| Cookies/session | 0 cookies; navigation sent no cookie | Cookie values/headers are not exposed by the browser inspection interface; no value collected | Not recorded |
| Protected probe | Secret header; authorized 200, unauthorized 403 | Not exercised from cloud browser because runner access succeeded normally | Header-token same-origin request received 403 |
| Origin/referer | Navigation has no Origin/Referer; fetch runs same-origin from Native page | Ordinary page reload; detailed transport headers unavailable in this interface | Same-origin fetch in certified client source |
| User-Agent | Official bundled Chromium, unmodified `HeadlessChrome/151.0.7922.34` | Not exposed by read-only DOM inspection | Not captured in the failing artifact |
| Response headers | Preserved, sanitized in matrix | Not exposed by this inspection interface | Not captured for original denial |

No browser fingerprint, network route, DNS destination, proxy, IP allowlist or security setting was changed to obtain success.

## Difference from previous successful QA

- NF-UX-03 browser QA navigated directly to the real Native page in normal Playwright Chromium.
- NF-UX-03A's earlier Core probe used a temporary token-guarded root PHP file which required `wp-load.php`; it was removed immediately. That historical pattern was inspected, not reinstated.
- Certified NF-UX-04 used a temporary MU plugin, normal WordPress bootstrap, REST registration on `rest_api_init`, and same-origin browser fetch with `credentials: same-origin` plus `X-NF04-Token`.
- NF-UX-05 used that **identical client file**. Its probe changed the REST namespace from `nf04-qa/v1` to `nf05-qa/v1` and extended protected/historical fixture reads through #68. It did not change the authorization mechanism or cookie flow.
- This diagnostic reuses that same normal bootstrap/REST/same-origin/header-token pattern, with an isolated `nfqa-access/v1` namespace, its own temporary header name, and a smaller read-only callback. The public Native page also works without a token. No evidence attributes restored public access to a probe implementation change.
- Historical runner source IP and edge decision logs were not recorded. No causality is inferred from a new run succeeding.

## Remediation

METHOD: **None applied.** An already-certified same-origin QA transport was demonstrated working again; no explanation for the previous HTML 403 is proven.

SECURITY SCOPE: Temporary random-path, 256-bit secret-header protected read-only WordPress REST probe, restricted to `HTTP_HOST=staging.badaround.it`, GET/HEAD only, no persistence/media/publication calls. Secret passed only through process input and request headers; never in URLs, logs or artifacts. No application-runtime file or security configuration was altered.

APPLICATION RUNTIME CHANGED: **NO**. Remote baseline custody **PASS, 33/33**. Only a temporary QA MU plugin was uploaded and then deleted; it is isolated from application semantics.

PERMANENT SECURITY RELAXATION: **NO**. No global bypass, allowlisting, Cloudflare/WAF disablement, changed `.htaccess`, permanent endpoint or production change.

The read-only `.htaccess` security-directive inspection found ordinary WordPress existing-file/directory rewrite conditions and no deny/allow/user-agent/security directive matching the filter. This does not rule out server/vhost/WAF rules outside `.htaccess`.

## Access test

- NATIVE PAGE FROM QA EXECUTION ENVIRONMENT: **PASS** — HTTP 200 from runner; real page screenshot captured.
- PROTECTED PROBE: **PASS** — authorized GET and HEAD HTTP 200; real Core/contract/Native config read after standard bootstrap.
- UNAUTHORIZED ACCESS: **DENIED** — GET and HEAD HTTP 403; JSON code `nfqa_unauthorized` for GET.
- PUBLIC CACHE EXPOSURE: **NONE observed** — authorized result explicitly private/no-store.
- APPLICATION SEMANTIC TESTS / NF-UX-05 SUBMISSIONS: **NOT EXECUTED** in this task.

## Cleanup and workflow limitation

TEMPORARY QA COMPONENTS REMOVED: **YES**. `finally` deleted the exact uploaded MU plugin, retried the same file read, and verified FTP 550 (file absent). `cleanup.json` and log marker `TEMPORARY_PROBE_REMOVED_VERIFIED` are preserved. No token endpoint remains installed.

After the completed matrix and verified cleanup, the workflow encountered FTP `450 .../logs/: No such file or directory` while looking for site-local hosting log names. Therefore the overall workflow conclusion is **FAIL**, not a green certification. No server/WAF log correlation was obtained, and the planned after-custody step was not reached. Application runtime was never uploaded or modified by this workflow; the before-custody artifact contains all 33 matching hashes.

No real report/event, media, moderation, publication, GEO/Social state, DB schema or taxonomy was changed. No NF-UX-05 sweep or submission was resumed.

## Remaining evidence needed

To classify the original denial, correlate hosting/web-server/WAF access/error events around the original failing NF-UX-05 requests on 22 October 2026, approximately 13:19–13:26 Europe/Rome, with their actual transport/request identity. Those original response headers and correlation IDs are missing. No configuration remediation is justified by the current evidence.

## Final verdict

**NF-QA-ACCESS-01 STOP — STAGING QA ACCESS NOT RESTORED**

This required verdict reflects the unclosed UNKNOWN-origin gate. The current runner page/probe access checks pass; a proven root-cause remediation and durable-access certification are not claimed. NF-UX-05 remains stopped pending an explicit subsequent instruction.
