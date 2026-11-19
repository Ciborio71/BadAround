<?php
/** Database-backed durable handoff test. Ephemeral ba_identity_ci only. */
declare(strict_types=1);
require_once __DIR__.'/../wordpress/plugins/badaround-core/identity-dormant/recovery-store-bridge.php';
$dsn=(string)getenv('BA_TEST_DB_DSN');
if (!str_contains($dsn,'dbname=ba_identity_ci')) throw new RuntimeException('Unsafe database DSN');
$db=new PDO($dsn,(string)getenv('BA_TEST_DB_USER'),(string)getenv('BA_TEST_DB_PASS'));
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec("CREATE TABLE IF NOT EXISTS ba_identity_wizard_recovery_ci (
operation_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
identity_id VARCHAR(128) NOT NULL,
report_id BIGINT UNSIGNED NOT NULL,
event_id BIGINT UNSIGNED NOT NULL,
state VARCHAR(24) NOT NULL,
attempts INT UNSIGNED NOT NULL DEFAULT 0,
created_at BIGINT UNSIGNED NOT NULL,
resolved_at BIGINT UNSIGNED NULL,
revision BIGINT UNSIGNED NOT NULL DEFAULT 0,
UNIQUE KEY uq_report (report_id)
) ENGINE=InnoDB");
$db->exec('DELETE FROM ba_identity_wizard_recovery_ci');
function checkDb(bool $yes,string $label):void{
    if (!$yes)throw new RuntimeException('FAIL '.$label);
    echo 'PASS '.$label.PHP_EOL;
}
$bridge=new BadAround_Identity_Recovery_Store_Bridge(
    new BadAround_Identity_Mysql_Ownership_Recovery_Store($db,'ba_identity_wizard_recovery_ci')
);
$intent=BadAround_Identity_Ownership_Recovery_Policy::new_intent(
    'identity_12345678',
    ['report_id'=>171,'event_id'=>371,'duplicate'=>false],
    str_repeat('a',32),1000
);
checkDb($bridge->create($intent),'durable insert');
checkDb(!$bridge->create($intent),'same operation cannot be inserted twice');
$another=array_replace($intent,['operation_id'=>str_repeat('b',32)]);
checkDb(!$bridge->create($another),'second operation cannot claim same report');
$loaded=$bridge->load($intent['operation_id']);
checkDb($loaded!==null && $loaded['state']==='pending' && $loaded['revision']===0,
    'verified journal read');
checkDb($bridge->transition($intent['operation_id'],0,'granted',1001),
    'CAS confirms existing grant');
checkDb(!$bridge->transition($intent['operation_id'],0,'retry',1002),
    'stale CAS cannot overwrite bound ownership');
$bound=$bridge->load($intent['operation_id']);
checkDb($bound['state']==='bound' && $bound['revision']===1,
    'durable state remains bound');
echo 'ALL_DURABLE_OWNERSHIP_MYSQL_TESTS_PASS'.PHP_EOL;
