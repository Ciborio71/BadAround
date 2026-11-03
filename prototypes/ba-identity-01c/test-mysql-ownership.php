<?php
/** Isolated MySQL ownership tests; no WordPress production connection. */
declare(strict_types=1);
require __DIR__ . '/mysql-ownership-store.php';
$dsn = (string)getenv('BA_TEST_DB_DSN');
if (!preg_match('/(?:^|;)dbname=ba_identity_ci(?:;|$)/', $dsn)) {
    throw new RuntimeException('Safety stop: disposable ba_identity_ci required');
}
$pdo = new PDO($dsn,getenv('BA_TEST_DB_USER') ?: 'ba_ci',getenv('BA_TEST_DB_PASS') ?: '',[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
]);
$pdo->exec('CREATE TABLE IF NOT EXISTS ba_identity_ownership_ci (
  identity_id VARCHAR(128) NOT NULL,
  resource_kind VARCHAR(32) NOT NULL,
  resource_id BIGINT UNSIGNED NOT NULL,
  relation VARCHAR(32) NOT NULL,
  verified_binding_at BIGINT UNSIGNED NOT NULL,
  revoked_at BIGINT UNSIGNED NULL,
  UNIQUE KEY uq_resource (resource_kind,resource_id),
  KEY idx_identity (identity_id)
) ENGINE=InnoDB');
$pdo->exec('DELETE FROM ba_identity_ownership_ci');
$store = new BadAround_Identity_Mysql_Ownership_Store($pdo,'ba_identity_ownership_ci');
function check_owner(bool $condition, string $label): void {
    if (!$condition) { throw new RuntimeException('FAIL '.$label); }
    echo "PASS ".$label."\n";
}
check_owner($store->grant_creator('identity_12345678','report',501,1762156800),'initial ownership granted');
check_owner(!$store->grant_creator('identity_98765432','report',501,1762156801),'other identity cannot overwrite resource binding');
check_owner($store->may_edit('identity_12345678','report',501),'verified owner can edit own report');
check_owner(!$store->may_edit('identity_98765432','report',501),'other identity denied');
check_owner(!$store->may_edit('identity_12345678','report',502),'other report denied');
check_owner(!$store->may_edit('identity_12345678','comment',501),'cross kind denied');
$pdo->exec("UPDATE ba_identity_ownership_ci SET revoked_at=1762156802
    WHERE resource_kind='report' AND resource_id=501");
check_owner(!$store->may_edit('identity_12345678','report',501),'revoked binding denied');
echo "ALL_MYSQL_OWNERSHIP_TESTS_PASS\n";
