# Verified location map and public precision layout — 2026-11-09

Runtime candidate: 2ad34751f206596a57356e6103ffa4de5ee46afb.
Final test/workflow candidate: c108f623200b7ad68228141a95d3cc29a1926b8c.
Pinned production source baseline: e325a42c71cbe0c3a97e4ab48b382a3ba7561e24.

The verified location decision block groups the map and public precision fieldset under Smart Address. On desktop (1025px and above), the map receives approximately 65% of the space and the option column has a 260px minimum. Exact point and approximate area cards stack vertically. Smaller viewports place the map above the cards. The verified-address confirmation stays below the joint block. Existing typography and validation are preserved.

The block is hidden until a selected address is confirmed, or manual canonical territory/address fields are complete. Editing the selection hides it again. When no map can be drawn, the precision fieldset uses the full available width. The radius control remains part of approximate area only. The pin explicitly represents the private precise source; the circle represents the requested public area. No new public coordinates or address disclosure is introduced.

Precision/radius changes update circle geometry without invoking fitBounds or setZoom. Map zoom remains user-controlled; help text explains zooming out to see a large circle. The private point and canonical report payload are unchanged by presentation changes.

Local validation: 57 Smart Address DOM tests and three guarded release/rollback tests passed. Two new behavior tests cover joint visibility/reset and circle redraw without zoom or private-point mutation.

The first release run (42625632625) stopped before upload because an existing assertion still expected the old fieldset title. The test was updated for the agreed title and now also checks that both map and public-choice controls belong to the initially hidden joint container. Identity functional CI (42625632498) passed both jobs.

Final guarded release: PASS (run 42625874230, job 129189406053). All 92 JavaScript tests passed, alongside PHP syntax/publication and guarded release/rollback checks. The five runtime files were backed up and installed with remote SHA-256 readback verification. Frozen Core entrypoint preserved; activation, URLs and data unchanged.
https://github.com/Ciborio71/BadAround/actions/runs/42625874230

Browser visual certification remains unavailable behind the existing maintenance/access restrictions. No access controls were changed, no reset was performed, and no private configuration was modified.
