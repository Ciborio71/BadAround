<?php
declare(strict_types=1);
require __DIR__ . '/challenge-primitives.php';
function check(bool $ok, string $label): void {
    if (!$ok) { throw new RuntimeException('FAIL: ' . $label); }
    echo "PASS: $label\n";
}
$key = str_repeat('k', 32);
$now = 1762156800;
$c = BadAround_Identity_Challenge_Primitives::new_challenge('  USER@Example.COM ', $key, $now);
$r = $c['record'];
check(strlen($c['delivery_code']) === 8, '8 digit OTP');
check(!array_key_exists('delivery_code', $r), 'OTP not in record');
check(!isset($r['email']), 'email absent from challenge record');
check($r['expires_at'] === $now + 600, '10m expiration');
check($r['code_digest'] !== $c['delivery_code'], 'OTP stored as digest');
$invalid = BadAround_Identity_Challenge_Primitives::verify($r, '0000000x', $key, $now + 1);
check(!$invalid['ok'] && $invalid['record']['attempts'] === 1, 'wrong attempt counted');
$expired = BadAround_Identity_Challenge_Primitives::verify($r, $c['delivery_code'], $key, $now + 600);
check(!$expired['ok'] && $expired['reason'] === 'expired', 'expiry strict');
$good = BadAround_Identity_Challenge_Primitives::verify($r, $c['delivery_code'], $key, $now + 2);
check($good['ok'] && $good['record']['consumed_at'] === $now + 2, 'correct OTP consumed');
check(!BadAround_Identity_Challenge_Primitives::verify($good['record'], $c['delivery_code'], $key, $now + 3)['ok'], 'cannot reuse consumed OTP');
for($i=0;$i<5;$i++) { $r = BadAround_Identity_Challenge_Primitives::verify($r, '99999999', $key, $now + 3)['record']; }
$locked = BadAround_Identity_Challenge_Primitives::verify($r, $c['delivery_code'], $key, $now + 4);
check(!$locked['ok'] && $locked['reason'] === 'locked', 'max attempts enforced');
check(BadAround_Identity_Challenge_Primitives::digest('email','lookup','user@example.com',$key)
  !== BadAround_Identity_Challenge_Primitives::digest('otp','lookup','user@example.com',$key), 'digest domain separation');
check(strlen(BadAround_Identity_Challenge_Primitives::generate_session_secret()) === 64, 'session secret CSPRNG');
echo "ALL_TESTS_PASS\n";
