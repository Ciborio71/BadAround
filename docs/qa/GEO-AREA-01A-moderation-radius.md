# GEO-AREA-01A — read-only moderator radius display

Base: 7398815f3a946f194a75c28fcaf48683d1441832.

Only runtime change: `class-badaround-moderation-admin.php`, in `render_geo_decision_box()`.
After the existing service validates request and authority, version-2 governed approximate requests append ` — circa <radius>` to the read-only label. 1000 maps to 1 km; other enum values render in meters. Exact and version-1 labels remain unchanged. No controls, writes or moderation actions are added.

The actual SQL context rejects missing/unsupported/string radii and unknown request versions before rendering. Existing explicit non-verifiable-request copy remains the failure state; no fallback radius is inferred.

`tests/geo-area-01a-moderation.php` executes actual PHP moderation rendering with a real isolated SQL context for six positive cases, four legacy cases, and four negative cases. Legacy HTML is compared byte-for-byte with the certified base renderer, with historical public system radius 1000 present in metadata. Before/after checks assert no fixture/meta/audit mutation. The dedicated workflow runs this on MariaDB and includes existing GEO-PREC, GEO-AREA, publication, projection, Native v1/v2, conditional, C.1/C.2, Social/privacy and media regression tests.

Pre-deploy only. No staging access, deploy or new real fixture. NF-FINAL-01 stays on hold.
