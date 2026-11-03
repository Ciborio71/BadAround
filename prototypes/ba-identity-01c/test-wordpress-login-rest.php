<?php
/** CI-only HTTP OTP login controller test. No live HTTP or email. */
declare(strict_types=1);
define('BADAROUND_IDENTITY_LOGIN_ENABLED',true);
require __DIR__.'/wordpress-login-rest.php';
require __DIR__.'/purpose-keys.php';
$GLOBALS['ba_ssl']=true;
$GLOBALS['ba_routes']=[];
function is_ssl(){return $GLOBALS['ba_ssl'];}
function add_action($hook,$callback){$GLOBALS['ba_hook']=$hook;}
function register_rest_route($namespace,$path,$args){$GLOBALS['ba_routes'][$path]=$args;}
function rest_ensure_response($data){return $data;}
final class LoginRequestStub {
    public function __construct(private array $headers,private array $params){}
    public function get_header($name){return $this->headers[$name]??null;}
    public function get_body(){return json_encode($this->params,JSON_THROW_ON_ERROR);}
    public function get_json_params(){return $this->params;}
}
final class LoginQuotaStub implements BadAround_Identity_Issue_Quota {
    public function allow(string $purpose,string $lookup,string $ip,int $now):bool{
        return $purpose==='login';
    }
}
final class LoginChallengeWriterStub implements BadAround_Identity_Challenge_Writer {
    public function __construct(private BadAround_Identity_Challenge_Memory_Store $store){}
    public function insert(array $record):void{$this->store->insert($record);}
    public function invalidate(string $id,int $now):void{}
}
final class LoginTransportStub implements BadAround_Identity_Otp_Transport {
    public array $sent=[];
    public function send_code(string $email,string $code,string $purpose):bool {
        $this->sent[]=[$email,$code,$purpose];
        return true;
    }
}
final class LoginCookieStub implements BadAround_Identity_Cookie_Writer {
    public array $cookies=[];
    public bool $allowed=true;
    public function write(string $name,string $value,array $options):bool {
        if (!$this->allowed) return false;
        $this->cookies[]=[$name,$value,$options];return true;
    }
}
function check_login(bool $ok,string $label):void{
    if (!$ok) throw new RuntimeException('FAIL '.$label);
    echo 'PASS '.$label.PHP_EOL;
}
$root=str_repeat('A',48);
$csrfKey=str_repeat('B',32);
$challenges=new BadAround_Identity_Challenge_Memory_Store();
$transport=new LoginTransportStub();
$issuer=new BadAround_Identity_Otp_Issuer(
    $root,new LoginQuotaStub(),new LoginChallengeWriterStub($challenges),$transport
);
$sessions=new BadAround_Identity_Sessions(
    new BadAround_Identity_Session_Memory_Store(),str_repeat('C',32)
);
$resolver=new BadAround_Identity_Verified_Memory_Resolver();
$email='ana@example.com';
$lookup=BadAround_Identity_Challenge_Primitives::digest('email','lookup',$email,$root);
$resolver->verified[$lookup]='identity_12345678';
$verifier=new BadAround_Identity_Verification_Coordinator(
    $challenges,new BadAround_Identity_Challenge_Verifier(
        $challenges,BadAround_Identity_Purpose_Keys::for_purpose('login',$root)),
    $resolver,$sessions
);
$writer=new LoginCookieStub();
$controller=new BadAround_Identity_WordPress_Login_REST(
    $issuer,$verifier,$sessions,new BadAround_Identity_WordPress_Session_Cookie($writer),
    $csrfKey,'https://badaround.it'
);
check_login($controller->register_hooks() && $GLOBALS['ba_hook']==='rest_api_init',
    'explicit feature flag registers route initialization');
$controller->routes();
check_login(count($GLOBALS['ba_routes'])===2,'only login start/verify routes registered');
$headers=['origin'=>'https://badaround.it','content-type'=>'application/json',
    'sec-fetch-site'=>'same-origin'];
$_SERVER['REMOTE_ADDR']='127.0.0.1';
$started=$controller->start(new LoginRequestStub($headers,['email'=>$email]));
check_login(($started['ok']??false) && preg_match('/^[a-f0-9]{32}$/D',$started['challenge_id'])===1
    && count($transport->sent)===1,'OTP start delivers one code and opaque challenge');
$secretCode=$transport->sent[0][1];$cid=$started['challenge_id'];
$wrong=$controller->verify(new LoginRequestStub($headers,
    ['challenge_id'=>$cid,'code'=>'0000000x']));
check_login(($wrong['status']??null)===403 && count($writer->cookies)===0,
    'invalid OTP does not set cookie');
$success=$controller->verify(new LoginRequestStub($headers,
    ['challenge_id'=>$cid,'code'=>$secretCode]));
check_login(($success['ok']??false) && isset($success['csrf'])
    && count($writer->cookies)===1,'OTP sets secure cookie and returns CSRF, not bearer');
check_login(!isset($success['session']) && !isset($success['token'])
    && $writer->cookies[0][0]==='__Host-ba_identity',
    'response does not expose bearer and uses host-only cookie');
$repeat=$controller->verify(new LoginRequestStub($headers,
    ['challenge_id'=>$cid,'code'=>$secretCode]));
check_login(($repeat['status']??null)===403,
    'OTP replay denied');
$GLOBALS['ba_ssl']=false;
$denied=$controller->start(new LoginRequestStub($headers,['email'=>$email]));
check_login(($denied['status']??null)===403,
    'non-HTTPS transport rejected');
$GLOBALS['ba_ssl']=true;
$cross=$headers;$cross['origin']='https://evil.example';
$denied=$controller->start(new LoginRequestStub($cross,['email'=>$email]));
check_login(($denied['status']??null)===403,
    'cross-site request rejected');
$cookieFail=$controller->start(new LoginRequestStub($headers,['email'=>$email]));
$writer->allowed=false;
$denied=$controller->verify(new LoginRequestStub($headers,
    ['challenge_id'=>$cookieFail['challenge_id'],'code'=>$transport->sent[1][1]]));
check_login(($denied['status']??null)===503 && count($writer->cookies)===1,
    'cookie failure reports unavailable and does not emit a bearer');
echo "ALL_WORDPRESS_LOGIN_REST_TESTS_PASS\n";
