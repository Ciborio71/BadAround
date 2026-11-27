# BadAround autonomous technical verification — 8 November 2026

## Production observations (read-only)

- Approved source baseline: 6fe247c78d542aa40b64908865783fef81a570e7.
- blog_public=0. Maintenance/noindex intentionally retained; this is not public indexability certification.
- Event395 canonical frozen URL and SEO contract1.1 active; current server projection confirmed.
- 24 published events,62 pending; no deletion/reset performed.
- Published events without a bound active primary report:0.
- Active report media without a report:0.
- Approved Identity comments without a published event:0.
- Identity ops bridge is outside the private QA network perimeter. Request-scoped runtime flags remain off there; that is expected and does not establish that the authorized tester connection is disabled.
- Private config file exists, readable, owner matches PHP, permissions0600; notice integrity valid; required PDO/MySQL/mbstring/OpenSSL available; no required InnoDB table missing.
- Image service disabled and hosting unverified as previously deferred. upload_max_filesize2M vs wizard5MiB, isolated decoder paths unavailable; not activated or weakened.
- Active plugin inventory contains no Yoast or Rank Math. Updates are available for several vendor plugins; their vulnerability status and compatibility are not inferred from that fact. Vendor updates are not included in this baseline-preserving release.

## Performance scope

One authenticated diagnostic request: bootstrap to callback1457.2ms; body generation25.9ms, HTTP200. Query Monitor active, no external object cache. These are server diagnostic observations, not anonymous TTFB, LCP, mobile Web Vitals or a Lighthouse score. Cache/hosting changes remain a migration gate.

## Remediation

No JSON-LD emitter for event detail existed in the inspected runtime source. Added a public-contract-only WebPage plus BreadcrumbList graph using the existing canonical, public H1/description and visible Home → Segnalazioni → event hierarchy. No Schema.org Event type (a reported incident is not an organized event), person, address, coordinates, private report, occurrence date, inferred publication date or invented imagery.

Only published, moderated, unprotected contract1.1 events qualify. Legacy events are not rewritten. Unsafe canonical URL (foreign host, HTTP, query/token, fragment, userinfo or port) is rejected. Yoast/Rank Math ownership suppresses the graph to avoid overlapping emitters. JSON-LD uses hex escaping and the same H1 formatter as the visible page. A capability-checked no-store admin GET exposes the exact public graph for verification, without opening the frontend or relaxing maintenance.

This read-only diagnostic is not a complete front-end head duplication audit or a Google Rich Results / Search Console validation. Global noindex and maintenance remain in place.

## Regression plan

Existing Identity primitives and disposable MySQL suite; public details/GEO, dates, headings, gallery, contributions E1/E2/E3/E5, community moderation; historical wizard/media JS suite and17 recent interaction/Home/map/reply cases; guarded release/rollback tests. Structured-data cases cover canonical stability, breadcrumb positions, draft/password/legacy exclusions, private field omission and no mutation.

## Still open

Protected browser/mobile end-to-end (remote browser sees maintenance); actual mailbox delivery and OTP journey; image pipeline after migration; anonymous performance after migration; legal final privacy; controlled reversible reset and new beta fixtures; final canonical/sitemap/robots/structured-data crawl after authorized public opening. No complete penetration-test or production-launch certification is claimed.

## Extended suite finding and remediation

The first expanded run found a real Native wizard UX defect: review-time media errors were filtered out because their step was hidden. Submission remained blocked but the message/navigation could disappear. The final submit now passes those known media errors through the existing prior-step error presentation path, returning focus to the media step. Validation, upload permissions and pipeline activation do not change.

Three media assertions caught that single defect. Two further failures were test infrastructure drift: the differential runner could not resolve its historical Git tree from a shallow checkout, and a strict CSS !important allowlist predated the approved visually-hidden conditional live status. The zero-media differential now compares against the exact approved pre-audit production checkout (6fe247c...), across20 availability/response combinations, preserving payload/error/focus assertions. The CSS allowlist explicitly permits only the two named existing fences; no production CSS change or broad relaxation.

The first release stopped before upload:114 historical JS cases,109PASS/5FAIL. Existing PHP structured-data/SEO/privacy/GEO/community tests passed. The follow-up candidate includes the wizard error fix and reruns all114 historical JS cases plus17 interaction cases, not just the previously failed tests.

## Final verified outcome

Final runtime candidate8498252d8b4474572f2b97560cb586c68888b7d7. Guarded release run42487929766/job128753777477 PASS. Identity functional run42487929743 PASS (primitives and disposable MySQL). Historical wizard/media JS114/114PASS; recent interaction/Home/map/reply17/17PASS. PHP suites and three release/rollback tests PASS. Three runtime files backed up privately and byte-readback verified; frozen Core entrypoint preserved.

Live admin event-structured-data/395 returns exactly WebPage and BreadcrumbList with the frozen Honda URL and visible H1, Home and Segnalazioni. Segnalazioni is an existing published page242. Event395 body HTML and signed public-details snapshot compare unchanged against pre-release. This confirms the graph projection and installed wp_head hook source, not a Google crawl/Rich Results outcome or a complete anonymous head rendering.

No database writes/reset, publication, real mailbox delivery, image enablement, cache/plugin updates, maintenance/robots change or security-gate relaxation performed. Report status is a completed technical regression tranche, with the explicit launch/mobile/mail/media/legal gates above still open.
