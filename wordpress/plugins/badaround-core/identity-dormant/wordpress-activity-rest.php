<?php
/** BA-IDENTITY-02E private-only activity REST endpoint, no hooks on include. */
declare(strict_types=1);
require_once __DIR__.'/mysql-private-activity.php';

final class BadAround_Identity_WordPress_Activity_REST {
    public function __construct(private BadAround_Identity_Private_Activity $activity) {}
    public function register_hooks():bool {
        if (!defined('BADAROUND_IDENTITY_COMMUNITY_ENABLED')
            || BADAROUND_IDENTITY_COMMUNITY_ENABLED!==true
            || !function_exists('add_action')) {return false;}
        add_action('rest_api_init',[$this,'routes']);
        return true;
    }
    public function routes():void {
        if (!defined('BADAROUND_IDENTITY_COMMUNITY_ENABLED')
            || BADAROUND_IDENTITY_COMMUNITY_ENABLED!==true
            || !function_exists('register_rest_route')) {return;}
        register_rest_route('badaround/v1','/identity/my-activity',[
            'methods'=>'POST','callback'=>[$this,'mine'],
            'permission_callback'=>'__return_true'
        ]);
    }
    public function mine($request) {
        if (!defined('BADAROUND_IDENTITY_COMMUNITY_ENABLED')
            || BADAROUND_IDENTITY_COMMUNITY_ENABLED!==true
            || !function_exists('is_ssl') || is_ssl()!==true
            || !is_object($request) || !method_exists($request,'get_header')) {
            return $this->deny();
        }
        $site=$request->get_header('sec-fetch-site');
        if (is_string($site) && $site!=='' && !in_array(
            $site,['same-origin','none'],true
        )) {return $this->deny();}
        $token=$_COOKIE['__Host-ba_identity']??null;
        $csrf=$request->get_header('x-badaround-csrf');
        $origin=$request->get_header('origin');
        if (!is_string($token) || !is_string($csrf) || !is_string($origin)) {
            return $this->deny();
        }
        $rows=$this->activity->mine($token,$csrf,$origin,time());
        if ($rows===null) {return $this->deny();}
        if (class_exists('WP_REST_Response')) {
            $result=new WP_REST_Response(['items'=>$rows],200);
            $result->header('Cache-Control','no-store, private');
            return $result;
        }
        return ['items'=>$rows];
    }
    private function deny() {
        return class_exists('WP_Error')
            ? new WP_Error('ba_identity_activity_denied','Operazione non disponibile.',
                ['status'=>403])
            : ['ok'=>false,'status'=>403];
    }
}
