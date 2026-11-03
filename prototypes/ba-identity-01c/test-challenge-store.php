<?php
declare(strict_types=1);
require __DIR__ . '/challenge-store.php';
function assert_case(bool $ok, string $name): void {
    if (!$ok) { throw new RuntimeException('FAIL: ' . $name); }
    echo "PASS: " . $name . "\n";
}
$key = str_repeat('K', 32);
$now = 1762156800;
$created = BadAround_Identity_Challenge_Primitives::new_challenge('member@example.com', $key, $now);
$record = $created['record'];
$code = $created['delivery_code'];
$store = new BadAround_Identity_Challenge_Memory_Store();
$store->insert($record);
$verifier = new BadAround_Identity_Challenge_Verifier($store, $key);
$id = $record['challenge_id'];
assert_case($verifier->verify($id, '0000XXXX', $now + 1) === 'invalid', 'reject invalid OTP');
assert_case($store->load($id)['attempts'] === 1, 'persist failed attempt');
$stale = $store->load($id);
assert_case($verifier->verify($id, $code, $now + 2) === 'verified', 'consume valid OTP');
assert_case($store->load($id)['consumed_at'] === $now + 2, 'persist consumed state');
$stale['version']++;
assert_case(!$store->compare_and_swap($id, 1, $stale), 'cannot overwrite consumed state');
assert_case($verifier->verify($id, $code, $now + 3) === 'invalid', 'reject second use');
assert_case($verifier->verify('not-a-valid-id', $code, $now + 3) === 'invalid', 'invalid ID fails closed');
$another = BadAround_Identity_Challenge_Primitives::new_challenge('member@example.com', $key, $now);
$store->insert($another['record']);
for ($i = 0; $i < 5; $i++) {
    assert_case($verifier->verify($another['record']['challenge_id'], '88888888', $now + 2) === 'invalid', 'bad OTP rejected');
}
assert_case($verifier->verify($another['record']['challenge_id'], $another['delivery_code'], $now + 3) === 'invalid', 'locked after 5 attempts');
$third = BadAround_Identity_Challenge_Primitives::new_challenge('member@example.com', $key, $now, 60);
$store->insert($third['record']);
assert_case($verifier->verify($third['record']['challenge_id'], $third['delivery_code'], $now + 60) === 'invalid', 'expired challenge rejected');
echo "ALL_STORE_TESTS_PASS\n";
