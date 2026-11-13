# Wizard preferences, later-photo hint and address weight — 2026-11-13

Production release: https://github.com/Ciborio71/BadAround/actions/runs/43430889818
Commit: 400ad154c5ee59ca6e83f66f5b3d86d50233ff8c

- Address beside its heading is now bold (700); existing 17.6px size, inline layout and #056bd2 color preserved.
- Selecting media availability possible shows an accessible status note explaining images can be added later and the report can be submitted without photos. Other availability values hide the note.
- Contact notification cards keep focus and scroll position instead of auto-advancing to the next unanswered field/Continue. Other question progression is unchanged.
- 104 DOM/provider tests passed. Chromium checks passed at 1440, 1024, 768, 390 and 320 pixels, including the new address font weight assertion. Publication/SEO/GEO/social checks passed.
- Guarded FTPS backup/atomic replacement/hash readback passed for exactly wizard.js and native-report.css. Independent WPVibe source readback confirmed all three changes in production.

## Reported OTP error

The supplied screenshot contains a recipient-domain typo (gamril.com vs gmail.com). Administrative read-only inspection confirmed transactional-mail credential presence and that the correctly spelled test recipient is allowed by the configured QA recipient list; the screenshot's misspelled recipient is not. No credentials or recipient list are copied here, and no email was sent by these checks. The screenshot alone does not prove a provider outage or successful delivery after correction. Ask the tester to correct the address and retry before changing transport or admission settings.
