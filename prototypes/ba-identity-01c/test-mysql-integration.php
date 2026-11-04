<?php
/** MySQL-only integration test. Uses an ephemeral CI database. NEVER production. */
declare(strict_types=1);
require __DIR__ . '/mysql-challenge-store.php';
require __DIR__ . '/mysql-session-store.php';

function check_sql(bool $pass, string $label): void {
    if (!$pass) { throw new RuntimeException("FAIL $label"); }
    echo "PASS $label\n";
}
$dsn = getenv('BA_TEST_DB_DSN');
if (!$dsn || !str_contains($dsn, 'dbname=ba_identity_ci')) {
    throw new RuntimeException('Safety stop: only ba_identity_ci database allowed');
}
$db = new PDO($dsn, getenv('BA_TEST_DB_USER') ?: 'root', getenv('BA_TEST_DB_PASS') ?: '');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE IF NOT EXISTS ba_identity_challenges (
    challenge_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
    email_lookup CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    code_digest CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    created_at BIGINT UNSIGNED NOT NULL, expires_at BIGINT UNSIGNED NOT NULL,
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    consumed_at BIGINT UNSIGNED NULL, version BIGINT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB');
$db->exec('CREATE TABLE IF NOT EXISTS ba_identity_sessions (
    session_digest CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
    identity_id VARCHAR(128) NOT NULL, created_at BIGINT UNSIGNED NOT NULL,
    last_seen_at BIGINT UNSIGNED NOT NULL, expires_at BIGINT UNSIGNED NOT NULL,
    revoked_at BIGINT UNSIGNED NULL
) ENGINE=InnoDB');
$db->exec('DELETE FROM ba_identity_challenges');
$db->exec('DELETE FROM ba_identity_sessions');
$key = str_repeat('K', 32);
$now = 1762156800;
$c = BadAround_Identity_Challenge_Primitives::new_challenge('test@example.com',$key,$now);
$store = new BadAround_Identity_Mysql_Challenge_Store($db,'ba_identity_challenges');
$store->insert($c['record']);
$old = $store->load($c['record']['challenge_id']);
check_sql($old['version']===0 && $old['consumed_at']===null,'MySQL challenge inserted');
$v = new BadAround_Identity_Challenge_Verifier($store,$key);
check_sql($v->verify($old['challenge_id'],$c['delivery_code'],$now+1)==='verified','OTP atomically consumed in MySQL');
check_sql($v->verify($old['challenge_id'],$c['delivery_code'],$now+2)==='invalid','cannot reuse OTP');
$stale = $old;
$stale['version'] = 1;
$stale['consumed_at'] = $now+1;
check_sql(!$store->compare_and_swap($old['challenge_id'],0,$stale),'stale CAS rejected');
// A failed email delivery must invalidate a persisted challenge atomically.
$undelivered=BadAround_Identity_Challenge_Primitives::new_challenge('unsent@example.com',$key,$now);
$store->insert($undelivered['record']);
$undeliveredId=$undelivered['record']['challenge_id'];
$beforeInvalidation=$store->load($undeliveredId);
$store->invalidate($undeliveredId,$now+3);
$afterInvalidation=$store->load($undeliveredId);
check_sql($afterInvalidation['consumed_at']===$now+3 && $afterInvalidation['version']===1,
    'Undelivered OTP is invalidated in MySQL');
check_sql($v->verify($undeliveredId,$undelivered['delivery_code'],$now+4)==='invalid',
    'Undelivered OTP cannot authenticate');
$store->invalidate($undeliveredId,$now+5);
check_sql($store->load($undeliveredId)['version']===1,
    'OTP invalidation is idempotent');
$staleUndelivered=$beforeInvalidation;
$staleUndelivered['version']=1;
$staleUndelivered['consumed_at']=$now+4;
check_sql(!$store->compare_and_swap($undeliveredId,0,$staleUndelivered),
    'Stale OTP verifier cannot undo invalidation');
$sessionStore = new BadAround_Identity_Mysql_Session_Store($db,'ba_identity_sessions');
$sessions = new BadAround_Identity_Sessions($sessionStore,$key);
$secret = $sessions->issue('identity_87654321',$now);
check_sql($sessions->identity_for($secret,$now)==='identity_87654321','MySQL session issued');
check_sql($sessions->revoke($secret,$now+20),'MySQL session revoked');
check_sql($sessions->identity_for($secret,$now+21)===null,'revoked session denied');
$multi1=$sessions->issue('identity_87654321',$now+30);
$multi2=$sessions->issue('identity_87654321',$now+31);
$unrelated=$sessions->issue('identity_11111111',$now+32);
check_sql($sessions->revoke_all('identity_87654321',$now+33)===2,'MySQL bulk identity revocation');
check_sql($sessions->identity_for($multi1,$now+34)===null,'MySQL revoked session 1 rejected');
check_sql($sessions->identity_for($multi2,$now+34)===null,'MySQL revoked session 2 rejected');
check_sql($sessions->identity_for($unrelated,$now+34)==='identity_11111111','MySQL unrelated identity preserved');
echo "ALL_MYSQL_TESTS_PASS\n";
