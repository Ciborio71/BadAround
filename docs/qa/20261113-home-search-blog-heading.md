# Home search focus and editorial heading — 2026-11-13

User request: remove the extra frame that appears when clicking the Home location field; make the Home blog heading visually consistent with Diventa una Sentinella.

Scope: home.css, blog.css and inc/blog.php only. The existing search card remains; the focused native input has no additional border, outline or shadow. Input labels, keyboard editing, autocomplete integration, search submission and button focus styles remain unchanged.

The Home blog heading now reads Il blog di BadAround and reuses ba-home-display-heading / ba-home-display-heading__accent, including the same navy/petrol styling, weight and responsive sizes as the Sentinella heading. Informarsi, condividere, fare rete remains as a normal subtitle. No changes to article text, post cover cropping, SEO metadata or article pages.

Chromium fixtures compare the real Home blog heading against the shared Sentinella heading and check focused input styles at ten viewport widths. Existing editorial index/category/article and event card/wizard/legend regressions remain in the release. Native assets were read from current repository head to preserve concurrent editorial work.

Candidate 6ee4cda96b2df4693ed54d65c990de16d289c3c4. Baseline 9ccdf2a43d10a01c2c1d92bbd42a478a95e25c3c. Outcome: PASS. All 50 existing public UI/DOM checks and forty editorial context/viewport combinations passed; the ten Home widths specifically check focus styling and typography equivalence. Guarded three-file upload, final remote hashes and native source readback passed. Workflow run 43421308568.

