<?php
/** Plugin Name: BadAround read-only performance observations */
if(!defined('ABSPATH')){exit;}
final class BadAround_Performance_Observer {
    private static $stages=[],$plugins=[],$http=[],$requests=[],$last;
    public static function stage($name){global $wpdb;self::$stages[$name]=['elapsed_ms'=>round((microtime(true)-($_SERVER['REQUEST_TIME_FLOAT']??microtime(true)))*1000,1),'queries'=>(int)($wpdb->num_queries??0)];}
    public static function boot(){
        self::$last=microtime(true);self::stage('observer_loaded');
        foreach(['muplugins_loaded','plugins_loaded','after_setup_theme','init','wp_loaded'] as $hook)add_action($hook,static function()use($hook){self::stage($hook);},PHP_INT_MAX);
        add_action('plugin_loaded',static function($plugin){$now=microtime(true);self::$plugins[]=['plugin'=>plugin_basename($plugin),'interval_ms'=>round(($now-self::$last)*1000,1)];self::$last=$now;});
        add_filter('pre_http_request',static function($pre,$args,$url){$key=hash('sha256',$url);self::$requests[$key]=microtime(true);return $pre;},10,3);
        add_action('http_api_debug',static function($response,$context,$class,$args,$url){
            $key=hash('sha256',$url);if(isset(self::$requests[$key])&&count(self::$http)<20){self::$http[]=['host'=>wp_parse_url($url,PHP_URL_HOST),'duration_ms'=>round((microtime(true)-self::$requests[$key])*1000,1)];unset(self::$requests[$key]);}
        },10,5);
        add_action('rest_api_init',static function(){register_rest_route('badaround-ops/v1','/page-performance/(?P<id>\d+)',[
            'methods'=>'GET','permission_callback'=>static function($r){return current_user_can('manage_options')&&current_user_can('edit_post',(int)$r['id']);},
            'callback'=>static function($r){
                global $wpdb;$id=(int)$r['id'];self::stage('diagnostic_callback');$before=$wpdb->num_queries;$start=microtime(true);
                $request=new WP_REST_Request('GET','/badaround-ops/v1/event-details/'.$id);$request->set_query_params(['render'=>'1']);
                $render=rest_do_request($request);$data=$render->get_data();
                $elapsed=round((microtime(true)-$start)*1000,1);$plugins=self::$plugins;usort($plugins,static fn($a,$b)=>$b['interval_ms']<=>$a['interval_ms']);
                $opcache=function_exists('opcache_get_status')?@opcache_get_status(false):null;
                $opcache_ini=ini_get('opcache.enable');
                $response=new WP_REST_Response([
                    'scope'=>'Authenticated diagnostic request; body-render timing excludes initial bootstrap and browser/network rendering.',
                    'stages'=>self::$stages,'plugin_load_intervals'=>array_slice($plugins,0,8),'external_requests'=>self::$http,
                    'body_render_ms'=>$elapsed,'body_render_queries'=>$wpdb->num_queries-$before,'body_bytes'=>strlen($data['public_html']??''),'render_status'=>$render->get_status(),
                    'query_monitor_loaded'=>class_exists('QueryMonitor',false),'savequeries'=>defined('SAVEQUERIES')&&SAVEQUERIES,
                    'external_object_cache'=>(bool)wp_using_ext_object_cache(),'wp_cache_flag'=>defined('WP_CACHE')&&WP_CACHE,
                    'opcache_extension_loaded'=>extension_loaded('Zend OPcache'),
                    'opcache_ini_enabled'=>$opcache_ini===false?null:(bool)(int)$opcache_ini,
                    'opcache_status_available'=>is_array($opcache),
                    'opcache_restrict_api_configured'=>(string)ini_get('opcache.restrict_api')!=='',
                    'opcache_enabled'=>is_array($opcache)?(bool)($opcache['opcache_enabled']??false):null,
                ],200);$response->header('Cache-Control','private, no-store');return $response;
            }
        ]);});
    }
}
BadAround_Performance_Observer::boot();
