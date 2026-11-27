# Human-confirmed AI moderation v1 — 2026-11-13

## Approved scope

Assess eligibility for publication, never truth, guilt or identity. Recommendations: approve, edit, human_review, reject, needs_information. No proof requirement. No invented facts. Exact-point/full-address requests, approximate-area/radius and chosen public identity remain owned by existing canonical and publication services. Plates remain governed by existing public-details rules; author contacts never become public copy.

## Runtime

- Production implementation: bb1b341502cdceb6a2f4082839663ef49661ae4a; hardening: 4bf2e3bd5a1e4d984939cbb3f59136742947a52b.
- Guarded releases: https://github.com/Ciborio71/BadAround/actions/runs/43454468525 and https://github.com/Ciborio71/BadAround/actions/runs/43455057058 (PASS).
- Exactly two MU modules installed/updated. Existing core, theme, publication implementation and private credential file were not deployed or overwritten.
- Model gpt-5-mini through Responses API; Standard tier, store=false, strict JSON schema, low reasoning, max_output_tokens=4096, response size/input size/time bounds. No model tools or browsing.
- Maximum 50 model attempts per UTC day, including synthetic tests. This is a request ceiling, not a monetary cap. No unbounded automatic provider retries.
- Enabled after the approved policy and a successful authenticated synthetic API test. Production status confirms enabled=true and autonomous_publication=false. No historical reports were backfilled.

## Intake and worker

The first primary-report metadata insertion schedules a single WordPress cron event after 30 seconds; no external call is made in the intake request. WordPress cron depends on site traffic unless backed by a host scheduler. No per-event duplicate generation while queued/processing; advisory locks serialize jobs and daily-budget reservations. Failed jobs remain non-public and notify the configured moderator. Email delivery uncertainty does not trigger automatic resend.

Native canonical text and selected public structured facts are minimized. Known reporter names, email, phone, exact acquired address and coordinates are removed locally; generic email/phone/credential-shaped strings and URLs are redacted. Geography sent to AI stops at region/province/municipality and precision/radius. This is minimization, not a guarantee that arbitrary third-party personal information cannot occur in user free text.

Images are NOT sent to OpenAI in v1. Any report with images is forced to human_review; the moderator opens the existing private report/media screen. Image publication approval still uses existing moderation controls.

## Human review

Tools → Valutazioni AI: /wp-admin/tools.php?page=badaround-ai-review

Email includes the recommendation, summary and reasons. Three links open the authenticated admin view at confirm/modify/disagree anchors. GET never mutates. Actions require HTTPS, capabilities, per-event edit permission and WordPress nonce.

- Confirm approve: moderator reviews/edits public copy, checks explicit text/precision/image review acknowledgement, then the existing Moderation Service approves and the existing Publication Service publishes. All existing geo/SEO/media gates remain in force. Failure remains visible and requires intervention.
- Confirm reject: existing Moderation Service rejects with reason.
- Confirm edit/human_review/needs_information: keeps the event pending for human follow-up; no automatic author email is sent.
- Modify: records the instruction and schedules a fresh bounded AI generation; never publishes.
- Disagree: requires a reason and explicit final decision. Default is hold, not reject. Approve requires reviewed copy and checkbox.

Source digests cover original report, current public text, moderation/non-AI metadata, taxonomies and media review state. Changed inputs and replayed decisions are rejected. Original report data and requested geography are not edited by the model. Editorial locks are excluded from the digest.

History is capped at 30 generations per event. Human feedback records actor/time/decision/reason in private metadata and existing audit; up to 30 minimized validated examples are retained, with at most 3 supplied as non-authoritative examples per request. This is example-guided prompting, not weight training, and never revises the approved policy automatically.

The list offers Sospendi valutazioni AI. It stops new AI work; ordinary manual moderation remains available. Configuration stays at /wp-admin/tools.php?page=badaround-ai-moderation.

## Verification and limits

Isolated configuration and workflow tests passed, including known-data minimization, preserved dates/radius, structured/refused/incomplete/malformed responses, transport and daily-limit failures, worker no-publication, at-most-once automatic email dispatch, stale event/media proposals, explicit review requirement, reject/hold/modify, authorization/nonce and decision replay. Existing publication/public-details/SEO/GEO and release rollback checks passed.

Live synthetic test used invented key-finding text only: connected=true, schema_valid=true, recommendation=approve, email_sent=false, real_report_used=false. One paid API request counted. Credential not read into tools/chat; provider receives it only in server-side authorization.

Production readiness verified configured credential and private storage, enabled workflow, no autonomous publication and WordPress cron not disabled. Independent source readback confirmed the failure-notification branch. Real report → cron → email → human publication has NOT been exercised live yet; the next user-entered test report is the end-to-end acceptance test. No live report was created, published, rejected or emailed by these checks.
