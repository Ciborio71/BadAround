<?php
declare(strict_types=1);
require __DIR__ . '/ownership-policy.php';
function chk(bool $value, string $label): void {
    if (!$value) { throw new RuntimeException('FAIL: '.$label); }
    echo "PASS: $label\n";
}
$owner = [
  'identity_id' => 'identity-a', 'resource_kind' => 'report',
  'resource_id' => 501, 'relation' => 'creator',
  'verified_binding_at' => '2026-11-03T09:00:00Z', 'revoked_at' => null
];
$test = static fn(?string $id, string $kind, int $rid, array $record):
    bool => BadAround_Identity_Ownership_Policy::may_edit($id,$kind,$rid,$record);
chk($test('identity-a','report',501,$owner), 'verified owner allowed');
chk(!$test('identity-b','report',501,$owner), 'other identity denied');
chk(!$test(null,'report',501,$owner), 'anonymous denied');
chk(!$test('identity-a','report',502,$owner), 'other report denied');
chk(!$test('identity-a','comment',501,$owner), 'other resource kind denied');
chk(!$test('identity-a','report',501,array_merge($owner,['verified_binding_at'=>null])), 'unverified binding denied');
chk(!$test('identity-a','report',501,array_merge($owner,['revoked_at'=>'now'])), 'revoked binding denied');
chk(!$test('identity-a','report',501,array_merge($owner,['relation'=>'viewer'])), 'view-only denied');
chk(!$test('identity-a','report',501,['email'=>'member@example.com']), 'email matching not ownership');
echo "ALL_OWNERSHIP_TESTS_PASS\n";
