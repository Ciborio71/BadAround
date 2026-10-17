# NF-UX-08 — consistent home, form and private map category icons

One runtime file changed: Native wizard.js. Reuse the six SVG geometries and stroke/fill styles from the active home category navigation, and align the six accent colors with home and private map.

The existing private map marker clones the selected category SVG, so the same icon appears on the map without a second icon definition or any change to location-map.js, Smart Address, coordinates, contract, API or backend. Icons stay decorative; category text and keyboard controls remain unchanged.

Validation: six-way home/form geometry, fill/stroke and color comparison; map browser checks require exact SVG equality between selected form category and map marker. Full Native/Smart Address gate and both browser suites run before the one-file staging deployment. No report submission, DB mutation, media enablement, publication or production changes.
