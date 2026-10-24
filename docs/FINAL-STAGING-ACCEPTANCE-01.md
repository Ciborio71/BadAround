# FINAL-STAGING-ACCEPTANCE-01 — MVP RELEASE ACCEPTANCE

Date: 2026-10-24
Environment: staging only
Runtime candidate: 1184253c732afb51e6de5ea71dff3ca27f5108ff

## Result

TECHNICAL / PUBLIC ACCEPTANCE: PASS

No production deploy.
No merge.
No GEO contract change.
No publication semantic change.
No moderation semantic change.
No taxonomy change.

## Release candidate certification

GitHub Actions run 40112886955: SUCCESS
- PHP lint: 82/82
- Node regression suite: 89/89
- Staging runtime custody: 14/14 byte-identical

## Golden Path acceptance

Existing certified staging fixtures were reused to avoid unnecessary staging mutation.

Exact-point public fixture:
- Event #303
- status: publish
- Discovery: mode exact_point
- public radius: 0
- detail DOM: data-ba-event-mode="exact_point"
- detail DOM: aria-label="Mappa: Posizione esatta"
- public location copy: "Posizione esatta"

Approximate-area public fixture:
- Event #304
- status: publish
- Discovery: mode approximate_area
- public radius: 1000 m
- detail DOM: data-ba-event-mode="approximate_area"
- detail DOM: data-ba-event-radius="1000"
- detail DOM: aria-label="Mappa: Posizione approssimativa"
- public location copy: "Posizione approssimativa"

Public Discovery exposes approved public projection and passes privacy-boundary regression. No private-coordinate fallback is permitted by the certified suite.

## Territory consumer

Canonical territory URL verified:
- /territorio/torvaianica/
- rendered title: Torvaianica — Eventi — BadAround
- canonical link present
- 6 published events rendered
- event-card grid present
- GEO-PREC exact and approximate QA fixtures present

## Native Report Engine acceptance

UX remediation deployed and certified:
- Smart Address new-report reset clears provider UI state
- progressively hidden questions are not surfaced as presentation errors
- review sections expose direct "Modifica" navigation
- required-field affordance improved
- canonical validator and payload unchanged

## Sentinelle acceptance

- territory search control deployed
- canonical territory select preserved as data source
- D1/D2/D3 regressions PASS
- no account requirement changed

## Legal / privacy acceptance

Privacy Policy and Regole di pubblicazione aligned with certified GEO-PUBLIC behavior:
- exact_point and approximate_area both documented
- exact_point remains a request subject to moderation
- private source location remains segregated
- exact public projection is distinct from private source data

Privacy page:
- Accessibility 100/100 desktop
- Accessibility 100/100 mobile
- Best Practices 100/100 desktop/mobile

## Performance / responsive technical acceptance

Home mobile after PERF-POLISH-01:
- Performance 72
- LCP 5.8 s
- TBT 40 ms
- CLS 0.004

Map mobile:
- Accessibility 100
- Performance 61
- LCP 10.7 s
Google Maps remains the central performance cost. No click-to-load degradation was introduced.

Exact detail:
- desktop A11y 96 / BP 100 / Performance 87
- mobile A11y 96 / BP 100 / Performance 62

Approximate detail:
- desktop A11y 96 / BP 100 / Performance 91
- mobile A11y 96 / BP 100 / Performance 66

Torvaianica territory:
- desktop A11y 94 / BP 100 / Performance 96
- mobile A11y 95 / BP 100 / Performance 73

## Known non-blocking residuals

P2:
- isolated color-contrast findings on Home / Native / Sentinelle / territory/detail
- responsive image sizing / explicit image dimensions on some public cards
- Google Maps performance cost on Map and map-bearing event detail pages

These are not functional or privacy blockers for the MVP.

## Visual-device limitation

This session cannot certify a physical-device / exact viewport matrix at 1440, 1280, 1024, 768, 430, 390 and 360 px with a real virtual keyboard. Lighthouse desktop/mobile, responsive CSS, rendered DOM and automated browser-level regressions are PASS where stated.

A final human visual spot-check remains recommended before production but no technical blocker was demonstrated.

## Final staging state

FINAL-STAGING-ACCEPTANCE-01 = PASS WITH VISUAL SPOT-CHECK RESERVED

Next authorized gate:
RELEASE-MERGE-01
then BACKUP/ROLLBACK
then PRODUCTION-DEPLOY-01
then POST-PRODUCTION-SMOKE-01
