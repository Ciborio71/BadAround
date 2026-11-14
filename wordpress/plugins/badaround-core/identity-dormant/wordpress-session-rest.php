<?php
/**
 * BA-IDENTITY-02B — dormant login session-status / logout REST adapter.
 * No WordPress registration on include. Deliberately returns no profile PII,
 * email or identity UUID. Browser receives only a session-bound CSRF token.
 */
declare(strict_types=1);
require_once __DIR__.'/session-policy.php';
require_once __DIR__.'/csrf-policy.php';
require_once __DIR__.'/wordpress-session-cookie.php';
require_once __DIR__.'/identity-active-status.php';

final class BadAround_Identity_WordPress_Session_REST {
    public const COOKIE='__Host-ba_identity';
    public function __construct(
        private BadAround_Identity_Sessions $sessions,
        private BadAround_Identity_Active_Status $status,
        private BadAround_Identity_WordPress_Session_Cookie $cookies,
        private string $csrfKey,
        private string $expectedOrigin
    ) {
        if (strlen($csrfKey)<32
            || !BadAround_Identity_Csrf_Policy::origin_allowed(
                $expectedOrigin,$expectedOrigin
            )) {throw new InvalidArgumentException('Invalid session HTTP configuration');}
    }
    public function register_hooks():bool {
        if (!self::enabled() || !function_exists('add_action')) {return false;}
        add_action('rest_api_init',[$this,'routes']);
        return true;
    }
    public function routes():void {
        if (!self::enabled() || !function_exists('register_rest_route')) {return;}
        register_rest_route('badaround/v1','/identity/session',[
            'methods'=>'POST','callback'=>[$this,'session'],
            'permission_callback'=>'__return_true'
        ]);
        register_rest_route('badaround/v1','/identity/logout',[
            'methods'=>'POST','callback'=>[$this,'logout'],
            'permission_callback'=>'__return_true'
        ]);
    }
    private static function enabled():bool {
        return defined('BADAROUND_IDENTITY_LOGIN_ENABLED')
            && BADAROUND_IDENTITY_LOGIN_ENABLED===true;
    }
    /** Read-only server rendering; never exposes bearer, identity or CSRF data. */
    public function authenticated():bool {
        return self::enabled() && function_exists('is_ssl') && is_ssl()===true
            && $this->active_secret(time())!==null;
    }
    private function accepted($request):bool {
        if (!self::enabled() || !function_exists('is_ssl') || is_ssl()!==true
            || !is_object($request) || !method_exists($request,'get_header')) {
            return false;
        }
        $origin=$request->get_header('origin');
        if (!is_string($origin) || !BadAround_Identity_Csrf_Policy::origin_allowed(
            $origin,$this->expectedOrigin
        )) {return false;}
        $site=$request->get_header('sec-fetch-site');
        return !is_string($site) || $site==='' ||
            in_array($site,['same-origin','none'],true);
    }
    private function active_secret(int $now):?string {
        $bearer=$_COOKIE[self::COOKIE]??null;
        if (!is_string($bearer) || preg_match('/^[a-f0-9]{64}$/D',$bearer)!==1) {
            return null;
        }
        try {
            $identity=$this->sessions->identity_for($bearer,$now);
            return is_string($identity) && $this->status->is_active_verified($identity)
                ? $bearer : null;
        }catch(Throwable $error){return null;}
    }
    public function session($request) {
        if (!$this->accepted($request)) {return $this->error(403);}
        $secret=$this->active_secret(time());
        if ($secret===null) {return $this->result(['authenticated'=>false]);}
        try {
            return $this->result(['authenticated'=>true,
                'csrf'=>BadAround_Identity_Csrf_Policy::issue($secret,$this->csrfKey)]);
        } catch(Throwable $error){return $this->error(503);}
    }
    public function logout($request) {
        if (!$this->accepted($request)
            || !method_exists($request,'get_header')) {return $this->error(403);}
        $secret=$this->active_secret(time());
        if ($secret===null) {return $this->error(403);}
        $csrf=$request->get_header('x-badaround-csrf');
        if (!is_string($csrf) || !BadAround_Identity_Csrf_Policy::verify(
            $csrf,$secret,$this->csrfKey
        )) {return $this->error(403);}
        $now=time();
        try {
            // Revoke first: failure to clear an HTTP cookie cannot restore
            // the server-side session. Both steps are idempotent by design.
            if (!$this->sessions->revoke($secret,$now)) {
                return $this->error(503);
            }
            if (!$this->cookies->clear($now,true)) {
                return $this->error(503);
            }
            return $this->result(['ok'=>true,'authenticated'=>false]);
        }catch(Throwable $error){return $this->error(503);}
    }
    private function result(array $data) {
        if (class_exists('WP_REST_Response')) {
            $r=new WP_REST_Response($data,200);
            $r->header('Cache-Control','no-store, private');
            return $r;
        }
        return $data;
    }
    private function error(int $status) {
        return class_exists('WP_Error')
            ? new WP_Error('ba_identity_session_denied','Operazione non disponibile.',
                ['status'=>$status])
            : ['ok'=>false,'status'=>$status];
    }
}
