<?php
/** BA-REPORT-IDENTITY-02: disposable MySQL/MariaDB transaction regression. */
declare(strict_types=1);
require __DIR__.'/report-otp-schema-candidate.php';
require __DIR__.'/report-otp-store.php';
$dsn=(string)getenv('BA_TEST_DB_DSN');
if (!preg_match('/(?:^|;)dbname=ba_identity_ci(?:;|$)/',$dsn)) {
    throw new RuntimeException('Disposable CI database required');
}
$db=new PDO($dsn,getenv('BA_TEST_DB_USER')?:'ba_ci',getenv('BA_TEST_DB_PASS')?:'');
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$prefix='ba_reportotp_ci_';
$db->exec(BadAround_Report_Otp_Schema::statement($prefix));
$table=$prefix.'ba_report_otp';
$db->exec("DELETE FROM `{$table}`");
function check_report(bool $ok,string $label):void{
    if (!$ok)throw new RuntimeException('FAIL '.$label);
    echo 'PASS '.$label.PHP_EOL;
}
$store=new BadAround_Report_Otp_Store($db,$table,str_repeat('K',64));
$now=1762308000;
$submission='12345678-1234-4234-8234-123456789abc';
$email='Reporter@Example.test';
$lookup=$store->lookup($email);
$issued=$store->issue($submission,$email,$now);
$id=$issued['challenge_id'];$otp=$issued['delivery_code'];
check_report(preg_match('/^[0-9]{8}$/',$otp)===1 && preg_match('/^[a-f0-9]{32}$/',$id)===1,
    'internal OTP challenge issued without account');
$row=$db->query("SELECT * FROM `{$table}` LIMIT 1")->fetch(PDO::FETCH_ASSOC);
check_report(!str_contains(json_encode($row),$otp)
    && !str_contains(json_encode($row),$email),
    'no plaintext OTP/email stored');
check_report(!$store->verify($id,'000000000',$now+10),'malformed OTP denied');
check_report(!$store->verify($id,'abcdefgh',$now+11),'nonnumeric OTP denied');
check_report($store->verify($id,$otp,$now+12),'valid OTP verified once');
check_report(!$store->verify($id,$otp,$now+13),'OTP cannot verify twice');
check_report(!$store->claim_in_transaction($id,$submission,$lookup,$now+14),
    'claim requires existing canonical persistence transaction');
$db->beginTransaction();
check_report($store->claim_in_transaction($id,
    '87654321-4321-4234-8234-123456789abc',$lookup,$now+14)===null,
    'wrong submission denied');
check_report($store->claim_in_transaction($id,$submission,str_repeat('b',64),$now+14)===null,
    'other email denied');
$receipt=$store->claim_in_transaction($id,$submission,$lookup,$now+14);
check_report(is_array($receipt) && BadAround_Report_Verification_Policy::verified_receipt(
    $receipt,$lookup,$submission,$now+14),'trusted atomically claimed receipt valid');
$db->rollBack();
$db->beginTransaction();
$repeat=$store->claim_in_transaction($id,$submission,$lookup,$now+15);
check_report(is_array($repeat),'rollback restores proof for atomic canonical retry');
$db->commit();
$db->beginTransaction();
check_report($store->claim_in_transaction($id,$submission,$lookup,$now+16)===null,
    'committed proof cannot be replayed');
$db->rollBack();
$otherSubmission='98765432-5678-4567-8567-987654321abc';
$next=$store->issue($otherSubmission,'other@example.test',$now+20);
check_report(!$store->verify($next['challenge_id'],$next['delivery_code'],$now+621),
    'expired OTP denied');
$another=$store->issue('11111111-2222-4333-8444-555555555555','third@example.test',$now+30);
$store->invalidate_undelivered($another['challenge_id'],$now+31);
check_report(!$store->verify($another['challenge_id'],$another['delivery_code'],$now+32),
    'mail delivery failure invalidates challenge');
check_report(!BadAround_Report_Verification_Policy::can_claim_existing_report_by_email_only(),
    'email matching never grants historical ownership');
echo "ALL_REPORT_OTP_MYSQL_TESTS_PASS\n";
