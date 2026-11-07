# Field heights and community distinction — 7 November 2026

User screenshot shows native select text clipped by inherited fixed control height. Contribution text/select inputs now use automatic height with 50px minimum, explicit 16px type, 1.5 line-height and 10px vertical padding. Textareas retain flexible height. Radio/checkbox dimensions remain unchanged. Identity comment selects receive equivalent sizing.

The existing community card (including private-QA unavailable state) has a pale blue background, teal border and thicker leading border, distinct from white event details. Scope remains confined to event pages.

Public contribution and Identity comment submissions say “Invia il commento”. A note immediately below states that comments are moderated and published only after approval. Reserved contribution selection switches the label to “Invia informazioni riservate”; the note clarifies that reserved information is not public. No private/public or approval policy is changed.

11 local interaction/Identity/map cases PASS, including regression coverage for computed select room and reserved/public button labels. Three guarded-release tests PASS. Release CI also includes Home regression cases, PHP lint and existing public-details/GEO/community integration fixtures.

No comment or contribution was actually submitted; no email, image activation, reset, access-gate relaxation or report write is part of this change. Full visual responsive certification remains pending.

Source c3f8d88723db559e4c38afa0d928711c583af1d5; guarded release run42394053446/job128467539690 PASS, five runtime files verified remotely with prior backups. Release interaction suite: 15 tests, zero failures. Identity functional CI run42394053394 PASS.

Live event395 authenticated server render verifies “Invia il commento” immediately followed by the moderation note and reserved disclosure. Public details snapshot unchanged. Style sizing/hidden behaviour checked through DOM/CSS tests and source readback, not a fresh protected browser screenshot.
