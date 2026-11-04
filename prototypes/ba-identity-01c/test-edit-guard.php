<?php
declare(strict_types=1);
require __DIR__ . '/edit-guard.php';
function case_guard(bool $value, string $label): void {
    if (!$value) { throw new RuntimeException('FAIL: '.$label); }
    echo "PASS: $label\n";
}
$key = str_repeat('K', 32);
$now = 1762156800;
$store = new BadAround_Identity_Session_Memory_Store();
$sessions = new BadAround_Identity_Sessions($store, $key);
$token = $sessions->issue('identity_12345678', $now);
$csrf = BadAround_Identity_Csrf_Policy::issue($token, $key);
$ownership = [
    'identity_id' => 'identity_12345678', 'resource_kind' => 'report',
    'resource_id' => 501, 'relation' => 'creator',
    'verified_binding_at' => '2026-11-03T09:00:00Z', 'revoked_at' => null
];
$status = new BadAround_Identity_Active_Memory_Status();
$status->active['identity_12345678'] = true;
$guard = new BadAround_Identity_Edit_Guard($sessions,$key,'https://badaround.it',$status);
$can = static fn(?string $s,?string $c,?string $o,string $type,int $id,array $owner,int $t):
    bool => $guard->may_submit_revision($s,$c,$o,$type,$id,$owner,$t);
case_guard($can($token,$csrf,'https://badaround.it','report',501,$ownership,$now),'owner with CSRF and session allowed');
case_guard(!$can(null,$csrf,'https://badaround.it','report',501,$ownership,$now),'missing session denied');
case_guard(!$can($token,null,'https://badaround.it','report',501,$ownership,$now),'missing CSRF denied');
case_guard(!$can($token,$csrf,'https://evil.example','report',501,$ownership,$now),'cross origin denied');
case_guard(!$can($token,$csrf,'https://badaround.it/path','report',501,$ownership,$now),'invalid origin format denied');
case_guard(!$can($token,$csrf,'https://badaround.it','report',502,$ownership,$now),'other resource denied');
case_guard(!$can($token,$csrf,'https://badaround.it','report',501,array_merge($ownership,['identity_id'=>'someone_else']),$now),'other owner denied');
case_guard(!$can($token,$csrf,'https://badaround.it','report',501,$ownership,$now+86400),'expired session denied');
$status->active['identity_12345678'] = false;
case_guard(!$can($token,$csrf,'https://badaround.it','report',501,$ownership,$now+1),'revoked identity denied with live session');
$status->active['identity_12345678'] = true;
$sessions->revoke($token,$now+1);
case_guard(!$can($token,$csrf,'https://badaround.it','report',501,$ownership,$now+2),'revoked session denied');
echo "ALL_EDIT_GUARD_TESTS_PASS\n";
