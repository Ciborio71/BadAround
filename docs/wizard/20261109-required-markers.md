# Wizard required markers and heading alignment — 2026-11-09

Requested scope: replace visible required-field suffixes with a red, normal-weight asterisk; add the required-fields legend after Privacy by design; align the page heading with the wizard column.

Runtime candidate: 1be35142d866031e62b6a9f756bae18ffe41a8c5.
Parent/source baseline: e2e948bec2291591a6803b4b4ea6ee6f241d4577.

The model, conditional requirements, HTML required attributes and validation remain in place. Dynamic required markers are decorative for assistive technologies; aria-required remains on the controls. Optional labels retain neutral styling. The approximation-radius label uses the same asterisk. The final declaration retains its validation and gains aria-required.

The header uses the wizard's 880px/820px/fluid column, matching the stage's horizontal padding. The decorative icon moves to the right of the title. Font family and heading font sizes are unchanged.

Validation: JavaScript syntax and 55 Smart Address tests passed locally. The guarded-release workflow runs PHP syntax checks, GEO publication checks, 90 wizard/GEO/Smart Address DOM tests and release/rollback unit tests. Both Identity functional jobs passed. Browser visual certification remains unavailable behind the existing maintenance/access restrictions; these controls were preserved.

Production release: PASS. Job 129147064373 completed successfully, including all 90 JavaScript tests and guarded installation of the six runtime files. Backup and remote SHA-256 readback passed; frozen Core entrypoint preserved. No private configuration, user data or publication settings changed.
Workflow: https://github.com/Ciborio71/BadAround/actions/runs/42613348808
Identity CI: https://github.com/Ciborio71/BadAround/actions/runs/42613348670
