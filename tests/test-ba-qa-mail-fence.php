<?php
/** QA-only OTP mail gate tests. No external mail, DB or WordPress. */
declare(strict_types=1);
require_once __DIR__.'/../wordpress/plugins/badaround-core/identity-dormant/wordpress-qa-mail-fence.php';
final class QAMailStub {
    public int $sent=0;
    public array $destinations=[];
    public function send(string $to,string $subject,string $body):bool {
        ++$this->sent;
        $this->destinations[]=$to;
        return true;
    }
}
function qaMailAssert(bool $ok,string $label):void {
    if(!$ok)throw new RuntimeException('FAIL '.$label);
    echo 'PASS '.$label.PHP_EOL;
}
qaMailAssert(!BadAround_Identity_QA_Mail_Fence::configured(),'without allowlist QA mail is disabled');
try {
    new BadAround_Identity_QA_Mail_Fence(new QAMailStub());
    throw new RuntimeException('Unsafe QA mailer constructed');
} catch(InvalidArgumentException $e) {
    qaMailAssert(true,'missing allowlist refuses mailer assembly');
}
define('BADAROUND_IDENTITY_QA_EMAIL_ALLOWLIST',['qa-only@example.test','qa-ops@example.test']);
qaMailAssert(BadAround_Identity_QA_Mail_Fence::configured(),'two explicit inboxes accepted');
$stub=new QAMailStub();
$fence=new BadAround_Identity_QA_Mail_Fence($stub);
qaMailAssert(!$fence->send('stranger@example.test','OTP','00000000'),
    'arbitrary email does not reach mail provider');
qaMailAssert(!$fence->send('QA-ONLY@example.test','OTP','00000000'),
    'case mismatch is not silently normalized');
qaMailAssert(!$fence->send('qa-only@example.test.extra','OTP','00000000'),
    'suffix lookalike email rejected');
qaMailAssert($stub->sent===0,'all denied sends avoid provider invocation');
qaMailAssert($fence->send('qa-only@example.test','OTP','12345678'),
    'exact QA email delivered to stub');
qaMailAssert($stub->sent===1&&$stub->destinations===['qa-only@example.test'],
    'only approved recipient reaches provider');
echo 'ALL_QA_MAIL_FENCE_TESTS_PASS'.PHP_EOL;
