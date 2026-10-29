# DUAL-REPORT-01 / QUICK-01 — Contract v0.1 (implementation prototype)

## Experience
- The main CTA presents **Segnalazione rapida** or **Segnalazione dettagliata**.
- Detailed reporting remains the certified five-step native wizard, unchanged.
- The rapid experience is a responsive bottom sheet (mobile) or compact dialog (desktop), with a category, confirmed incident location and minimal description. Photo is optional.
- Target: minimal interaction, not a guarantee of three taps or 15 seconds on every network/device.
- Geolocation is a user-initiated permission request. Manual location entry always exists. Current device location is not assumed to be the incident location.
- The six canonical categories remain unchanged.
- Copy includes: **“BadAround è gratuito. Il valore lo creiamo insieme.”**

## Contract and security
- A local draft is not a server submission.
- Server-side private draft creation requires an explicit user action; no silent upload or silent GPS collection.
- Saved drafts are neither published nor eligible for Radar or public anomaly detection.
- Identity/email verification, draft token expiry and secure storage require separate security review.
- A draft may become ready only after all required fields are present.
- Submission must call the existing canonical intake/validation contract before moderation; never call the Publication Service directly.
- Identifying/location/photo data stays private, using the existing GEO-PUBLIC and media fences.
- Duplicate matches must never automatically convert independent incidents into confirmation votes.
- «È successo anche a me» opens a contextual quick-report flow; it is not an unverified +1 counter.

## Delivery gates
1. UX prototype mobile/desktop, accessibility and keyboard/escape focus.
2. Private draft API and storage design review with retention, anti-spam and idempotency.
3. Verified-email continuation, token revocation and safe link behavior.
4. Canonical moderation integration and error recovery.
5. Real-browser QA / privacy / concurrency / regression.
6. Gradual production launch, with the detailed wizard as an always-available fallback.

**Status (2026-10-29):** pure PHP transition model + synthetic tests only. No new API, no private draft persistence, no email delivery, no production UI change.
