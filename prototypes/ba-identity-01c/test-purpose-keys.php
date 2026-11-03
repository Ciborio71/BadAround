<?php
declare(strict_types=1);
require __DIR__ . '/purpose-keys.php';
$root=str_repeat('P',32);
$enroll=BadAround_Identity_Purpose_Keys::for_purpose('enrollment',$root);
$login=BadAround_Identity_Purpose_Keys::for_purpose('login',$root);
if (strlen($enroll)!==32 || strlen($login)!==32 || hash_equals($enroll,$login)) {
    throw new RuntimeException('Purpose keys not separated');
}
$now=1762156800;
$c=BadAround_Identity_Challenge_Primitives::new_challenge('purpose@example.com',$enroll,$now);
$good=BadAround_Identity_Challenge_Primitives::verify($c['record'],$c['delivery_code'],$enroll,$now+1);
if (!$good['ok']) { throw new RuntimeException('Enrollment challenge not accepted in enrollment context'); }
$cross=BadAround_Identity_Challenge_Primitives::verify($c['record'],$c['delivery_code'],$login,$now+1);
if ($cross['ok']) { throw new RuntimeException('Enrollment OTP accepted as login OTP'); }
$loginC=BadAround_Identity_Challenge_Primitives::new_challenge('purpose@example.com',$login,$now);
$reverse=BadAround_Identity_Challenge_Primitives::verify($loginC['record'],$loginC['delivery_code'],$enroll,$now+1);
if ($reverse['ok']) { throw new RuntimeException('Login OTP accepted as enrollment OTP'); }
if (hash_equals($c['record']['email_lookup'],$loginC['record']['email_lookup'])) {
    throw new RuntimeException('Lookup accidentally shared across purposes');
}
$denied=false;
try { BadAround_Identity_Purpose_Keys::for_purpose('reset-admin-password',$root); }
catch (InvalidArgumentException $e) { $denied=true; }
if (!$denied) { throw new RuntimeException('Unknown purpose allowed'); }
echo "ALL_PURPOSE_SEPARATION_TESTS_PASS\n";
