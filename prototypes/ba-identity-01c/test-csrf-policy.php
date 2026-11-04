<?php
declare(strict_types=1);
require __DIR__ . '/csrf-policy.php';
function check_csrf(bool $result, string $name): void {
    if (!$result) { throw new RuntimeException('FAIL '.$name); }
    echo "PASS $name\n";
}
$key = str_repeat('K', 32);
$session = bin2hex(random_bytes(32));
$other = bin2hex(random_bytes(32));
$token = BadAround_Identity_Csrf_Policy::issue($session, $key);
check_csrf(BadAround_Identity_Csrf_Policy::verify($token,$session,$key),'valid token');
check_csrf(!BadAround_Identity_Csrf_Policy::verify($token,$other,$key),'cross-session rejected');
check_csrf(!BadAround_Identity_Csrf_Policy::verify('bad',$session,$key),'malformed rejected');
check_csrf(!BadAround_Identity_Csrf_Policy::verify($token,$session,str_repeat('Z',32)),'wrong key rejected');
check_csrf(BadAround_Identity_Csrf_Policy::origin_allowed('https://badaround.it','https://badaround.it'),'same origin');
check_csrf(!BadAround_Identity_Csrf_Policy::origin_allowed('https://evil.example','https://badaround.it'),'foreign origin rejected');
check_csrf(!BadAround_Identity_Csrf_Policy::origin_allowed('http://badaround.it','https://badaround.it'),'insecure origin rejected');
check_csrf(!BadAround_Identity_Csrf_Policy::origin_allowed(null,'https://badaround.it'),'missing origin rejected');
check_csrf(!BadAround_Identity_Csrf_Policy::origin_allowed('https://badaround.it/path','https://badaround.it'),'origin path rejected');
check_csrf(!BadAround_Identity_Csrf_Policy::origin_allowed('https://badaround.it?x=1','https://badaround.it'),'origin query rejected');
check_csrf(!BadAround_Identity_Csrf_Policy::origin_allowed('https://badaround.it#fragment','https://badaround.it'),'origin fragment rejected');
check_csrf(!BadAround_Identity_Csrf_Policy::origin_allowed('https://u@badaround.it','https://badaround.it'),'origin user-info rejected');
check_csrf(!BadAround_Identity_Csrf_Policy::origin_allowed('https://badaround.it:444','https://badaround.it'),'origin alternate port rejected');
echo "ALL_CSRF_TESTS_PASS\n";
