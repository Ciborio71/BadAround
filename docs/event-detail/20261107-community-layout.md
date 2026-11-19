# Event witness navigation and community layout — 7 November 2026

## Scope

The witness prompt directly beneath H1 is now a normal accessible link to the existing `#contribuisci` section and its dynamic “Hai visto questo Honda SH?” heading. Sticky-header scroll offset is scoped to the event page.

The Identity discussion/form is rendered once within the event main column, after the witness contribution card. The former duplicate placement after main has been removed. The comment card has its own H2, approved discussion H3 and author H4 headings. Empty approved discussions display an explicit message instead of disappearing for SEO-enabled events.

All existing network, privacy-version, runtime and session/moderation gates are retained. Outside private QA, the static card explains why comments are unavailable and exposes neither private comments nor submission forms. No gate is enabled, IP spoofed, or pending content published.

Photo gallery and compact no-photo state are titled white rounded cards. Existing detail, contribution, information and safety cards share scoped white backgrounds, borders and radii. The original H1, breadcrumbs, geography, dates, gallery behaviour and URLs remain intact.

## Evidence

Read-only production aggregate: 2 approved Identity comments belong to event392; event395 (Honda SH) has no approved Identity comments. Comments were not deleted or transferred across events.

Six local JS tests passed: gallery navigation/keyboard/swipe and Identity activity/reply/pseudonym transport. Three guarded deployment unit tests passed. PHP lint, gallery fixtures, date/public-detail/GEO fixtures and community integration tests run before guarded upload. Identity production-source functional CI run42370657896 passed.

The initial release workflow stopped before upload due to a misnamed test command. The command was corrected; no runtime mutation occurred during that failed attempt.

Visual browser/responsive certification remains pending; live verification uses authenticated server rendering and guarded source readback.

Final source: 14be0f6902dee3ad0a489487a56854edb3074aad. Guarded release run42370709139/job128398051766 PASS, all six runtime files verified by remote readback with private backups.

Live authenticated server render: event395 has the witness anchor and matching “Hai visto questo Honda SH?” target, one comment card inside main, and the titled no-photo card. Event381 has the titled photo-gallery card and one comment card. The admin bridge is outside authorized private QA, so the unavailable notice is expected there; no access restriction was relaxed to obtain a form screenshot. Event395 H1, breadcrumbs and public details snapshot compare unchanged against the pre-release render.
