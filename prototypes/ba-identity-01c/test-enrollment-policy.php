<?php
declare(strict_types=1);
require __DIR__ . '/enrollment-policy.php';
function enroll_check(bool $ok, string $label): void {
    if (!$ok) { throw new RuntimeException('FAIL: '.$label); }
    echo "PASS: ".$label."\n";
}
$key = str_repeat('E', 32);
$now = 1762156800;
$challengeStore = new BadAround_Identity_Challenge_Memory_Store();
$pending = new BadAround_Identity_Enrollment_Memory_Store();
$verifier = new BadAround_Identity_Challenge_Verifier($challengeStore, $key);
$service = new BadAround_Identity_Enrollment($pending, $challengeStore, $verifier, $key);
$profile = BadAround_Identity_Enrollment::validate_profile(' Anna ', ' Rossi ', 'ANNA@example.com');
enroll_check($profile['email'] === 'anna@example.com' && $profile['first_name'] === 'Anna', 'private fields normalized');
$missingDenied = false;
try { BadAround_Identity_Enrollment::validate_profile('', 'Rossi', 'anna@example.com'); }
catch (InvalidArgumentException $e) { $missingDenied = true; }
enroll_check($missingDenied, 'missing mandatory name rejected');
$invalidEmail = false;
try { BadAround_Identity_Enrollment::validate_profile('Anna', 'Rossi', 'not-an-email'); }
catch (InvalidArgumentException $e) { $invalidEmail = true; }
enroll_check($invalidEmail, 'invalid email rejected');

$c = BadAround_Identity_Challenge_Primitives::new_challenge($profile['email'], $key, $now);
$challengeStore->insert($c['record']);
$service->stage($profile, $c['record'], $now);
enroll_check($pending->verified_count() === 0, 'no identity created at stage');
enroll_check($service->verify_and_activate($c['record']['challenge_id'], 'garbage', $now + 1) === null, 'invalid code rejected');
enroll_check($pending->verified_count() === 0, 'invalid OTP does not activate');
$identity = $service->verify_and_activate($c['record']['challenge_id'], $c['delivery_code'], $now + 2);
enroll_check(is_string($identity) && str_starts_with($identity, 'identity_'), 'verified OTP activates identity');
enroll_check($pending->verified_count() === 1, 'one verified identity');
enroll_check($service->verify_and_activate($c['record']['challenge_id'], $c['delivery_code'], $now + 3) === null, 'OTP replay fails');
enroll_check($pending->verified_count() === 1, 'replay creates no duplicate identity');
$c2 = BadAround_Identity_Challenge_Primitives::new_challenge($profile['email'], $key, $now);
$challengeStore->insert($c2['record']);
$service->stage($profile, $c2['record'], $now);
enroll_check($service->verify_and_activate($c2['record']['challenge_id'], $c2['delivery_code'], $now + 5) === null, 'existing verified identity not overwritten');
enroll_check($pending->verified_count() === 1, 'duplicate email not duplicated');
$mismatched = BadAround_Identity_Challenge_Primitives::new_challenge('someoneelse@example.com', $key, $now);
$badBinding = false;
try { $service->stage($profile, $mismatched['record'], $now); }
catch (InvalidArgumentException $e) { $badBinding = true; }
enroll_check($badBinding, 'mismatched challenge/email HMAC rejected');
echo "ALL_ENROLLMENT_TESTS_PASS\n";
