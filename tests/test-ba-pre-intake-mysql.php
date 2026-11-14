<?php
/** BA-IDENTITY-WIZARD-07 isolated MySQL test; refuses all non-CI databases. */
declare(strict_types=1);
require_once __DIR__.'/../wordpress/plugins/badaround-core/identity-dormant/mysql-pre-intake-store.php';

$dsn=(string)getenv('BA_TEST_DB_DSN');
if (!str_contains($dsn,'dbname=ba_identity_ci'))
    throw new RuntimeException('Safety stop: CI database only');
$db=new PDO($dsn,(string)getenv('BA_TEST_DB_USER'),(string)getenv('BA_TEST_DB_PASS'));
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec("CREATE TABLE IF NOT EXISTS ba_identity_pre_intake_ci (
    operation_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
    submission_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
    identity_id VARCHAR(128) NOT NULL,
    state VARCHAR(20) NOT NULL,
    report_id BIGINT UNSIGNED NULL,
    event_id BIGINT UNSIGNED NULL,
    created_at BIGINT UNSIGNED NOT NULL,
    linked_at BIGINT UNSIGNED NULL,
    revision BIGINT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB");
$db->exec('DELETE FROM ba_identity_pre_intake_ci');
function checkPre(bool $ok,string $name):void {
    if(!$ok)throw new RuntimeException('FAIL '.$name);
    echo 'PASS '.$name.PHP_EOL;
}
$store=new BadAround_Identity_Mysql_Pre_Intake_Store($db,'ba_identity_pre_intake_ci');
$service=new BadAround_Identity_Pre_Intake_Reservation($store);
$submission='123e4567-e89b-42d3-a456-426614174000';
$op=str_repeat('a',32);
$identity='identity_12345678';
checkPre($service->start($op,$submission,$identity,1000)==='reserved',
    'verified creator reserved before any canonical intake');
checkPre($service->start(str_repeat('b',32),$submission,'identity_other_999',1001)==='conflict',
    'another identity cannot claim the submission UUID');
$row=$store->find_by_submission($submission);
checkPre($row!==null && $row['identity_id']===$identity && $row['state']==='reserved',
    'creator identity preserved in durable reservation');
checkPre($service->complete($op,$submission,['submission_id'=>$submission,
    'report_id'=>71,'event_id'=>81,'duplicate'=>false],1002)==='linked',
    'trusted canonical receipt links pending reservation');
$row=$store->find_by_submission($submission);
checkPre($row['report_id']===71 && $row['event_id']===81 && $row['revision']===1,
    'canonical identifiers and revision persisted');
checkPre($service->complete($op,$submission,['submission_id'=>$submission,
    'report_id'=>71,'event_id'=>81,'duplicate'=>false],1003)==='conflict',
    'replay cannot re-link completed reservation');
checkPre($service->complete(str_repeat('b',32),$submission,['submission_id'=>$submission,
    'report_id'=>71,'event_id'=>81,'duplicate'=>false],1004)==='conflict',
    'wrong operation cannot link reservation');
checkPre($service->complete($op,$submission,['submission_id'=>$submission,
    'report_id'=>72,'event_id'=>82,'duplicate'=>true],1005)==='unavailable',
    'duplicate receipt never acquires creator');
checkPre($service->start($op,'wrong',$identity,1006)==='unavailable',
    'malformed submission denied');
echo 'ALL_MYSQL_PRE_INTAKE_RESERVATION_TESTS_PASS'.PHP_EOL;
