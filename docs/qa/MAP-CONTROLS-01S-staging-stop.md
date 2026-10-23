# MAP-CONTROLS-01S — STOP before application upload

Candidate:443bb4cb90782a45d058ce03717762c3648f3d6c
Source branch:feature/map-controls-01-global-navigation
Repository:Ciborio71/BadAround
Staging only.

## DEPLOY
RUN:39922002566
RESULT:FAIL — pre-upload custody gate
REMOTE HASHES:FAIL,58/59 match; functions.php differs
ROLLBACK:NOT REQUIRED — upload never started
Earlier preflight run39921737539 reached the same custody gate; neither run uploaded application files.
The second launch also corrected a QA-only referrer-policy setting before any QA component installation. This did not affect application code.

## Exact blocker
File:wordpress/themes/badaround-child/functions.php

|State|SHA-256|
|---|---|
|Expected baseline879f55853fbb99c20ad85bc522e03f19f93bc3c6|8a3aae7fc64b0cbf2ee08b9b0a09bef6c3d20b661569766ba991fb3322ab5b21|
|Actual remote|693068d807119ebd1691dd3a6dd7401d9d97363ed5cfc799dcbdde5ab141ce6c|
|Candidate443bb4cb90782a45d058ce03717762c3648f3d6c|1cb2b13bd940d80748ee49dde7ec4e23ae4dcda3abc9db7f9b8fbab47f2d871f|

Actual remote hash exactly matches repository blob3174eae647dd31466aeb176e000f7a620d9657f5 from commit4622bec87e051f2000884abc0095b258fe2bfcb5 (“ui(admin): disable Gutenberg and use classic editor on BadAround staging”).

Compared with expected879f baseline, the actual remote file retains only this previously approved seven-line comment/filter block:

```php
/**
 * BadAround admin: prefer the classic WordPress editor on every post type.
 * This is UI-only; moderation/publication permissions and hooks stay intact.
 */
add_filter( 'use_block_editor_for_post_type', '__return_false', 100 );
add_filter( 'use_block_editor_for_post', '__return_false', 100 );
```

Deploying the candidate functions.php would remove these two Classic Editor filters in addition to introducing map navigation. The fixed candidate was not modified and no reconciliation was performed automatically.
A reconciled candidate preserving the approved filters, renewed CI and explicit deployment authorization are required before retry.

## MAP INVENTORY
NATIVE/HOME/PUBLIC MAP/DETAIL:NOT EXECUTED LIVE — blocked before upload.
MODERATION:NO MAP in audited baseline source; no live moderation action.
OTHER:no additional inventory check performed during this stopped run.

## CONTROLS
ZOOM+/ZOOM-/DRAG PAN/CUSTOM PAN/FULLSCREEN/KEYBOARD/FOCUS:
NOT EXECUTED LIVE — candidate never installed.
No mobile/touch/fullscreen or iOS pass is claimed.

## NATIVE
EXACT/APPROX/RADIUS IMMUTABLE/MAP PAN DOES NOT MOVE REPORT POINT:
NOT EXECUTED LIVE.

## HOME
CLUSTERS/LEGEND/CONTROLS:NOT EXECUTED LIVE.

## PUBLIC MAP
CONTROLS/CLUSTERS/EXACT/APPROX:NOT EXECUTED LIVE.

## DETAIL
EXACT/APPROX100/APPROX500/APPROX1000:NOT EXECUTED LIVE.
Read-only existing discovery before preflight still returned the four protected fixtures with expected modes/radii.

## RESPONSIVE
320/390/768/1024/1440:NOT EXECUTED LIVE.
CONTROL OVERLAP/ATTRIBUTION OVERLAP:NOT TESTED.
No live screenshots were generated; earlier offline images are not relabeled as staging evidence.

## PERFORMANCE
NEW HEAVY DEPENDENCY:NO application deployment.
SECOND GOOGLE MAPS LOAD/SECOND MAP INSTANCE/DUPLICATE CONTROLS:NOT TESTED LIVE.

## REGRESSION
GEO-PREC/GEO-AREA/ALL RADII/NATIVE/SMART ADDRESS/GEOLOCATION/HOME/DISCOVERY/DETAIL/MODERATION:
live certification NOT EXECUTED after the blocking custody gate.
Prior dedicated candidate CI39891617495 remains successful; this is not new live certification.
DB/TAXONOMY/PROTECTED FIXTURES:UNCHANGED BY THIS TASK — no mutation operation executed.
New reports/events:0. No moderation, republication, migration or submission.
All58 other custody entries match, including certified Native/GEO-AREA/Core/Home files and expected absence of the two new navigation assets.

## CLEANUP
Temporary probe installation was skipped. Final FTPS directory/read check verifies absence ofmu-plugins/mapcontrols01s-443bb4cb-qa.php.
POST/mapcontrols01s-qa/v1/snapshot returns404 rest_no_route.
No temporary remote component remains.

## Evidence
Run39922002566 artifact12487731379:
SHA256 d8f3b76bbba1661fdc7e63625b807b0f9a6230419132e48423e93428f62af6af
Independently downloaded and verified. Contains only custody-before.json and temporary-probe-removed.txt.
No upload-started.txt, uploaded.txt, probe-installed.txt or rollback-used.txt exists.
Earlier run39921737539 artifact12487552340 SHA25685a3599d5c4617b4c344c1abef8cd1c5dbfce06f0251161a91cb9702754f6b65 was independently verified with the same mismatch and no application upload.

MAP-CONTROLS-01 STOP — GLOBAL MAP NAVIGATION NOT CERTIFIED ON STAGING

No production, merge, NF-FINAL-01 or PUBLIC-DETAIL-01 action.
