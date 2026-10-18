# NF-GPS-SCROLL — Reveal verified address after explicit geolocation

Baseline: staging deployment 1a79e09527adc6cac8dbc9bc2e5217a57fbed7b4. An isolated worktree was created from that exact current deployment because the original local checkout was older. Active location-map.js was read and matched the baseline. Other Native/legal/preview work is not uploaded or replaced.

Only runtime file changed: wordpress/themes/badaround-child/assets/js/native-report/location-map.js.

The presentation adapter moves the existing verified-address block immediately after the map panel. Its existing Smart Address data bindings remain intact. An explicit GPS success waits for map rendering (including its controlled failure), focuses the address block with preventScroll, then scrolls below the current sticky header. Reduced motion is honored. The existing Place acceptance starts a new request ticket; the scroll retains that ticket and is canceled for stale answers, edited addresses, a hidden location step, teardown or submission lock. Search selection and marker dragging do not trigger the new automatic scroll. Permission denial and unresolved addresses never focus a false result.

No change to smart-address.js, its territory reconciliation, schemas, model/API, location coordinates or privacy semantics, backend, media, publication, fixtures or production. No actual report submission or DB mutation is required.

QA extends the source-backed map browser suite: mobile320/390, tablet768 and desktop1440; simulated fixed88px header and1100px spacer prove actual scrolling. Verified-address focus, ordering and viewport visibility are asserted. Denied GPS remains unconfirmed; a subsequent retry succeeds. Chromium and WebKit run in CI with mocked Google and GPS, not a physical-iPhone certification.
