<?php
/** BA-REPORT-IDENTITY-02 isolated memory-only contract tests. */
declare(strict_types=1);
require __DIR__.'/report-otp-service.php';
function verify_test(bool $ok,string $label):void {
    if(!$ok)throw new RuntimeException('FAIL '.$label);
    echo 'PASS '.$label.PHP_EOL;
}
class ReportOtpMemoryStore implements BadAround_Report_Otp_Proof_Store {
    public array $rows=[];
    public function stage(array $record):bool {
        if(isset($this->rows[$record['challenge_id']]))return false;
        $this->rows[$record['challenge_id']]=$record;return true;
    }
    public function invalidate(string $id,int $now):void {
        if(isset($this->rows[$id]))$this->rows[$id]['claimed_at']=$now;
    }
    public function verify_once(string $id,string $digest,int $now):bool {
        $row=$this->rows[$id]??null;
        if(!$row || $row['purpose']!=='report_submission'
            || $row['claimed_at']!==null || $row['verified_at']!==null
            || $now >= $row['expires_at']||$row['attempts']>=5)return false;
        $this->rows[$id]['attempts']++;
        if(!hash_equals($row['code_digest'],$digest))return false;
        $this->rows[$id]['verified_at']=$now;
        return true;
    }
    public function claim_once(string $id,string $lookup,string $submissionId,int $now):bool {
        $row=$this->rows[$id]??null;
        if(!$row || $row['purpose']!=='report_submission'
            || $row['verified_at']===null || $row['claimed_at']!==null
            || $now >= $row['expires_at']
            || !hash_equals($row['email_lookup'],$lookup)
            || !hash_equals($row['submission_id'],$submissionId))return false;
        $this->rows[$id]['claimed_at']=$now;return true;
    }
}
class ReportOtpMemoryTransport implements BadAround_Report_Otp_Transport {
    public bool $ok=true;
    public string $lastCode='';
    public function send_report_code(string $email,string $code):bool {
        $this->lastCode=$code;
        return $this->ok;
    }
}
class ReportOtpMemoryQuota implements BadAround_Report_Otp_Quota {
    public bool $ok=true;
    public function consume(string $lookup,string $trustedIp,int $now):bool{
        return $this->ok;
    }
}
$store=new ReportOtpMemoryStore();
$mail=new ReportOtpMemoryTransport();
$quota=new ReportOtpMemoryQuota();
$service=new BadAround_Report_Otp_Service($store,$mail,$quota,str_repeat('R',64));
$id='12345678-1234-4234-8234-123456789abc';
$now=1762272000;
$challenge=$service->request('Giulia','Rossi','Giulia@Example.test',$id,'127.0.0.1',$now);
verify_test(is_string($challenge)&&preg_match('/^[a-f0-9]{32}$/D',$challenge)===1,
    'account-free report challenge created');
verify_test(!array_key_exists('delivery_code',$store->rows[$challenge])
    && !array_key_exists('email',$store->rows[$challenge]),
    'plaintext email and OTP never stored in proof');
verify_test(!$service->claim_for_canonical_intake($challenge,'giulia@example.test',$id,$now+1),
    'unverified proof cannot submit');
verify_test(!$service->confirm($challenge,'00000000',$now+1),'wrong code rejected');
verify_test($service->confirm($challenge,$mail->lastCode,$now+2),'correct report code accepted');
verify_test(!$service->confirm($challenge,$mail->lastCode,$now+3),'OTP replay rejected');
verify_test(!$service->claim_for_canonical_intake(
    $challenge,'other@example.test',$id,$now+3),'wrong email rejected');
verify_test(!$service->claim_for_canonical_intake(
    $challenge,'giulia@example.test','aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',$now+3),
    'wrong submission rejected');
verify_test($service->claim_for_canonical_intake(
    $challenge,'giulia@example.test',$id,$now+4),
    'exact trusted report claim succeeds');
verify_test(!$service->claim_for_canonical_intake(
    $challenge,'giulia@example.test',$id,$now+5),'duplicate claim rejected');
$late=$service->request('Giulia','Rossi','giulia@example.test',$id,'127.0.0.1',$now);
verify_test(is_string($late),'fresh challenge created');
verify_test(!$service->confirm($late,$mail->lastCode,$now+600),'expired code refused');
$mail->ok=false;
$failed=$service->request('Giulia','Rossi','giulia@example.test',$id,'127.0.0.1',$now);
verify_test($failed===null,'email delivery failure returns no usable challenge');
foreach($store->rows as $key=>$record){
  if($key!==$challenge && $key!==$late) {
    verify_test(!$service->confirm($key,$mail->lastCode,$now+1),'undelivered code invalidated');
  }
}
$quota->ok=false;
verify_test($service->request('Giulia','Rossi','giulia@example.test',$id,'127.0.0.1',$now)===null,
    'quota failure rejects issuance');
echo "ALL_REPORT_OTP_SERVICE_POLICY_TESTS_PASS\n";
