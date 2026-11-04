<?php
/** Disposable ba_identity_ci only. No WordPress database used. */
declare(strict_types=1);
require __DIR__.'/mysql-ownership-recovery-store.php';
$dsn=getenv('BA_TEST_DB_DSN');
if (!$dsn || !str_contains($dsn,'dbname=ba_identity_ci')) {
    throw new RuntimeException('Safety stop: only ba_identity_ci database allowed');
}
$db=new PDO($dsn,getenv('BA_TEST_DB_USER')?:'root',getenv('BA_TEST_DB_PASS')?:'');
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec("CREATE TABLE IF NOT EXISTS ba_identity_recovery_ci (
 operation_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
 identity_id VARCHAR(128) NOT NULL,report_id BIGINT UNSIGNED NOT NULL,
 event_id BIGINT UNSIGNED NOT NULL,state VARCHAR(24) NOT NULL,
 attempts INT UNSIGNED NOT NULL DEFAULT 0,created_at BIGINT UNSIGNED NOT NULL,
 resolved_at BIGINT UNSIGNED NULL,revision BIGINT UNSIGNED NOT NULL DEFAULT 0,
 UNIQUE KEY uq_report (report_id)
) ENGINE=InnoDB");
$db->exec('DELETE FROM ba_identity_recovery_ci');
function recovery_check(bool $ok,string $message):void {
    if (!$ok) { throw new RuntimeException('FAIL: '.$message); }
    echo 'PASS: '.$message.PHP_EOL;
}
$store=new BadAround_Identity_Mysql_Ownership_Recovery_Store($db,'ba_identity_recovery_ci');
$now=1762156800;
$intent=BadAround_Identity_Ownership_Recovery_Policy::new_intent(
    'identity_12345678',['report_id'=>701,'event_id'=>801,'duplicate'=>false],str_repeat('a',32),$now
);
recovery_check($store->create($intent),'persist pending recovery');
recovery_check(!$store->create($intent),'duplicate operation denied');
recovery_check(!$store->create(array_merge($intent,['operation_id'=>str_repeat('b',32)])),
    'different operation cannot take existing report');
$loaded=$store->load($intent['operation_id']);
recovery_check($loaded!==null && $loaded['state']==='pending' && $loaded['revision']===0,
    'recovery state read from database');
recovery_check($store->transition($intent['operation_id'],0,'retry',$now+1),
    'retry CAS committed');
recovery_check(!$store->transition($intent['operation_id'],0,'granted',$now+2),
    'stale version refused');
recovery_check($store->transition($intent['operation_id'],1,'granted',$now+2),
    'grant CAS committed');
$bound=$store->load($intent['operation_id']);
recovery_check($bound['state']==='bound' && $bound['revision']===2,
    'bound ownership persisted');
recovery_check(!$store->transition($intent['operation_id'],2,'retry',$now+3),
    'resolved recovery immutable');
echo "ALL_MYSQL_OWNERSHIP_RECOVERY_TESTS_PASS\n";
