<?php
/** BA-REPORT-IDENTITY-04 disposable SQL test. */
declare(strict_types=1);
require __DIR__.'/report-otp-outbox-schema.php';
require __DIR__.'/report-otp-outbox-coordinator.php';
$dsn=(string)getenv('BA_TEST_DB_DSN');
if(!str_contains($dsn,'dbname=ba_identity_ci'))throw new RuntimeException('CI only');
$db=new PDO($dsn,getenv('BA_TEST_DB_USER')?:'ba_ci',getenv('BA_TEST_DB_PASS')?:'');
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$prefix='ba_reportoutbox_ci_';
$db->exec(BadAround_Report_Otp_Outbox_Schema::statement($prefix));
$table=$prefix.'ba_report_otp_outbox';
$db->exec("DELETE FROM `{$table}`");
function test_outbox(bool $ok,string $name):void{
 if(!$ok)throw new RuntimeException('FAIL '.$name);
 echo 'PASS '.$name.PHP_EOL;
}
$store=new BadAround_Report_Otp_Outbox_Store($db,$table);
$service=new BadAround_Report_Otp_Outbox_Coordinator($store);
$op=str_repeat('a',32);$challenge=str_repeat('b',32);
$uuid='12345678-1234-4234-8234-123456789abc';$lookup=str_repeat('c',64);
$now=1762308000;
test_outbox($service->begin_verified($op,$challenge,$uuid,$lookup,$now),'verified insert');
test_outbox(!$service->begin_verified(str_repeat('d',32),$challenge,$uuid,$lookup,$now),'duplicate rejected');
test_outbox(!$service->reserve($op,$uuid,str_repeat('e',64),$now+1),'wrong email rejected');
test_outbox($service->reserve($op,$uuid,$lookup,$now+2),'reserve');
test_outbox(!$service->reserve($op,$uuid,$lookup,$now+3),'replay denied');
$row=$store->read($op);
test_outbox($row['version']===1 && $row['state']==='reserved','durable reservation');
$receipt=['submission_id'=>$uuid,'report_id'=>777,'event_id'=>778,'duplicate'=>false];
$next=BadAround_Report_Otp_Reservation_Policy::link($row,$receipt,$now+4);
test_outbox(!$store->transition($op,0,$next),'stale CAS denied');
test_outbox($service->link($op,$receipt,$now+5),'canonical link');
test_outbox($service->confirmed($op),'linked receipt confirmed');
test_outbox(!$service->link($op,$receipt,$now+6),'no duplicate link');
$op2=str_repeat('f',32);$uuid2='87654321-4321-4234-8234-123456789abc';
test_outbox($service->begin_verified($op2,str_repeat('1',32),$uuid2,$lookup,$now),'second intent');
test_outbox($service->reserve($op2,$uuid2,$lookup,$now+2),'second reserve');
test_outbox($service->quarantine($op2,$now+3),'quarantine');
test_outbox(!$service->confirmed($op2),'quarantine not success');
echo "ALL_REPORT_OTP_OUTBOX_MYSQL_TESTS_PASS\n";
