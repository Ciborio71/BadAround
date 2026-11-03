<?php
/** Disposable database only; no WordPress production access. */
declare(strict_types=1);
require __DIR__.'/mysql-wp-actor-binding.php';
$dsn=getenv('BA_TEST_DB_DSN');
if (!$dsn || !str_contains($dsn,'dbname=ba_identity_ci')) {
    throw new RuntimeException('Safety stop: only ba_identity_ci permitted');
}
$db=new PDO($dsn,getenv('BA_TEST_DB_USER')?:'root',getenv('BA_TEST_DB_PASS')?:'');
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec("CREATE TABLE IF NOT EXISTS ba_wp_actor_ci (
    wp_user_id BIGINT UNSIGNED NOT NULL,
    identity_id VARCHAR(128) NOT NULL,
    verified_at BIGINT UNSIGNED NOT NULL,
    revoked_at BIGINT UNSIGNED NULL,
    version INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (wp_user_id),
    UNIQUE KEY identity_unique(identity_id)
) ENGINE=InnoDB");
$db->exec('DELETE FROM ba_wp_actor_ci');
function binding_check(bool $ok,string $message):void {
    if (!$ok) { throw new RuntimeException('FAIL: '.$message); }
    echo 'PASS: '.$message.PHP_EOL;
}
$store=new BadAround_Identity_Mysql_WP_Actor_Binding($db,'ba_wp_actor_ci');
$now=1762156800;
binding_check(!$store->matches_current_user(42,'identity_12345678'),
    'unprovisioned actor cannot moderate');
binding_check($store->grant_verified(42,'identity_12345678',$now),
    'trusted verified provision persisted');
binding_check($store->matches_current_user(42,'identity_12345678'),
    'same actor binding accepted');
binding_check(!$store->matches_current_user(43,'identity_12345678'),
    'unrelated WordPress user rejected');
binding_check(!$store->matches_current_user(42,'identity_87654321'),
    'different BadAround identity rejected');
binding_check(!$store->grant_verified(42,'identity_87654321',$now),
    'same WordPress actor cannot bind a different identity');
binding_check(!$store->grant_verified(43,'identity_12345678',$now),
    'same Identity cannot bind a different WordPress actor');
binding_check($store->revoke(42,'identity_12345678',0,$now+1),
    'revocation CAS persisted');
binding_check(!$store->matches_current_user(42,'identity_12345678'),
    'revocation immediately blocks authorization');
binding_check(!$store->revoke(42,'identity_12345678',0,$now+2),
    'stale revocation refused');
binding_check(!$store->grant_verified(42,'identity_12345678',$now+2),
    'revoked row cannot be silently reactivated through duplicate insert');
echo "ALL_MYSQL_WP_ACTOR_BINDING_TESTS_PASS\n";
