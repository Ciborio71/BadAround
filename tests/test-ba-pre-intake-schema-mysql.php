<?php
/** Disposable MySQL CI only. No WordPress connection and no production DDL. */
declare(strict_types=1);
require_once __DIR__.'/../wordpress/plugins/badaround-core/identity-dormant/pre-intake-schema-candidate.php';
require_once __DIR__.'/../wordpress/plugins/badaround-core/identity-dormant/mysql-pre-intake-store.php';

$dsn=(string)getenv('BA_TEST_DB_DSN');
if (!str_contains($dsn,'dbname=ba_identity_ci'))
    throw new RuntimeException('Safety stop: CI database only');
$db=new PDO($dsn,(string)getenv('BA_TEST_DB_USER'),(string)getenv('BA_TEST_DB_PASS'));
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
function schema_check(bool $ok,string $name):void {
    if (!$ok)throw new RuntimeException('FAIL '.$name);
    echo 'PASS '.$name.PHP_EOL;
}
$prefix='ci_wizard_';
$table=BadAround_Identity_Pre_Intake_Schema::table($prefix);
schema_check($table==='ci_wizard_ba_identity_pre_intake','canonical prefix generates exact table name');
try { BadAround_Identity_Pre_Intake_Schema::table('../bad');throw new RuntimeException('bad prefix accepted'); }
catch (InvalidArgumentException $e) {schema_check(true,'path traversal prefix rejected');}
$db->exec("DROP TABLE IF EXISTS `{$table}`");
try {
    $db->exec(BadAround_Identity_Pre_Intake_Schema::create_sql($prefix));
    schema_check(BadAround_Identity_Pre_Intake_Schema::inspect($db,$prefix),'created QA table verified with information_schema');
    $store=new BadAround_Identity_Mysql_Pre_Intake_Store($db,$table);
    $uuid='123e4567-e89b-42d3-a456-426614174000';
    $op=str_repeat('a',32);
    schema_check($store->reserve($op,$uuid,'identity_12345678',100),'reservation persisted');
    schema_check(!$store->reserve(str_repeat('b',32),$uuid,'identity_99999999',101),'unique submission blocks another identity');
    schema_check($store->link($op,$uuid,42,52,102),'linked report and event');
    schema_check(!$store->link($op,$uuid,42,52,103),'linked row cannot replay');
    $uuid2='123e4567-e89b-42d3-a456-426614174001';
    schema_check($store->reserve(str_repeat('c',32),$uuid2,'identity_12345678',104),'second submission reserved');
    schema_check(!$store->link(str_repeat('c',32),$uuid2,42,53,105),
        'unique report ID prevents multiple ownership reservations');
    $table2='ci_wizard_schema_drift';
    $db->exec("DROP TABLE IF EXISTS `{$table2}`");
    $db->exec("CREATE TABLE `{$table2}` (operation_id CHAR(32) PRIMARY KEY) ENGINE=InnoDB");
    schema_check(!BadAround_Identity_Pre_Intake_Schema::inspect($db,'ci_wizard_schema_drift_prefix_'),
        'missing/mismatched table rejected');
    $db->exec("DROP TABLE `{$table2}`");
} finally {
    $db->exec("DROP TABLE IF EXISTS `{$table}`");
}
echo 'ALL_PRE_INTAKE_SCHEMA_QA_MYSQL_TESTS_PASS'.PHP_EOL;
