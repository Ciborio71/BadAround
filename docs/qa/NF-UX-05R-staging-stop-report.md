# NF-UX-05R — Fresh access preflight STOP

Date: 22 October 2026, Europe/Rome. Environment: staging only.

**NF-UX-05 STOP — 33-SUBTYPE NATIVE V2 REGRESSION NOT CERTIFIED**

Run [39774763677](https://github.com/Ciborio71/BadAround/actions/runs/39774763677), QA-only commit `51c691f1abaec1088a5c092826e968e1cbb74912`, branch `qa/nf-ux-05r-certification`. Result: FAIL at the first access-preflight request. The application candidate remains `d0ac50bf4866b326943f3f4487b49fbe3d878d7b`; no application file was modified or deployed.

## Access preflight

| Gate | Result |
|---|---|
| GET Home | FAIL — unexpected HTML HTTP 403 |
| GET Native | NOT RUN — stopped at preceding failure |
| GET wp-json | NOT RUN |
| GET public discovery | NOT RUN |
| Install temporary probe | NOT PERFORMED |
| Probe unauthorized | NOT RUN |
| Probe authorized | NOT RUN |

The preflight obeyed the requested order and stopped immediately, before probe installation, on the first unexpected access failure. No retries, alternative security/network path, WAF change, fingerprint change, allowlisting or NF-QA-ACCESS-01 investigation was performed. The historical 403 cause remains outside scope and UNKNOWN.

## Captured failure evidence

- UTC: `2026-10-22T12:00:17.253Z`.
- Europe/Rome: `2026-10-22 14:00:17` (CEST, UTC+02:00).
- Endpoint: `https://staging.badaround.it/`.
- Method: `GET`.
- Probe authorization state: `none`; no probe existed.
- HTTP: `403`.
- User-Agent: `Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/151.0.7922.34 Safari/537.36` (ordinary bundled Chromium, unmodified).

Full sanitized response headers captured:

```json
{
  "content-encoding": "br",
  "content-type": "text/html; charset=iso-8859-1",
  "date": "Thu, 22 Oct 2026 12:00:17 GMT",
  "server": "nginx",
  "vary": "Accept-Encoding"
}
```

Body signature:

```text
403 Forbidden Forbidden You don't have permission to access this resource. Additionally, a 403 Forbidden error was encountered while trying to use an ErrorDocument to handle the request.
```

No Cloudflare/server correlation or request-ID header was present in the returned header set. The response was HTML, not a WordPress JSON authorization error. This report records the failure without attributing it to a security layer.

Raw sanitized evidence: `nf-ux-05r-evidence/access-preflight.json`, `nf-ux-05r-evidence/preflight-cleanup.json` and [artifact 12423139201](https://github.com/Ciborio71/BadAround/actions/runs/39774763677/artifacts/12423139201), retained for 90 days.

## Runtime

- VERSION: certified baseline `badaround-report/v2`; current Native page was not reached by this run, so no fresh runtime version assertion was made.
- APPLICATION CHANGES: **NO**. Repository application diff against the certified candidate passed. No staging application upload occurred.
- RUNTIME HASHES: **NOT RUN**, gated after access preflight. Previous custody is not represented as a new check.
- CONTRACT SHA: expected frozen `dc282153243ffb80f3bd4f2a222ea0f4c4f42806fba6f3a0bde0a76855bd8e3c`; new remote verification **NOT RUN**.

## Semantic certification gates

All following gates are **NOT RUN**, not proven semantic defects:

| Group | Gates |
|---|---|
| Subtypes | 33/33 oracles; actual visible/required/optional/hidden fields; dependencies; summary keys; payload keys |
| Matrix | 2,805 field/subtype comparisons; 554 dependency activations; live frontend/backend differential |
| Damage | 2 REQUIRED; 14 OPTIONAL; 17 HIDDEN, including hidden/no blocking/no summary/no payload |
| Altro | All 8; requiredness; length; Unicode/plain text; canonical keys; stale clearing; summary; payload |
| Emergency | 9 automatic subtypes; yes/unsure; false positives; single dual-trigger notice |
| UX | 320/390/1440; keyboard/accessibility; requiredness; back/forward; focus; hidden fields not tabbable |
| Regression | V1 backend/historical read; C.1; C.2; GEO; Social; five consents; no-media; media fail-closed |

Failed subtypes: **none established**. All 33 remain uncertified because the sweep was never entered. No actual application semantic mismatch was observed, and no runtime fix was attempted.

## Real submissions and protected state

- COUNT: **0**.
- REPORT / EVENT: **none**.
- ALL V2 / ALL PENDING / IDEMPOTENT RETRY: **NOT RUN / not applicable**.
- NO PUBLICATION: **YES — no report, event, moderation, Social or publication operation was performed**.
- New protected fixtures: **none**. Existing #35–#68 and related events, residual #57, and #68/#344 received no mutation by this task.
- DB schema / canonical taxonomy / protected fixture before-after digests: **NOT RUN**, because baseline collection was gated after access preflight. No DB/schema/taxonomy write was performed by this run; this is not presented as a verified SQL digest comparison.

## Temporary QA

REMOVED: **YES — nothing was installed**.

The `finally` cleanup record is `{"removed":true,"installed":false}`. The four public GET requests were required to precede installation; Home failed before the installation handshake, so no temporary file, route or secret was uploaded to staging. No endpoint remains installed from this run.

## Final verdict

**NF-UX-05 STOP — 33-SUBTYPE NATIVE V2 REGRESSION NOT CERTIFIED**

Stop reason is the new unexpected access failure in this run, not the unknown historical cause. No feature implementation, application/security patch, merge, production deploy, NF-HOME-01 or NF-FINAL-01 was started.
