# NF-DISCLAIMER-LINKS — Published policy links in the final confirmation

Baseline: latest deployed 6a5504f0b7b771794740cdcaf19a7fa3d3958ae4. Current active wizard.js and native-report.php were read and matched this baseline before changes. Work is isolated from the older original checkout.

Read-only WordPress verification: published Regole di pubblicazione285, Termini e condizioni248, Privacy Policy3, Cookie Policy249. Public GET verification: all four canonical paths returned200 without redirects to a different page.

Only runtime file changed: wordpress/themes/badaround-child/assets/js/native-report/wizard.js. The existing declaration sentence now contains actual anchors for publication rules, terms and privacy. Cookie Policy is an additional consultation link within the same box, without expanding the mandatory acceptance. All links are underlined, open a new tab with noopener/noreferrer and an accessible new-tab description, and remain outside the checkbox label.

No policy text, WordPress page, consent version, canonical boolean, privacy/contact preference, validation contract, provider/map behavior, media/backend/publication/SEO/fixture or production change. No database mutation or real report submission.

QA: existing DOM test verifies four exact destination paths and link semantics. Browser flows open all four links in new tabs at390/1440px, assert null opener, then verify unchanged canonical payload and checkbox. Document transport is intercepted in browser tests; public destination availability was checked separately. Full Native and Chromium/WebKit regression gates precede the one-file staging upload.
