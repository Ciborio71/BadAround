# NF-MOBILE-03 — Mobile control clipping and visible submission confirmation

Baseline: deployed 626b91861c3e52af170ef4fb5a646097ceed94e5. Existing current source read before modifications; no old wizard restore.

Runtime scope: four presentation files only: native-report.css, wizard.js, inc/native-report.php, template-parts/native-report/shell.php under wordpress/themes/badaround-child.

- Override theme select fixed heights with 56px border-box, 8px vertical padding, normal line-height and 16px font; text inputs have auto height/min52px to avoid vertical clipping. Native select semantics retained.
- Successful submission focuses the confirmation heading with preventScroll, then explicitly scrolls its panel below the current site header; a guarded next animation frame recalculates after layout. No scrolling on failure, no new submission or changed persistence.
- Confirmation panel has a strong heading Segnalazione inviata and a distinct teal border/background. Existing moderation message remains unchanged.
- Previously requested changes completed: plain unnumbered confirmation checkbox/title, explanatory text outside label on full panel width with justified text/hyphenation, clearer updates question and three existing canonical options. Necessary moderation contact remains distinguished from optional updates.
- Five mandatory acknowledgements remain separately mapped; optional contact choice and all canonical enums unchanged.

QA: existing full Native/Smart Address/media DOM and PHP suites, source-backed Chromium browser suites plus dedicated WebKit run of six desktop/mobile paths. Browser assertions check text vertical space, full-width justified explanation, unnumbered checkbox, successful focus and heading within viewport below a simulated fixed88px header. Provider and submission transports are intercepted. This is not certification on a physical iPhone or a new live Google session. No real report/DB mutation is necessary for this frontend patch.

No F1.2/model/API/Smart Address/location-map/media backend/publication/SEO/fixtures/production change. Legal pages remain a separate roadmap task; this patch does not create pages or invent legal document URLs.
