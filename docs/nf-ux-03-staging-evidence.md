# NF-UX-03 — Staging deployment verified

Date: 2026-10-17.

Application: `a5de5bc6d519f8517a43bf94d3a1c2937b520398`.
Deployment: `b59ba8cf7882c04d7a71cf6fa759c0df8f6a1f3e`.

[CI and deployment run](https://github.com/Ciborio71/BadAround/actions/runs/39018727795): SUCCESS.

- 139/139 JS/DOM regressions passed, including five new compact-choice tests.
- PHP/backend constraints and JS/PHP syntax passed.
- Source-backed Chromium checks at 320, 390, 768 and 1440px passed: compact/edit, arrow and Tab navigation, visible errors, overflow, controls, paired field geometry and seven-step navigation. Desktop/mobile screenshots reviewed.
- Two files uploaded to staging: native-report.css and wizard.js. First FTPS attempt succeeded; retry and rollback steps were unnecessary. Rollback baseline artifact retained.
- Both active theme files independently read after deployment using authenticated WordPress and matched the approved source, ignoring terminal whitespace from the source-reading transport.
- Local and remote deployment trees are identical: `471469c61fd7b0115bd66a0c7e8b27c92fcc1a7a`.
- Browser validation used the source fixture; live interactive Google and physical-device testing were not repeated.
- No real submission, DB mutation, backend/F1.2 change, Smart Address change, media enablement, production deployment or merge.

STAGING DEPLOY: COMPLETE.
