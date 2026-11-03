<?php
declare(strict_types=1);
require __DIR__ . '/mysql-identity-active-status.php';
$dsn = (string)getenv('BA_TEST_DB_DSN');
if (!preg_match('/(?:^|;)dbname=ba_identity_ci(?:;|$)/',$dsn)) {
    throw new RuntimeException('Disposable CI database required');
}
$db = new PDO($dsn,getenv('BA_TEST_DB_USER') ?: 'ba_ci',getenv('BA_TEST_DB_PASS') ?: '');
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE IF NOT EXISTS ba_identity_status_ci (
    identity_id VARCHAR(128) NOT NULL PRIMARY KEY,
    verified_at BIGINT UNSIGNED NULL,
    revoked_at BIGINT UNSIGNED NULL
) ENGINE=InnoDB');
$db->exec('DELETE FROM ba_identity_status_ci');
$insert=$db->prepare('INSERT INTO ba_identity_status_ci (identity_id,verified_at,revoked_at)
    VALUES (?,?,?)');
$insert->execute(['identity_active_1',1762156800,null]);
$insert->execute(['identity_pending_2',null,null]);
$insert->execute(['identity_revoked_3',1762156800,1762156900]);
$checker=new BadAround_Identity_Mysql_Active_Status($db,'ba_identity_status_ci');
foreach (['identity_active_1'=>true,'identity_pending_2'=>false,
    'identity_revoked_3'=>false,'identity_absent_4'=>false] as $id=>$expected) {
    if ($checker->is_active_verified($id) !== $expected) {
        throw new RuntimeException('Identity status mismatch');
    }
}
$update=$db->prepare('UPDATE ba_identity_status_ci SET revoked_at=? WHERE identity_id=?');
$update->execute([1762156950,'identity_active_1']);
if ($checker->is_active_verified('identity_active_1')) {
    throw new RuntimeException('Revoked identity retained access');
}
echo "ALL_MYSQL_ACTIVE_STATUS_TESTS_PASS\n";
