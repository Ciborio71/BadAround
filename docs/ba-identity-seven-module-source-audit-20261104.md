# BA-IDENTITY — seven-module read-only source comparison (2026-11-04)

Scope: seven Identity files under `wp-content/plugins/badaround-core/identity-dormant/` read from live WordPress via WPVibe, compared line-by-line with `prototypes/ba-identity-01c/` from `feature/ba-identity-01c-dormant-20261103`.

| Module | Live lines | Compared unmasked lines | Visible mismatches | Redacted / masked lines |
| --- | ---: | ---: | ---: | --- |
| wordpress-runtime-loader.php | 57 | 56 | 0 | 41 |
| wordpress-private-bootstrap.php | 119 | 119 | 0 | — |
| wordpress-privacy-notice.php | 45 | 45 | 0 | — |
| wordpress-registration-rest.php | 124 | 124 | 0 | — |
| wordpress-comment-form.php | 53 | 53 | 0 | — |
| wordpress-identity-portal.php | 77 | 76 | 0 | 39 (text rewritten by safe redaction) |
| wordpress-community-rest-adapter.php | 126 | 125 | 0 | 74 |

Total: **598 unmasked lines match out of 601 source lines**. The live reader redacts or rewrites 3 lines. No functional divergence was observed in visible source; this is **not byte-exact attestation**.

## Interpretation

This evidence reduces reconciliation uncertainty for these seven modules, but does not authorize deploy, merge, or feature flag enablement. The read-only FTPS SHA256 workflow remains the required next proof. Do not copy masked source into GitHub. Keep PR #84 in draft. See #83 for gates.

## Source location

- Live plugin: `https://badaround.it`
- GitHub source ref: `feature/ba-identity-01c-dormant-20261103`
- Reconciliation ref: `reconcile/identity-production-baseline-20261104`
