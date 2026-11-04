<?php
/** PHP-only WordPress registration adapter test (no live routes). */
declare(strict_types=1);
define('BADAROUND_IDENTITY_REGISTRATION_ENABLED',true);
require __DIR__.'/wordpress-registration-rest.php';
$GLOBALS['registration_ssl']=true;
$GLOBALS['registration_routes']=[];
function is_ssl(){return $GLOBALS['registration_ssl'];}
function add_action($hook,$callback){$GLOBALS['registration_hook']=$hook;}
function register_rest_route($namespace,$route,$args){
    $GLOBALS['registration_routes'][$route]=[$namespace,$args];
}
function rest_ensure_response($data){return $data;}
final class RegRequest {
    public function __construct(private array $headers,private array $body) {}
    public function get_header($key){return $this->headers[$key]??null;}
    public function get_body(){return json_encode($this->body,JSON_THROW_ON_ERROR);}
    public function get_json_params(){return $this->body;}
}
final class RegActions implements BadAround_Identity_Registration_Actions {
    public array $startCalls=[];
    public array $verifyCalls=[];
    public bool $allow=true;
    public function start(string $firstName,string $lastName,string $email,
        bool $consent,string $ip,int $now):?string {
        $this->startCalls[]=[$firstName,$lastName,$email,$consent,$ip,$now];
        return $this->allow ? str_repeat('a',32):null;
    }
    public function verify(string $id,string $code,int $now):?string {
        $this->verifyCalls[]=[$id,$code];
        return $this->allow ? 'identity_12345678':null;
    }
}
function expect_reg(bool $ok,string $label):void {
    if (!$ok)throw new RuntimeException('FAIL '.$label);
    echo 'PASS '.$label.PHP_EOL;
}
$backend=new RegActions();
$api=new BadAround_Identity_WordPress_Registration_REST($backend,'https://badaround.it');
expect_reg($api->register_hooks() && $GLOBALS['registration_hook']==='rest_api_init',
    'only explicit flag registers controller');
$api->register_routes();
expect_reg(count($GLOBALS['registration_routes'])===2,
    'start and verify only, no moderation or owner editing');
$headers=['content-type'=>'application/json','origin'=>'https://badaround.it',
    'sec-fetch-site'=>'same-origin'];
$_SERVER['REMOTE_ADDR']='127.0.0.1';
$body=['first_name'=>'Anna','last_name'=>'Rossi','email'=>'anna@example.com',
    'privacy_acknowledged'=>true,'notice_version'=>'forged',
    'accepted_at'=>1];
$missing=$body;$missing['privacy_acknowledged']=false;
$result=$api->start(new RegRequest($headers,$missing));
expect_reg(($result['status']??null)===400 && $backend->startCalls===[],
    'missing privacy checkbox denies staging');
$result=$api->start(new RegRequest($headers,$body));
expect_reg($result['challenge_id']===str_repeat('a',32)
    && count($backend->startCalls)===1,'registration sends controlled server data');
expect_reg($backend->startCalls[0][0]==='Anna'
    && $backend->startCalls[0][3]===true
    && !in_array('forged',$backend->startCalls[0],true),
    'untrusted consent metadata never passed');
$backend->allow=false;
$result=$api->start(new RegRequest($headers,$body));
expect_reg(($result['ok']??false)===true
    && preg_match('/^[a-f0-9]{32}$/D',$result['challenge_id'])===1
    && $result['challenge_id']!==str_repeat('a',32),
    'failed enrollment returns opaque decoy challenge');
$backend->allow=true;
$result=$api->verify(new RegRequest($headers,[
    'challenge_id'=>str_repeat('a',32),'code'=>'12345678'
]));
expect_reg(($result['status']??null)==='verified'
    && !isset($result['identity_id']), 'verification never exposes private identity');
$bad=$api->start(new RegRequest(array_merge($headers,
    ['origin'=>'https://evil.example']),$body));
expect_reg(($bad['status']??null)===403,'foreign origin rejected');
$GLOBALS['registration_ssl']=false;
$bad=$api->start(new RegRequest($headers,$body));
expect_reg(($bad['status']??null)===403,'plain HTTP denied');
echo "ALL_WORDPRESS_REGISTRATION_REST_TESTS_PASS\n";
