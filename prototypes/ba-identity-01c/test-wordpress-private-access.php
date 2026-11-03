<?php
/** BA-IDENTITY-02A network fence: only server-observed, approved QA IP. */
declare(strict_types=1);
require __DIR__.'/wordpress-private-access.php';
function fence_check(bool $ok,string $name):void{
    if (!$ok) {throw new RuntimeException('FAIL '.$name);}
    echo 'PASS '.$name.PHP_EOL;
}
$_SERVER['REMOTE_ADDR']='127.0.0.1';
fence_check(!BadAround_Identity_Private_Access::authorized(),
    'disabled QA never authorizes, even with valid IP');
define('BADAROUND_IDENTITY_PRIVATE_QA_ENABLED',true);
fence_check(!BadAround_Identity_Private_Access::authorized(),
    'QA flag alone never authorizes unlisted IP');
define('BADAROUND_IDENTITY_QA_IP_ALLOWLIST',['127.0.0.1','::1']);
fence_check(BadAround_Identity_Private_Access::authorized(),
    'explicit server peer allowlist authorizes QA request');
$_SERVER['REMOTE_ADDR']='203.0.113.10';
fence_check(!BadAround_Identity_Private_Access::authorized(),
    'unlisted visitor never gets private endpoints');
$_SERVER['HTTP_X_FORWARDED_FOR']='127.0.0.1';
fence_check(!BadAround_Identity_Private_Access::authorized(),
    'spoofed forwarding header cannot bypass peer check');
$_SERVER['REMOTE_ADDR']='::1';
fence_check(BadAround_Identity_Private_Access::authorized(),
    'explicit IPv6 address is allowed');
unset($_SERVER['REMOTE_ADDR']);
fence_check(!BadAround_Identity_Private_Access::authorized(),
    'unknown peer denied');
echo "ALL_PRIVATE_QA_NETWORK_FENCE_TESTS_PASS\n";
