<?php
/** MySQL-only enrollment integration; requires disposable ba_identity_ci DB. */
declare(strict_types=1);
require __DIR__ . '/mysql-enrollment-store.php';
require_once __DIR__ . '/mysql-challenge-store.php';
if (!extension_loaded('pdo_mysql')) { throw new RuntimeException('pdo_mysql unavailable'); }
$dsn = (string)getenv('BA_TEST_DB_DSN');
if (!preg_match('/(?:^|;)dbname=ba_identity_ci(?:;|$)/', $dsn)) {
    throw new RuntimeException('Only disposable ba_identity_ci allowed');
}
$db = new PDO($dsn, getenv('BA_TEST_DB_USER') ?: 'ba_ci', getenv('BA_TEST_DB_PASS') ?: '');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec("CREATE TABLE IF NOT EXISTS ba_identity_pending (
    challenge_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
    email_lookup CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    protected_profile TEXT NOT NULL, created_at BIGINT UNSIGNED NOT NULL,
    verified_at BIGINT UNSIGNED NULL
) ENGINE=InnoDB");
$db->exec("CREATE TABLE IF NOT EXISTS ba_identities_ci (
    identity_id VARCHAR(128) NOT NULL PRIMARY KEY,
    email_lookup CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
    protected_profile TEXT NOT NULL,
    verified_at BIGINT UNSIGNED NOT NULL, revoked_at BIGINT UNSIGNED NULL
) ENGINE=InnoDB");
$db->exec("CREATE TABLE IF NOT EXISTS ba_identity_challenges (
    challenge_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
    email_lookup CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    code_digest CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    created_at BIGINT UNSIGNED NOT NULL, expires_at BIGINT UNSIGNED NOT NULL,
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    consumed_at BIGINT UNSIGNED NULL, version BIGINT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB");
$db->exec('DELETE FROM ba_identity_pending');
$db->exec('DELETE FROM ba_identities_ci');
$key = str_repeat('K',32);
$encryptKey = str_repeat('E',32);
$now = 1762156800;
$pending = new BadAround_Identity_Mysql_Enrollment_Store($db, 'ba_identity_pending', 'ba_identities_ci', $encryptKey);
$challengeStore = new BadAround_Identity_Mysql_Challenge_Store($db, 'ba_identity_challenges');
$verifier = new BadAround_Identity_Challenge_Verifier($challengeStore,$key);
$service = new BadAround_Identity_Enrollment($pending,$challengeStore,$verifier,$key);
$legacyBlocked = false;
try { $pending->activate_once(str_repeat('0',32),str_repeat('a',64),$now); }
catch (LogicException $e) { $legacyBlocked = true; }
if (!$legacyBlocked) { throw new RuntimeException('Legacy non-atomic activation must be blocked'); }
$profile = BadAround_Identity_Enrollment::validate_profile('Anna','Rossi','anna@example.com');
$c = BadAround_Identity_Challenge_Primitives::new_challenge($profile['email'],$key,$now);
$challengeStore->insert($c['record']);
$service->stage($profile,$c['record'],$now);
$stmt=$db->prepare('SELECT protected_profile FROM ba_identity_pending WHERE challenge_id=?');
$stmt->execute([$c['record']['challenge_id']]);
$blob=(string)$stmt->fetchColumn();
if (str_contains($blob,'Anna') || str_contains($blob,'anna@example.com')) {
    throw new RuntimeException('PII leaked in pending storage');
}
if ($service->verify_and_activate($c['record']['challenge_id'],'0000000x',$now+1)!==null) {
    throw new RuntimeException('Bad OTP activated identity');
}
$id=$service->verify_and_activate($c['record']['challenge_id'],$c['delivery_code'],$now+2);
if (!is_string($id) || !str_starts_with($id,'identity_')) {
    throw new RuntimeException('Verified enrollment failed');
}
if ($service->verify_and_activate($c['record']['challenge_id'],$c['delivery_code'],$now+3)!==null) {
    throw new RuntimeException('OTP reused');
}
$c2 = BadAround_Identity_Challenge_Primitives::new_challenge($profile['email'],$key,$now);
$challengeStore->insert($c2['record']);
$service->stage($profile,$c2['record'],$now);
if ($service->verify_and_activate($c2['record']['challenge_id'],$c2['delivery_code'],$now+4)!==null) {
    throw new RuntimeException('Duplicate verified email must fail');
}
$rejectedChallenge = $challengeStore->load($c2['record']['challenge_id']);
if ($rejectedChallenge['consumed_at'] !== null || $rejectedChallenge['attempts'] !== 0) {
    throw new RuntimeException('Failed enrollment must rollback OTP consumption');
}
$first = $challengeStore->load($c['record']['challenge_id']);
if ($first['consumed_at'] !== $now+2 || $first['attempts'] !== 2) {
    throw new RuntimeException('Successful activation must commit OTP once');
}
$count=(int)$db->query('SELECT COUNT(*) FROM ba_identities_ci')->fetchColumn();
if ($count!==1) { throw new RuntimeException('Expected one identity'); }
echo "ALL_MYSQL_ENROLLMENT_TESTS_PASS\n";
