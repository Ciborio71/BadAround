<?php
declare(strict_types=1);
require_once __DIR__.'/../wordpress/plugins/badaround-core/identity-dormant/native-intake-gate.php';
function check_gate(bool $v, string $name):void{
    if (!$v) throw new RuntimeException('FAIL '.$name);
    echo 'PASS '.$name.PHP_EOL;
}
$store = new BadAround_Identity_Session_Memory_Store();
$sessions = new BadAround_Identity_Sessions($store, str_repeat('K',32));
$active = new BadAround_Identity_Active_Memory_Status();
$gate = new BadAround_Identity_Native_Intake_Gate($sessions,$active);
$identity = 'test_identity_12345';
$token = $sessions->issue($identity,100);
$active->active[$identity] = true;
$result = $gate->authorize($token,101);
check_gate(is_array($result) && $result['identity_id']===$identity && $result['verified_at']===101,'verified session authorized');
check_gate(preg_match('/^[a-f0-9]{32}$/D',$result['operation_id'])===1,'server generated operation id');
$other = $gate->authorize($token,102);
check_gate($other['operation_id']!==$result['operation_id'],'operation IDs unique');
check_gate($gate->authorize(null,103)===null,'anonymous denied');
check_gate($gate->authorize('invalid',103)===null,'untrusted token denied');
check_gate($gate->authorize($token,0)===null,'invalid clock denied');
$active->active[$identity] = false;
check_gate($gate->authorize($token,103)===null,'revoked identity denied');
$active->active[$identity] = true;
check_gate($sessions->revoke($token,103),'session revoked in persistence');
check_gate($gate->authorize($token,104)===null,'revoked session denied');
$token2 = $sessions->issue($identity,200);
check_gate($gate->authorize($token2,200+BadAround_Identity_Sessions::IDLE_TIMEOUT)===null,'idle timeout denied');
check_gate($gate->authorize($token2,200+BadAround_Identity_Sessions::LIFETIME)===null,'absolute expiry denied');
echo 'ALL_NATIVE_INTAKE_GATE_TESTS_PASS'.PHP_EOL;
