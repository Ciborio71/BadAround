# Photo-only event category overlays — 2026-11-13

User rule: the event placeholder already contains the category symbol. Add the lateral category medallion only over an actual uploaded photograph.

Audit: the shared PHP event-card template serves Home, the proximity feed, search, event lists and territorial archives. The shared map.js preview builder and map results-list builder are the only other event media-category producers. Editorial blog category labels and static category controls are not image overlays and remain unchanged.

Implementation: classify the original image before injecting a fallback in the PHP renderer; recognise configured normal/approximate placeholder URLs in JavaScript, ignoring cache-version query parameters. Do not create an empty badge element for placeholders. Keep the correct category/precision medallion for real photographs. Preserve all geometry, radii, image assets, titles, consent and publication rules.

Regression fixtures: no image; normal placeholder passed explicitly as thumbnail; approximate placeholder passed explicitly as thumbnail; uploaded photograph; exact and approximate precision. Assert the overlay's presence only for uploaded photos in server cards, map result cards and map preview cards. Existing asset integrity and five Chromium viewport checks retained.

Candidate ed665c6c4334f6aa7c234434ef4a44724acdd050. Baseline 2b544761656abce6a9b435d658ff4ad7a1a9b60e. Guarded workflow run 43408274719; upload scope map.js and template-parts/components/event-card.php only. Outcome: PASS. PHP fixtures, all 50 public UI/DOM checks and five Chromium viewport checks passed. Guarded two-file upload and remote hash readback passed. Native live source readback confirmed both runtime files.

