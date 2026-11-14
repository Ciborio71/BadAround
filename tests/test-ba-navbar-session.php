<?php
declare(strict_types=1);
require __DIR__.'/../wordpress/plugins/badaround-core/identity-dormant/wordpress-session-rest.php';
define('BADAROUND_IDENTITY_LOGIN_ENABLED',true);
$GLOBALS['https']=true;
function is_ssl(){return $GLOBALS['https'];}
function check_nav(bool $ok,string $name):void {if(!$ok)throw new RuntimeException($name);echo "PASS $name\n";}
$store=new BadAround_Identity_Session_Memory_Store();
$sessions=new BadAround_Identity_Sessions($store,str_repeat('s',32));
$status=new BadAround_Identity_Active_Memory_Status();
$api=new BadAround_Identity_WordPress_Session_REST($sessions,$status,new BadAround_Identity_WordPress_Session_Cookie(new BadAround_Identity_Native_Cookie_Writer()),str_repeat('c',32),'https://badaround.it');
check_nav(!$api->authenticated(),'missing cookie anonymous');
$_COOKIE[$api::COOKIE]='fake';check_nav(!$api->authenticated(),'malformed cookie anonymous');
$secret=$sessions->issue('identity_test',time());$_COOKIE[$api::COOKIE]=$secret;
check_nav(!$api->authenticated(),'unverified identity denied');
$status->active['identity_test']=true;check_nav($api->authenticated(),'active verified session accepted');
$GLOBALS['https']=false;check_nav(!$api->authenticated(),'plaintext denied');$GLOBALS['https']=true;
$status->active['identity_test']=false;check_nav(!$api->authenticated(),'disabled identity denied');$status->active['identity_test']=true;
$sessions->revoke($secret,time());check_nav(!$api->authenticated(),'revoked session denied');
$_COOKIE[$api::COOKIE]=$sessions->issue('identity_test',time()-BadAround_Identity_Sessions::IDLE_TIMEOUT-1);
check_nav(!$api->authenticated(),'idle session denied');
$_COOKIE[$api::COOKIE]=$sessions->issue('identity_test',time()-BadAround_Identity_Sessions::LIFETIME-1);
check_nav(!$api->authenticated(),'expired session denied');
echo "NAVBAR_SESSION_TESTS_PASS\n";
