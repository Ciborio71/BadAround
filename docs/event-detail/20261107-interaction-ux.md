# Event interaction UX and map completion state — 7 November 2026

## Reference and defects

User-supplied two-page PDF screenshot of Honda event395, 13:16 Europe/Rome. The completed map retained its loading button because a theme display declaration overrode the HTML hidden state. The contribution form displayed radios/checkboxes at full input width, separating controls from labels. Optional information, identity and visibility choices formed a long undifferentiated form.

## Implementation

- Keep lazy map loading and public geometry unchanged. Once loaded, hide the button with a scoped hidden rule and announce success in a visually hidden live region. Retry stays visible on provider failure.
- Fix hidden state for contribution panel and alias; keep the initial card compact until explicit expansion.
- Expand/collapse control has aria-controls/expanded, focuses the first field on opening and preserves a closed draft. Respect reduced motion.
- Separate primary information, optional location/time/photos disclosure, identity/visibility choices and contact/confirmation. Preserve all original field names, values, consent, limits, backend validation, moderation and private/public semantics.
- Align radios and checkboxes with their text. Match font sizes, spacing, field widths, contrast, focus indicators, rounded borders and mobile stacking to the page.
- Clearly styled pending/success/error feedback; prevent duplicate pending requests; retain entered text on errors and reset alias on success.
- Version the contribution assets by file mtime so stale Core version cache keys cannot hide the update.

## Verification

13 local JS tests PASS: contribution open/close/focus/hidden/alias, pending duplicate prevention, error draft retention, success reset, detail lazy map completion/retry and no national discovery, Home geolocation regressions, Identity reply/alias transport. Three guarded deployment unit tests PASS.

Production release runs PHP lint, existing public-details/GEO/community integration tests and the JS checks before FTPS upload. No actual contribution is sent, no email dispatched, no image service activated and no QA access gate changed.

Final source ade6ed1929850ce567461ae3da4ae49cf12b0087. Release run42372089885/job128402121156 PASS. Five runtime files backed up and verified by remote byte readback; Core entrypoint frozen.

Live authenticated server render of event395 confirms the three primary groups, visibility choices, optional disclosure, controlled expandable panel and exactly the same original contribution field names. H1 and public details snapshot unchanged. Map success hiding is verified by DOM/CSS interaction tests, including an explicit conflicting theme button display rule; the protected-site live map was not re-certified with a new browser screenshot. Full visual responsive certification remains pending.
