<?php
declare(strict_types=1);
require __DIR__ . '/verification-coordinator.php';
function test_case(bool $pass, string $label): void {
    if (!$pass) { throw new RuntimeException("FAIL: ".$label); }
    echo "PASS: ".$label."\n";
}
$key = str_repeat('J', 32);
$now = 1762156800;
$store = new BadAround_Identity_Challenge_Memory_Store();
$verified = new BadAround_Identity_Verified_Memory_Resolver();
$sessionsStore = new BadAround_Identity_Session_Memory_Store();
$sessions = new BadAround_Identity_Sessions($sessionsStore, $key);
$verifier = new BadAround_Identity_Challenge_Verifier($store, $key);
$coordinator = new BadAround_Identity_Verification_Coordinator($store,$verifier,$verified,$sessions);
$c = BadAround_Identity_Challenge_Primitives::new_challenge('contact@example.com',$key,$now);
$store->insert($c['record']);
$id = $c['record']['challenge_id'];
test_case($coordinator->verify_and_issue($id,$c['delivery_code'],$now) === null, 'unverified identity not allowed');
test_case($store->load($id)['consumed_at'] === null, 'unverified challenge not consumed');
$verified->verified[$c['record']['email_lookup']] = 'identity_12345678';
test_case($coordinator->verify_and_issue($id,'1111111x',$now) === null, 'invalid OTP denied');
$token = $coordinator->verify_and_issue($id,$c['delivery_code'],$now+1);
test_case(is_string($token) && strlen($token) === 64, 'verified challenge issues bearer');
test_case($sessions->identity_for($token,$now+1) === 'identity_12345678', 'session bound to verified identity');
test_case($coordinator->verify_and_issue($id,$c['delivery_code'],$now+2) === null, 'cannot reuse OTP to create session');
test_case(count($sessionsStore->rows) === 1, 'exactly one session issued');
$c2 = BadAround_Identity_Challenge_Primitives::new_challenge('contact@example.com',$key,$now);
$store->insert($c2['record']);
$verified->verified[$c2['record']['email_lookup']] = 'identity_12345678';
unset($verified->verified[$c2['record']['email_lookup']]);
test_case($coordinator->verify_and_issue($c2['record']['challenge_id'],$c2['delivery_code'],$now) === null, 'revoked/removed identity cannot log in');
test_case($coordinator->verify_and_issue('invalid',$c['delivery_code'],$now) === null, 'malformed challenge denied');
echo "ALL_COORDINATOR_TESTS_PASS\n";
