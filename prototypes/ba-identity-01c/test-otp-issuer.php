<?php
declare(strict_types=1);
require __DIR__ . '/otp-issuer.php';
final class QuotaFixture implements BadAround_Identity_Issue_Quota {
    public bool $allowRequest = true;
    public int $calls = 0;
    public function allow(string $purpose,string $lookup,string $ip,int $now): bool {
        ++$this->calls;
        return $this->allowRequest;
    }
}
final class ChallengeFixture implements BadAround_Identity_Challenge_Writer {
    public array $records=[];
    public array $invalidated=[];
    public function insert(array $record): void {
        $this->records[$record['challenge_id']]=$record;
    }
    public function invalidate(string $challengeId, int $now): void {
        $this->invalidated[]=$challengeId;
        if (!isset($this->records[$challengeId])) { throw new RuntimeException('Missing challenge'); }
        $this->records[$challengeId]['consumed_at']=$now;
    }
}
final class MailFixture implements BadAround_Identity_Otp_Transport {
    public array $messages=[];
    public bool $succeed=true;
    public function send_code(string $email,string $code,string $purpose): bool {
        $this->messages[]=['email'=>$email,'code'=>$code,'purpose'=>$purpose];
        return $this->succeed;
    }
}
function assert_issuer(bool $ok,string $name): void {
    if (!$ok) { throw new RuntimeException('FAIL: '.$name); }
    echo "PASS: ".$name."\n";
}
$key=str_repeat('I',32);
$q=new QuotaFixture();
$w=new ChallengeFixture();
$m=new MailFixture();
$issuer=new BadAround_Identity_Otp_Issuer($key,$q,$w,$m);
$now=1762156800;
$q->allowRequest=false;
assert_issuer($issuer->issue('anna@example.com','enrollment','127.0.0.1',$now)===null,'quota denial generic failure');
assert_issuer(count($w->records)===0 && count($m->messages)===0,'no OTP stored or sent when blocked');
$q->allowRequest=true;
$id=$issuer->issue('ANNA@EXAMPLE.COM','enrollment','127.0.0.1',$now);
assert_issuer(is_string($id) && preg_match('/^[a-f0-9]{32}$/D',$id)===1,'opaque challenge issued');
assert_issuer($m->messages[0]['email']==='anna@example.com','normalized recipient');
assert_issuer(!array_key_exists('delivery_code',$w->records[$id]),'plaintext OTP never stored');
$enrollKey=BadAround_Identity_Purpose_Keys::for_purpose('enrollment',$key);
$loginKey=BadAround_Identity_Purpose_Keys::for_purpose('login',$key);
assert_issuer(BadAround_Identity_Challenge_Primitives::verify(
    $w->records[$id],$m->messages[0]['code'],$enrollKey,$now+1
)['ok'],'issued OTP validates only in enrollment context');
assert_issuer(!BadAround_Identity_Challenge_Primitives::verify(
    $w->records[$id],$m->messages[0]['code'],$loginKey,$now+1
)['ok'],'cross-purpose OTP denied');
assert_issuer($issuer->issue('anna@example.com','enrollment','not-an-ip',$now)===null,'untrusted invalid IP denied');
$m->succeed=false;
assert_issuer($issuer->issue('anna@example.com','login','127.0.0.1',$now)===null,'failed transport returns generic failure');
assert_issuer(count($m->messages)===2,'transport called only after quota');
assert_issuer(count($w->invalidated)===1,'failed transport invalidates persisted challenge');
$failedId=$w->invalidated[0];
assert_issuer(!BadAround_Identity_Challenge_Primitives::verify(
    $w->records[$failedId],$m->messages[1]['code'],$loginKey,$now+1
)['ok'],'undelivered OTP cannot be consumed');
echo "ALL_OTP_ISSUER_TESTS_PASS\n";
