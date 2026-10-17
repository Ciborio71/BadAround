# NF-UX-06 — Italian labels and verified location interaction

Scope: Native presentation on staging. No report submission, DB mutation, F1.2, publication, media enablement, or public projection changes.

- Normalize taxonomy labels to Italian sentence case only when composing Native presentation metadata. Canonical keys, term names and database stay unchanged.
- Cancel implicit form submission from Enter in the Google search while preserving the widget's suggestion selection. Only `gmp-select` plus Place Details can confirm an address; text edits invalidate both confirmed and pending selections.
- Restore a lazy private map preview, with existing category glyphs and marker palette. No map or geolocation request on page load.
- Explicit “Usa la mia posizione” requests device permission. GPS and dragged points are reverse-geocoded by Google, then resolved through Google Place Details and the existing canonical territory resolver. Private point coordinates are accepted only with that verified address/territory. No local coordinate-to-territory inference or provider ID persistence.
- Keyboard marker dragging uses Google's AdvancedMarkerElement native behavior. Search remains an alternative. Denial, timeout, unsupported provider, stale results, invalid territory and submission locks are handled without an unverified location advancing.
- Map unavailability does not invalidate an otherwise verified autocomplete address. The map displays private coordinates, not the future public precision.

The territory resolver prefix before `let loaderPromise` is byte-identical to deployed NF-UX-05. The remaining SmartAddress changes are presentation integration and the pending-response invalidation regression fix.

Tests: Native/Smart Address gate (146 JS/DOM tests), PHP schema/SSR and existing backend suites, full PHP and JS syntax, seven-step browser fixture at 320/390/768/1440 and dedicated mocked-provider browser tests of Enter, explicit GPS, marker category and drag reconciliation at all four widths. No real database writes. Live provider/browser behavior must be distinguished from provider mocks in final evidence.

The PHP SSR suite had obsolete legacy fallback and enqueue assertions; these now reflect the already deployed Native-only route and actual Smart Address dependency chain. Browser card checks now wait for the observable collapsed state rather than a fixed 10ms delay.
