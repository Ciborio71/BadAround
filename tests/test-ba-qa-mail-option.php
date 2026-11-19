<?php
declare(strict_types=1);
require_once __DIR__.'/../wordpress/plugins/badaround-core/identity-dormant/wordpress-qa-mail-fence.php';
$emails=null;
function get_option($name,$default=null){return $GLOBALS['emails'];}
function option_mail_check(bool $ok,string $label):void {
    if(!$ok)throw new RuntimeException('FAIL '.$label);
    echo 'PASS '.$label.PHP_EOL;
}
$provider=new class {public int $count=0;public function send(...$args):bool{++$this->count;return true;}};
option_mail_check(!BadAround_Identity_QA_Mail_Fence::configured(),'missing option denies mail');
$emails=['tester@example.org'];$fence=new BadAround_Identity_QA_Mail_Fence($provider);
option_mail_check($fence->send('tester@example.org','Test','Synthetic'),'explicit server option permits designated inbox');
option_mail_check(!$fence->send('other@example.org','Test','Synthetic'),'unlisted inbox denied');
$emails=['other@example.org'];
option_mail_check(!$fence->send('other@example.org','Test','Synthetic'),'configuration expansion cannot change existing fence recipients');
option_mail_check(!$fence->send('tester@example.org','Test','Synthetic'),'configuration revocation takes effect immediately');
foreach([[],['invalid'],['Tester@example.org'],['tester@example.org','tester@example.org'],['a'=>'tester@example.org']] as $bad) {
    $emails=$bad;option_mail_check(!BadAround_Identity_QA_Mail_Fence::configured(),'malformed option fails closed');
}
$emails=['tester@example.org'];
define('BADAROUND_IDENTITY_QA_EMAIL_ALLOWLIST',[]);
option_mail_check(!BadAround_Identity_QA_Mail_Fence::configured(),'invalid explicit constant cannot fall back to a permissive option');
option_mail_check($provider->count===1,'all denied paths emit no mail');
echo "ALL_QA_MAIL_OPTION_TESTS_PASS\n";
