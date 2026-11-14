<?php
/** Private-QA unified access. Cookie secret never leaves the secure cookie. */
declare(strict_types=1);
require_once __DIR__.'/unified-access.php';
require_once __DIR__.'/wordpress-session-cookie.php';
require_once __DIR__.'/csrf-policy.php';
final class BadAround_Identity_WordPress_Access_REST {
    public function __construct(private BadAround_Identity_Unified_Access $access,private BadAround_Identity_Sessions $sessions,
        private BadAround_Identity_WordPress_Session_Cookie $cookies,private string $origin) {}
    private function enabled():bool {
        return defined('BADAROUND_IDENTITY_REGISTRATION_ENABLED')&&BADAROUND_IDENTITY_REGISTRATION_ENABLED===true
            &&defined('BADAROUND_IDENTITY_LOGIN_ENABLED')&&BADAROUND_IDENTITY_LOGIN_ENABLED===true
            &&class_exists('BadAround_Identity_WordPress_Portal',false)&&BadAround_Identity_WordPress_Portal::enabled();
    }
    public function register_hooks():void {if($this->enabled())add_action('rest_api_init',[$this,'routes']);}
    public function routes():void {
        if(!$this->enabled())return;
        foreach(['start','verify','check'] as $method)register_rest_route('badaround/v1','/identity/access/'.$method,
            ['methods'=>'POST','callback'=>[$this,$method],'permission_callback'=>'__return_true']);
    }
    private function body($r):?array {
        if(!$this->enabled()||!defined('BADAROUND_IDENTITY_REGISTRATION_ENABLED')||BADAROUND_IDENTITY_REGISTRATION_ENABLED!==true
            ||!defined('BADAROUND_IDENTITY_LOGIN_ENABLED')||BADAROUND_IDENTITY_LOGIN_ENABLED!==true
            ||!is_ssl()||!is_object($r)||!method_exists($r,'get_header')||!method_exists($r,'get_body')||!method_exists($r,'get_json_params')
            ||!BadAround_Identity_Csrf_Policy::origin_allowed($r->get_header('origin'),$this->origin)
            ||!in_array((string)$r->get_header('sec-fetch-site'),['','same-origin','none'],true)
            ||strtolower(trim(explode(';',(string)$r->get_header('content-type'))[0]))!=='application/json'
            ||strlen((string)$r->get_body())>4096)return null;
        $v=$r->get_json_params();return is_array($v)&&!array_is_list($v)?$v:null;
    }
    private function response(array $data,int $status=200) {
        if(class_exists('WP_REST_Response')){$r=new WP_REST_Response($data,$status);$r->header('Cache-Control','private, no-store');$r->header('X-Content-Type-Options','nosniff');return $r;}
        return ['status'=>$status,'body'=>$data];
    }
    private function denied(int $status=403){return $this->response(['ok'=>false,'message'=>'Operazione non disponibile. Riprova più tardi.'],$status);}
    public function start($r) {
        $v=$this->body($r);if($v===null)return $this->denied();
        foreach(['email','first_name','last_name'] as $key)if(isset($v[$key])&&(!is_string($v[$key])||strlen($v[$key])>768))return $this->denied(400);
        if(($v['privacy_acknowledged']??null)!==true||!is_string($v['email']??null)||!filter_var(trim($v['email']),FILTER_VALIDATE_EMAIL))return $this->denied(400);
        $id=$this->access->start($v['email'],$v['first_name']??'',$v['last_name']??'',true,(string)($_SERVER['REMOTE_ADDR']??''),time());
        return $id===null?$this->denied(503):$this->response(['ok'=>true,'state'=>'code_sent','challenge_id'=>$id]);
    }
    public function verify($r) {
        $v=$this->body($r);if($v===null)return $this->denied();
        foreach(['challenge_id','code','first_name','last_name'] as $key)if(isset($v[$key])&&(!is_string($v[$key])||strlen($v[$key])>768))return $this->denied();
        $result=$this->access->verify($v['challenge_id']??'',$v['code']??'',$v['first_name']??'',$v['last_name']??'',time());
        if($result===null)return $this->denied();
        if($result['state']==='profile_required')return $this->response(['ok'=>true,'state'=>'profile_required']);
        $secret=$result['secret'];
        if(!$this->cookies->issue($secret,time(),true)){$this->sessions->revoke($secret,time());return $this->denied(503);}
        $old=$_COOKIE[BadAround_Identity_WordPress_Session_Cookie::NAME]??null;
        if(is_string($old)&&$old!==$secret){try{$this->sessions->revoke($old,time());}catch(Throwable $ignored){}}
        return $this->response(['ok'=>true,'state'=>'verified']);
    }
    public function check($r) {
        $v=$this->body($r);if($v===null||!is_string($v['email']??null))return $this->denied();
        $secret=$_COOKIE[BadAround_Identity_WordPress_Session_Cookie::NAME]??null;
        return $this->response(['ok'=>true,'matches'=>$this->access->matches(is_string($secret)?$secret:null,$v['email'],time())]);
    }
}
