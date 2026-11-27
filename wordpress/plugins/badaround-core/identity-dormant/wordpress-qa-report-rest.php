<?php
/**
 * BA-IDENTITY-WIZARD-13: separate, private QA REST controller.
 *
 * No hooks/routes on include. The existing public /badaround/v1/reports
 * endpoint is UNCHANGED. Explicitly constructed by a trusted QA bootstrap,
 * with server-owned canonical validator and normal/media native adapters.
 * Request content can never supply an identity, operation ID or callback.
 */
declare(strict_types=1);
require_once __DIR__.'/wordpress-qa-report-transport.php';
require_once __DIR__.'/wordpress-privacy-notice.php';

final class BadAround_Identity_QA_Report_REST {
    public const NAMESPACE='badaround-identity-qa/v1';
    public const ROUTE='/reports';
    private Closure $validate;
    private Closure $native;
    private Closure $clock;

    /**
     * $validate: callable(array): array|WP_Error (canonical schema validator)
     * $native: callable(array,WP_REST_Request,bool): array|WP_Error
     * Both factories MUST be bound by the trusted WordPress composition.
     */
    public function __construct(
        private BadAround_Identity_QA_Report_Transport $transport,
        callable $validate,callable $native,?callable $clock=null
    ) {
        $this->validate=Closure::fromCallable($validate);
        $this->native=Closure::fromCallable($native);
        $this->clock=$clock===null ? static fn():int=>time() : Closure::fromCallable($clock);
    }

    public static function enabled():bool {
        return defined('BADAROUND_IDENTITY_RUNTIME_INTEGRATION_ENABLED')
            && BADAROUND_IDENTITY_RUNTIME_INTEGRATION_ENABLED===true
            && defined('BADAROUND_IDENTITY_PRIVATE_QA_ENABLED')
            && BADAROUND_IDENTITY_PRIVATE_QA_ENABLED===true
            && defined('BADAROUND_IDENTITY_QA_REPORTS_ENABLED')
            && BADAROUND_IDENTITY_QA_REPORTS_ENABLED===true
            && BadAround_Identity_Private_Access::authorized()
            && BadAround_Identity_WordPress_Privacy_Notice::runtime_version()!==null;
    }

    /** No automatic hooks; must be called from independently authorized QA. */
    public function register_hooks():bool {
        if (!self::enabled() || !function_exists('add_action'))return false;
        add_action('rest_api_init',[$this,'register_routes']);
        return true;
    }
    public function register_routes():void {
        if (!self::enabled() || !function_exists('register_rest_route'))return;
        register_rest_route(self::NAMESPACE,self::ROUTE,[
            'methods'=>'POST',
            'callback'=>[$this,'submit'],
            'permission_callback'=>[$this,'permission'],
        ]);
    }

    public function permission($request) {
        if (!self::enabled())return self::deny(403);
        if (!is_object($request) || !method_exists($request,'get_header'))return self::deny(403);
        if (!function_exists('is_ssl') || !is_ssl()
            || !function_exists('home_url') || !function_exists('wp_parse_url'))return self::deny(403);
        $base=wp_parse_url(home_url('/'));
        if (!is_array($base) || ($base['scheme']??null)!=='https'
            || strtolower((string)($base['host']??''))!=='badaround.it')return self::deny(403);
        $fetch=strtolower(trim((string)$request->get_header('sec-fetch-site')));
        if ($fetch==='cross-site')return self::deny(403);
        $origin=trim((string)$request->get_header('origin'));
        // Private cookie-authenticated POSTs must carry an explicit,
        // matching Origin. Never accept an absent origin by default.
        if ($origin==='' || !self::same_origin($origin,home_url('/')))
            return self::deny(403);
        $marker=strtolower(trim((string)$request->get_header('x-badaround-intake')));
        if ($marker!=='badaround-report/v1')return self::deny(403);
        return true;
    }

    public function submit($request) {
        // WordPress should call permission_callback, but repeated verification
        // prevents direct invocation from bypassing the private QA boundary.
        if ($this->permission($request)!==true)return self::response('unavailable',403);
        if (!method_exists($request,'get_body'))return self::response('invalid_request',400);
        $type=strtolower(trim(explode(';',(string)$request->get_header('content-type'))[0]));
        if ($type!=='application/json')return self::response('invalid_request',415);
        $body=(string)$request->get_body();
        if ($body==='' || strlen($body)>131072)return self::response('invalid_request',400);
        $raw=json_decode($body,true,32);
        if (!is_array($raw) || array_is_list($raw))return self::response('invalid_request',400);
        try {
            $canonical=($this->validate)($raw);
            if (!is_array($canonical) || !is_string($canonical['submission_id']??null)
                || !BadAround_Identity_Pre_Intake_Reservation::valid_submission($canonical['submission_id']))
                return self::response('invalid_request',422);
            $items=$canonical['media']['items']??[];
            if (!is_array($items))return self::response('invalid_request',422);
            $hasMedia=count($items)>0;
            if ($hasMedia && (!function_exists('is_ssl') || !is_ssl()))
                return self::response('unavailable',403);
            // Transport reserves durable creator intent before invoking the
            // trusted native callback; no direct native call here.
            $result=$this->transport->process(
                $canonical,
                fn()=>($this->native)($raw,$request,$hasMedia),
                $_COOKIE,
                (int)($this->clock)()
            );
            $state=$result['state']??'unavailable';
            $status=['owner_confirmed'=>201,'pending'=>202,'conflict'=>409,
                'denied'=>401,'unavailable'=>403][$state]??503;
            return self::response($state,$status);
        } catch(Throwable $error) {
            return self::response('unavailable',503);
        }
    }

    private static function same_origin(string $left,string $right):bool {
        $a=wp_parse_url($left);$b=wp_parse_url($right);
        if(!is_array($a)||!is_array($b))return false;
        foreach(['scheme','host'] as $key) {
            if(!isset($a[$key],$b[$key]) || strtolower((string)$a[$key])!==strtolower((string)$b[$key]))
                return false;
        }
        $port=static fn(array $url):int=>(int)($url['port']??($url['scheme']==='https'?443:80));
        return $port($a)===$port($b);
    }

    private static function deny(int $status) {
        return class_exists('WP_Error')
            ? new WP_Error('ba_private_qa_denied','Not available',['status'=>$status])
            : false;
    }
    private static function response(string $state,int $status) {
        $allowed=['owner_confirmed','pending','conflict','denied','unavailable','invalid_request'];
        if (!in_array($state,$allowed,true))$state='unavailable';
        $payload=['state'=>$state];
        if (class_exists('WP_REST_Response')) {
            $r=new WP_REST_Response($payload,$status);
            $r->header('Cache-Control','no-store, private');
            $r->header('X-Content-Type-Options','nosniff');
            return $r;
        }
        return ['status'=>$status,'body'=>$payload];
    }
}
