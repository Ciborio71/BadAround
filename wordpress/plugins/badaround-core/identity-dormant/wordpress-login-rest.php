<?php
/**
 * BA-IDENTITY-01O: dormant login REST controller.
 * Explicitly disabled unless a reviewed bootstrap wires every dependency.
 * Never handles registration/consent: verified users ONLY.
 */
declare(strict_types=1);
require_once __DIR__.'/otp-issuer.php';
require_once __DIR__.'/verification-coordinator.php';
require_once __DIR__.'/wordpress-session-cookie.php';
require_once __DIR__.'/csrf-policy.php';

final class BadAround_Identity_WordPress_Login_REST {
    public const NAMESPACE = 'badaround/v1';
    public const MAX_JSON_BYTES = 4096;
    public function __construct(
        private BadAround_Identity_Otp_Issuer $issuer,
        private BadAround_Identity_Verification_Coordinator $verifier,
        private BadAround_Identity_Sessions $sessions,
        private BadAround_Identity_WordPress_Session_Cookie $cookies,
        private string $csrfKey,
        private string $siteOrigin
    ) {
        if (strlen($csrfKey) < 32
            || !BadAround_Identity_Csrf_Policy::origin_allowed($siteOrigin,$siteOrigin)) {
            throw new InvalidArgumentException('Invalid login REST configuration');
        }
    }
    public function register_hooks():bool {
        if (!defined('BADAROUND_IDENTITY_LOGIN_ENABLED')
            || BADAROUND_IDENTITY_LOGIN_ENABLED !== true
            || !function_exists('add_action')) { return false; }
        add_action('rest_api_init',[$this,'routes']);
        return true;
    }
    public function routes():void {
        if (!defined('BADAROUND_IDENTITY_LOGIN_ENABLED')
            || BADAROUND_IDENTITY_LOGIN_ENABLED !== true
            || !function_exists('register_rest_route')) { return; }
        register_rest_route(self::NAMESPACE,'/identity/login/start',[
            'methods'=>'POST','callback'=>[$this,'start'],
            'permission_callback'=>'__return_true'
        ]);
        register_rest_route(self::NAMESPACE,'/identity/login/verify',[
            'methods'=>'POST','callback'=>[$this,'verify'],
            'permission_callback'=>'__return_true'
        ]);
    }

    private function accepted($request):?array {
        if (!defined('BADAROUND_IDENTITY_LOGIN_ENABLED')
            || BADAROUND_IDENTITY_LOGIN_ENABLED !== true
            || !is_object($request)
            || !method_exists($request,'get_header')
            || !method_exists($request,'get_body')
            || !method_exists($request,'get_json_params')
            || !function_exists('is_ssl') || is_ssl()!==true) {
            return null;
        }
        $origin=$request->get_header('origin');
        if (!is_string($origin)
            || !BadAround_Identity_Csrf_Policy::origin_allowed($origin,$this->siteOrigin)) {
            return null;
        }
        $fetchSite=$request->get_header('sec-fetch-site');
        if (is_string($fetchSite) && $fetchSite!==''
            && !in_array($fetchSite,['same-origin','none'],true)) { return null; }
        $contentType=strtolower(trim(explode(';',(string)$request->get_header('content-type'),2)[0]));
        $raw=(string)$request->get_body();
        if ($contentType!=='application/json' || $raw==='' || strlen($raw)>self::MAX_JSON_BYTES) {
            return null;
        }
        $body=$request->get_json_params();
        return is_array($body) ? $body : null;
    }
    public function start($request) {
        $body=$this->accepted($request);
        if ($body===null) { return $this->error(403); }
        // Generic receipt even if email is unknown, malformed, throttled or
        // mail delivery is unavailable; never expose raw challenge or code.
        $challengeId=bin2hex(random_bytes(16)); // indistinguishable decoy on every failure
        try {
            $email=$body['email']??null;
            if (is_string($email) && strlen($email)<=191) {
                $ip=$_SERVER['REMOTE_ADDR']??'';
                if (is_string($ip) && filter_var($ip,FILTER_VALIDATE_IP)) {
                    $issued=$this->issuer->issue($email,'login',$ip,time());
                    if (is_string($issued) && preg_match('/^[a-f0-9]{32}$/D',$issued)) {
                        $challengeId=$issued;
                    }
                }
            }
        } catch(Throwable $error) {}
        return $this->response(['ok'=>true,'challenge_id'=>$challengeId,
            'message'=>'Se il recapito è valido, riceverai istruzioni di accesso.']);
    }
    public function verify($request) {
        $body=$this->accepted($request);
        if ($body===null) { return $this->error(403); }
        $id=$body['challenge_id']??null;
        $code=$body['code']??null;
        if (!is_string($id) || !preg_match('/^[a-f0-9]{32}$/D',$id)
            || !is_string($code) || !preg_match('/^(?:[0-9]{6}|[0-9]{8})$/D',$code)) {
            return $this->error(403);
        }
        $now=time(); $secret=null;
        try {
            $secret=$this->verifier->verify_and_issue($id,$code,$now);
            if ($secret===null) { return $this->error(403); }
            if (!$this->cookies->issue($secret,$now,true)) {
                $this->sessions->revoke($secret,$now);
                return $this->error(503);
            }
            $csrf=BadAround_Identity_Csrf_Policy::issue($secret,$this->csrfKey);
            // Only the CSRF token is returned; the session secret stays in
            // the Secure HttpOnly host-only cookie.
            return $this->response(['ok'=>true,'csrf'=>$csrf]);
        } catch(Throwable $error) {
            if (is_string($secret)) {
                try {$this->sessions->revoke($secret,$now);}catch(Throwable $ignored){}
            }
            return $this->error(503);
        }
    }
    private function response(array $data) {
        return function_exists('rest_ensure_response') ? rest_ensure_response($data) : $data;
    }
    private function error(int $status) {
        if (class_exists('WP_Error')) {
            return new WP_Error('ba_identity_auth_denied','Operazione non disponibile.',
                ['status'=>$status]);
        }
        return ['ok'=>false,'status'=>$status];
    }
}
