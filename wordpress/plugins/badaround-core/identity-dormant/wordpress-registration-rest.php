<?php
/**
 * BA-IDENTITY-01P — dormant registration REST adapter.
 * No hooks or routes until an explicitly enabled, reviewed Core bootstrap.
 * Never activates legacy ownership or publishes a report.
 */
declare(strict_types=1);
require_once __DIR__.'/registration-service.php';
require_once __DIR__.'/csrf-policy.php';

final class BadAround_Identity_WordPress_Registration_REST {
    public const NAMESPACE='badaround/v1';
    public const MAX_BYTES=4096;
    public function __construct(
        private BadAround_Identity_Registration_Actions $registration,
        private string $siteOrigin
    ) {
        if (!BadAround_Identity_Csrf_Policy::origin_allowed($siteOrigin,$siteOrigin)) {
            throw new InvalidArgumentException('Invalid expected site origin');
        }
    }
    public function register_hooks():bool {
        if (!defined('BADAROUND_IDENTITY_REGISTRATION_ENABLED')
            || BADAROUND_IDENTITY_REGISTRATION_ENABLED !== true
            || !function_exists('add_action')) {return false;}
        add_action('rest_api_init',[$this,'register_routes']);
        return true;
    }
    public function register_routes():void {
        if (!defined('BADAROUND_IDENTITY_REGISTRATION_ENABLED')
            || BADAROUND_IDENTITY_REGISTRATION_ENABLED !== true
            || !function_exists('register_rest_route')) {return;}
        foreach (['/identity/register/start'=>'start',
            '/identity/register/verify'=>'verify'] as $route=>$method) {
            register_rest_route(self::NAMESPACE,$route,[
                'methods'=>'POST','callback'=>[$this,$method],
                'permission_callback'=>'__return_true'
            ]);
        }
    }
    private function accepted($request):?array {
        if (!defined('BADAROUND_IDENTITY_REGISTRATION_ENABLED')
            || BADAROUND_IDENTITY_REGISTRATION_ENABLED !== true
            || !function_exists('is_ssl') || is_ssl()!==true
            || !is_object($request) || !method_exists($request,'get_header')
            || !method_exists($request,'get_body')
            || !method_exists($request,'get_json_params')) {
            return null;
        }
        if (!BadAround_Identity_Csrf_Policy::origin_allowed(
            $request->get_header('origin'),$this->siteOrigin
        )) {return null;}
        $site=$request->get_header('sec-fetch-site');
        if (is_string($site) && $site!=='' && !in_array(
            $site,['same-origin','none'],true
        )) {return null;}
        $type=strtolower(trim(explode(';',(string)$request->get_header('content-type'),2)[0]));
        $raw=(string)$request->get_body();
        if ($type!=='application/json' || $raw==='' || strlen($raw)>self::MAX_BYTES) {
            return null;
        }
        $json=$request->get_json_params();
        return is_array($json)?$json:null;
    }
    public function start($request) {
        $body=$this->accepted($request);
        if ($body===null) {return $this->error(403);}
        if (($body['privacy_acknowledged']??null)!==true) {
            return $this->error(400);
        }
        $first=$body['first_name']??null;
        $last=$body['last_name']??null;
        $email=$body['email']??null;
        if (!is_string($first) || !is_string($last) || !is_string($email)
            || strlen($first)>768 || strlen($last)>768 || strlen($email)>191) {
            return $this->error(400);
        }
        // The server controls the privacy notice version and timestamp.
        // Never trust an input-supplied version/accepted_at or fabricated identity.
        $id=bin2hex(random_bytes(16));
        try {
            $ip=$_SERVER['REMOTE_ADDR']??'';
            if (is_string($ip) && filter_var($ip,FILTER_VALIDATE_IP)) {
                $created=$this->registration->start(
                    $first,$last,$email,true,$ip,time()
                );
                if (is_string($created) && preg_match('/^[a-f0-9]{32}$/D',$created)) {
                    $id=$created;
                }
            }
        } catch(Throwable $e) {}
        return $this->ok(['challenge_id'=>$id,
            'message'=>'Se i dati sono validi, riceverai un codice di verifica.']);
    }
    public function verify($request) {
        $body=$this->accepted($request);
        if ($body===null) {return $this->error(403);}
        $id=$body['challenge_id']??null;$code=$body['code']??null;
        if (!is_string($id) || !preg_match('/^[a-f0-9]{32}$/D',$id)
            || !is_string($code) || !preg_match('/^(?:[0-9]{6}|[0-9]{8})$/D',$code)) {
            return $this->error(403);
        }
        try {
            $identity=$this->registration->verify($id,$code,time());
            // Never reveal the private identity ID or create report ownership
            // in an unauthenticated registration response.
            return $identity===null ? $this->error(403)
                : $this->ok(['status'=>'verified']);
        } catch(Throwable $e) {return $this->error(503);}
    }
    private function ok(array $payload) {
        $body=['ok'=>true]+$payload;
        return function_exists('rest_ensure_response')
            ?rest_ensure_response($body):$body;
    }
    private function error(int $status) {
        if (class_exists('WP_Error')) {
            return new WP_Error('ba_identity_registration_denied',
                'Operazione non disponibile.',['status'=>$status]);
        }
        return ['ok'=>false,'status'=>$status];
    }
}
