# MAP-CONTROLS-01B — fullscreen diagnosis

## Classification

ROOT CAUSE: UNKNOWN.

No application patch, staging upload, production operation, merge, DB/taxonomy mutation, report creation or fixture mutation was performed. The reconciled candidate remains 56853ec079cbd9bdd1f466104790e07f54bb60d1 and is not deployed. Staging remains the rollback baseline.

## PLATFORM

fullscreenEnabled: actual page-world value NOT OBTAINED. The documented read-only inspection scope returned JavaScript undefined (stringified as "undefined"), not false.

requestFullscreen: actual browser availability NOT DETERMINED. typeof Element.prototype.requestFullscreen returned "undefined" in the restricted inspection scope.

exitFullscreen: actual browser availability NOT DETERMINED. typeof document.exitFullscreen returned "undefined" in that same scope.

fullscreenElement: actual page-world value NOT OBTAINED. Inspection returned undefined, not null.

Direct native fullscreen: NOT EXECUTED — QA capability blocker. A harmless standalone local HTML probe was prepared with an element, a user-click button calling target.requestFullscreen(), promise-result recording, fullscreenchange/fullscreenerror listeners, and exitFullscreen. Its attempted opening in the same cloud browser was rejected by browser URL policy because file: is not an allowed protocol. It was never loaded or executed. No alternate protocol, indirect execution, raw CDP, injected script workaround or security bypass was attempted. The temporary local probe was removed afterward. This rejection is not evidence that native requestFullscreen itself fails.

Embedding: ordinary top-level browser tabs opened directly on staging; inspection reported window.top === window.self as true and referrer "". One descendant iframe without sandbox or allow attributes was observed. This is not a controlled iframe-policy experiment.

Permissions Policy: document.permissionsPolicy and document.featurePolicy were not exposed by the read-only inspection scope. A separate ordinary CLI HTTPS GET to /mappa/ returned HTTP 200. Its Permissions-Policy header listed private-state-token-redemption and private-state-token-issuance only; no fullscreen directive was present. That transport response is not asserted to be the exact browser-session response, and does not establish effective browser policy.

Vendor API fallback was not used because native API availability itself could not be measured reliably.

## GOOGLE

CONTROL RENDERED: PASS on public Mappa baseline.

CLICK RECEIVED: PASS for observable control-state activation — aria-pressed changed false to true. This does not independently prove a successful native fullscreen request.

fullscreenchange: NOT OBSERVED / no page-world listener installed. Do not report NO as if instrumented.

fullscreenerror: NOT OBSERVED / no page-world listener installed. Do not report NO as if instrumented.

fullscreenElement: NOT OBTAINED in actual page world.

Public Mappa before and after:
- map #ba-google-map class: ba-google-map, unchanged
- inline style: overflow: hidden;, unchanged
- rectangle: 958 × 3007.734375 px, unchanged
- Google button aria-pressed: false → true
- page remained visually embedded in screenshot.

The observation reproduces the symptom without the candidate navigation helper being active. It does not isolate Google Maps, browser/platform, effective policy, or the browser automation layer.

Previous run 39939688213 demonstrated rendered Home fullscreen control and a staging observer output fullscreen=false. That run was rolled back byte-exact. This historical result is supporting evidence only, not a fresh native-platform test.

## MAP MATRIX

| Surface | Current rollback-baseline behavior | Diagnosis |
|---|---|---|
| Home | Live map present; initializer explicitly sets fullscreenControl:false for Home | Candidate fullscreen interaction cannot be retested without deploying or injecting behavior; not an application failure |
| Public Mappa | Baseline enables fullscreen; control visible and becomes pressed; no visible expansion | Symptom reproduced; root cause UNKNOWN |
| Detail #363 | Live map present; Google fullscreen DOM node exists but isVisible() is false; baseline initializer sets fullscreenControl:false | Candidate fullscreen interaction not testable on current baseline |
| Native | Native page loads; baseline location-map initializer explicitly sets fullscreenControl:false | No live Native map acquired for this diagnosis; candidate fullscreen interaction not executed |

Correction to an intermediate progress update: the completed source audit shows Native baseline fullscreenControl:false as well; only the public Mappa baseline enables it.

It would be incorrect to label all four maps ENV BLOCKED or to infer a shared environmental cause from this matrix.

## APPLICATION AUDIT — candidate 56853ec0

fullscreenControl enabled: PASS — shared options() returns true at RIGHT_TOP, used by Native and both public initializer branches.

disableDefaultUI conflict: NONE IDENTIFIED — explicitly false. cameraControl:false retains explicit zoom controls.

JS interception: NONE IDENTIFIED. No requestFullscreen, exitFullscreen or fullscreenchange handler exists in application runtime. The shared helper installs click handlers only on its own four directional buttons; each calls existing map.panBy. It does not intercept Google's button, stop propagation or prevent its default action.

CSS conflict: NONE CAUSALLY PROVEN. Candidate map-navigation.css changes min-height, custom-pad sizing, and legend/preview/privacy-note placement. It does not target :fullscreen, .gm-fullscreen-control, Google click handling or fullscreen API.

Actual public-map ancestor inspection on rollback baseline:
- #ba-google-map: position:absolute; overflow:hidden; transform:none; pointer-events:auto; z-index:auto
- .ba-map-canvas: position:relative; overflow:hidden; transform:none; pointer-events:auto
- .ba-map-workspace: position:static; overflow:visible; transform:none; pointer-events:auto
- main: position:static; overflow:visible; transform:none; pointer-events:auto
- body: position:relative; overflow:hidden auto; transform:none; pointer-events:auto
- html: position:static; overflow:visible; transform:none; pointer-events:auto

These rules alone do not prove that fullscreen is prevented or masked. No CSS change was attempted.

Sources audited:
- wordpress/themes/badaround-child/assets/js/map-navigation.js
- wordpress/themes/badaround-child/assets/js/map.js
- wordpress/themes/badaround-child/assets/js/native-report/location-map.js
- wordpress/themes/badaround-child/assets/css/map-navigation.css
- wordpress/themes/badaround-child/assets/css/map.css
- wordpress/themes/badaround-child/assets/css/home.css
- wordpress/themes/badaround-child/assets/css/native-report.css
- repository-wide fullscreen/requestFullscreen/setOptions search.

## Browser comparison

Cloud browser: available, same browser ID 1 as previous live attempt.

Independent normal interactive desktop Chromium: not available through the authorized tools. No GitHub-hosted live staging browser run was attempted.

## Certification decision

FULLSCREEN: NOT CERTIFIED, not ENVIRONMENT N/A.

The required direct native API experiment could not execute. Therefore browser/platform unavailability is not proven. No application defect is proven either.

The inspection-scope undefined values must not be presented as native browser values. Remaining causal experiments require an execution environment/instrumentation capability that can observe and trigger the actual page-world API through a trusted user gesture, without redeploying application code or bypassing browser policy.

No persistent diagnostic component exists on staging. Only documentation/evidence is stored in the diagnostic repository branch.

## FINAL VERDICT

MAP-CONTROLS-01B STOP — FULLSCREEN ROOT CAUSE UNKNOWN

