<?php
/** BA-IDENTITY-02B: isolated session and CSRF-verified logout tests. */
declare(strict_types=1);
define('BADAROUND_IDENTITY_LOGIN_ENABLED',true);
require __DIR__.'/wordpress-session-rest.php';
$GLOBALS['sid_ssl']=true;
$GLOBALS['sid_routes']=[];
function is_ssl():bool{return $GLOBALS['sid_ssl'];}
function add_action($action,$callback){$GLOBALS['sid_hook']=$action;}
function register_rest_route($ns,$path,$args){$GLOBALS['sid_routes'][$path]=$args;}
final class SidRequest {
    public function __construct(private array $headers){}
    public function get_header($name){return $this->headers[$name]??null;}
}
final class SidCookie implements BadAround_Identity_Cookie_Writer {
    public array $calls=[];
    public function write(string $name,string $value,array $options):bool{
        $this->calls[]=[$name,$value,$options];return true;
    }
}
final class SidStatus implements BadAround_Identity_Active_Status {
    public bool $enabled=true;
    public function is_active_verified(string $identityId):bool {
        return $this->enabled && $identityId==='identity_12345678';
    }
}
function sid_assert(bool $ok,string $name):void{
    if (!$ok)throw new RuntimeException('FAIL '.$name);
    echo 'PASS '.$name.PHP_EOL;
}
$sessionStore=new BadAround_Identity_Session_Memory_Store();
$sessions=new BadAround_Identity_Sessions($sessionStore,str_repeat('S',32));
$secret=$sessions->issue('identity_12345678',time());
$_COOKIE['__Host-ba_identity']=$secret;
$status=new SidStatus();
$writer=new SidCookie();
$key=str_repeat('C',32);
$controller=new BadAround_Identity_WordPress_Session_REST(
    $sessions,$status,new BadAround_Identity_WordPress_Session_Cookie($writer),
    $key,'https://badaround.it'
);
sid_assert($controller->register_hooks() && $GLOBALS['sid_hook']==='rest_api_init',
    'explicit login flag registers session routes');
$controller->routes();
sid_assert(count($GLOBALS['sid_routes'])===2,'POST session recovery and POST logout only');
$headers=['origin'=>'https://badaround.it','sec-fetch-site'=>'same-origin'];
$state=$controller->session(new SidRequest($headers));
sid_assert(($state['authenticated']??false)===true && isset($state['csrf'])
    && !isset($state['identity_id']) && !isset($state['email']),
    'same-origin session reveals CSRF but no private identity data');
$bad=$controller->logout(new SidRequest($headers+['x-badaround-csrf'=>'wrong']));
sid_assert(($bad['status']??null)===403,'logout rejects invalid CSRF');
$other=$controller->session(new SidRequest(['origin'=>'https://example.com']));
sid_assert(($other['status']??null)===403,'cross-origin access denied');
$status->enabled=false;
$inactive=$controller->session(new SidRequest($headers));
sid_assert(($inactive['authenticated']??true)===false,
    'revoked identity cannot retrieve CSRF token');
$status->enabled=true;
$headers['x-badaround-csrf']=$state['csrf'];
$ok=$controller->logout(new SidRequest($headers));
sid_assert(($ok['ok']??false)===true && $ok['authenticated']===false,
    'logout revokes session before clearing host cookie');
sid_assert(count($writer->calls)===1
    && $writer->calls[0][0]==='__Host-ba_identity'
    && $writer->calls[0][1]===''
    && $writer->calls[0][2]['expires']<time(),
    'logout expires host cookie');
$after=$controller->session(new SidRequest($headers));
sid_assert(($after['authenticated']??true)===false,
    'revoked bearer never authenticates after logout');
$GLOBALS['sid_ssl']=false;
sid_assert(($controller->session(new SidRequest($headers))['status']??null)===403,
    'HTTP cannot fetch session');
echo "ALL_WORDPRESS_SESSION_REST_TESTS_PASS\n";
