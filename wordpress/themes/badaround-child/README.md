# BadAround Child Theme

This child theme is reserved for presentation-specific customizations.

Business logic and platform features belong in `badaround-core`.

Canonical content identifiers:

- event post type: `ba_evento`;
- event type taxonomy: `ba_tipo_evento`;
- territory taxonomy: `ba_territorio`;
- public event metadata: `_ba_*` keys exposed through the theme's allow-listed helper only.

The child theme must never query `ba_reports`, exact coordinates, full plates,
reporter contacts, consent records, original media, or audit data.
