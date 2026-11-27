# Navbar Identity session stability — 2026-11-11

Runtime commit: 85794a480dc1ee67b7e94cd6228aabc9209e0f89
Prior production source: 9688529aa1b5cb69afe0c09c4686098f2ef831af.

## Defect and correction
The shared header always rendered Accedi before asynchronous identity/session changed it to Il mio profilo. Page navigation therefore repeated the label flash and changed the actions column width.

The assembled private Identity bootstrap now binds a read-only server session reader to the portal. Header desktop and mobile labels render from that verified state. The reader reuses the opaque-cookie session policy and current active/verified identity check; missing, malformed, idle, expired, revoked or disabled sessions fail closed. No profile data, bearer or CSRF token is rendered. HTTPS and existing private access/privacy gates remain required. WP administrator login is not treated as Identity login.

Desktop reserves an 8em account slot. Existing responsive visibility rules and typography remain. The asynchronous session check still reconciles genuine expiry/logout and retains its generation guard against stale responses. Access asset version is 1.1.1.

## Verification
Eight JavaScript tests pass, including pending-check preservation of the server label and stale-response protection after a newer authentication. PHP tests cover server reader failure and privacy gates, active session, malformed/missing cookie, HTTPS, disabled identity, revocation, idle timeout and expiry.

Guarded release: https://github.com/Ciborio71/BadAround/actions/runs/43028492681
Full Identity functional/MySQL CI: https://github.com/Ciborio71/BadAround/actions/runs/43028492597

Deployment uses six exact runtime files, prior-byte checks, outside-webroot backup, atomic replacement and readback. Existing private/no-store QA response policy is retained.

Visual navigation in the user's authenticated browser is not certified by this run; the available bridge is outside the authorized network. No IP authorization was bypassed. No event, identity or database records were changed.

Release completed PASS; six live file hashes verified by readback. Native WordPress source readback confirms header reader and bootstrap binding. Full Identity CI completed success.
