<?php
/** BA-REPORT-IDENTITY-03: integration on disposable MySQL/MariaDB only. */
declare(strict_types=1);
require __DIR__.'/report-otp-schema-candidate.php';
require __DIR__.'/report-otp-atomic-intake.php';
$dsn=(string)getenv('BA_TEST_DB_DSN');
if(!preg_match('/(?:^|;)dbname=ba_identity_ci(?:;|$)/',$dsn)) {
    throw new RuntimeException('Disposable CI DB required');
}
$db=new PDO($dsn,getenv('BA_TEST_DB_USER')?:'ba_ci',getenv('BA_TEST_DB_PASS')?:'');
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$prefix='ba_reportatomic_ci_';
$db->exec(BadAround_Report_Otp_Schema::statement($prefix));
$otpTable=$prefix.'ba_report_otp';
$canon=$prefix.'reports';
$db->exec("CREATE TABLE IF NOT EXISTS `{$canon}` (
 report_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 submission_id CHAR(36) NOT NULL UNIQUE,
 event_id BIGINT UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$db->exec("DELETE FROM `{$otpTable}`");
$db->exec("DELETE FROM `{$canon}`");
function pass_atomic(bool $ok,string $label):void {
 if(!$ok)throw new RuntimeException('FAIL '.$label);
 echo 'PASS '.$label.PHP_EOL;
}
final class DummyCanonical implements BadAround_Report_Canonical_Transaction_Writer {
 public bool $fail=false;
 public function __construct(private string $table){}
 public function insert_new_canonical_report(PDO $db,string $submissionId,int $now):array {
  if(!$db->inTransaction())throw new RuntimeException('No canonical transaction');
  if($this->fail)throw new RuntimeException('Simulated canonical failure');
  $q=$db->prepare("INSERT INTO `{$this->table}` (submission_id,event_id)
      VALUES (:submission,:event)");
  $q->execute([':submission'=>$submissionId,':event'=>246]);
  return [
   'submission_id'=>$submissionId,
   'report_id'=>(int)$db->lastInsertId(),
   'event_id'=>246,'duplicate'=>false
  ];
 }
}
$key=str_repeat('S',64);
$store=new BadAround_Report_Otp_Store($db,$otpTable,$key);
$writer=new DummyCanonical($canon);
$coordinator=new BadAround_Report_Otp_Atomic_Intake($db,$store,$writer);
$submission='12345678-1234-4234-8234-123456789abc';
$email='test@example.test';
$issued=$store->issue($submission,$email,1762308000);
$id=$issued['challenge_id'];
$lookup=$store->lookup($email);
pass_atomic(!$coordinator->submit($id,$submission,$lookup,1762308005),
    'unverified proof blocks canonical insert');
pass_atomic($store->verify($id,$issued['delivery_code'],1762308010),
    'verify one-use OTP before submission');
$writer->fail=true;
pass_atomic($coordinator->submit($id,$submission,$lookup,1762308015)===null,
    'canonical insert failure returns no success');
pass_atomic((int)$db->query("SELECT COUNT(*) FROM `{$canon}`")->fetchColumn()===0,
    'canonical write rolled back on failure');
$writer->fail=false;
$receipt=$coordinator->submit($id,$submission,$lookup,1762308016);
pass_atomic(($receipt['status']??null)==='received_for_moderation'
    && !isset($receipt['report_id']) && !isset($receipt['email_lookup']),
    'atomic commit returns safe moderation receipt');
pass_atomic((int)$db->query("SELECT COUNT(*) FROM `{$canon}`")->fetchColumn()===1,
    'exactly one canonical report persisted');
pass_atomic($coordinator->submit($id,$submission,$lookup,1762308017)===null,
    'claimed OTP never causes duplicate report');
pass_atomic((int)$db->query("SELECT COUNT(*) FROM `{$canon}`")->fetchColumn()===1,
    'replay leaves canonical repository untouched');
$issued2=$store->issue('87654321-4321-4234-8234-123456789abc',$email,1762308000);
pass_atomic($store->verify($issued2['challenge_id'],$issued2['delivery_code'],1762308010),
    'second independent challenge verified');
pass_atomic($coordinator->submit($issued2['challenge_id'],
    '87654321-4321-4234-8234-123456789abc',str_repeat('b',64),1762308015)===null,
    'wrong email-bound lookup denied');
pass_atomic((int)$db->query("SELECT COUNT(*) FROM `{$canon}`")->fetchColumn()===1,
    'wrong identity creates no new canonical report');
echo "ALL_REPORT_OTP_ATOMIC_INTAKE_MYSQL_TESTS_PASS\n";
