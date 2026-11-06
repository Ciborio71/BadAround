<?php
define('ABSPATH',__DIR__);$_SERVER['REQUEST_TIME_FLOAT']=microtime(true);$GLOBALS['hooks']=[];$GLOBALS['allowed']=true;
function add_action($name,$fn,$priority=10,$args=1){$GLOBALS['hooks'][$name]=$fn;}function add_filter($name,$fn,$priority=10,$args=1){$GLOBALS['hooks'][$name]=$fn;}
function plugin_basename($p){return basename($p);}function wp_parse_url($u,$part){return parse_url($u,$part);}function current_user_can($cap,$id=null){return $GLOBALS['allowed'];}function wp_using_ext_object_cache(){return false;}
function register_rest_route($ns,$route,$args){$GLOBALS['route']=$args;}
class WP_REST_Request extends ArrayObject{public function __construct($method,$route){parent::__construct(['id'=>381]);}public function set_query_params($p){}}
class WP_REST_Response{public $data;function __construct($d,$s){$this->data=$d;}function header($k,$v){}function get_data(){return $this->data;}function get_status(){return 200;}}
function rest_do_request($request){$GLOBALS['wpdb']->num_queries+=2;return new WP_REST_Response(['public_html'=>'<main>public</main>'],200);}
$wpdb=(object)['num_queries'=>20];require __DIR__.'/../wordpress/mu-plugins/badaround-performance-observer.php';
($GLOBALS['hooks']['rest_api_init'])();$r=new WP_REST_Request('GET','');
$GLOBALS['allowed']=false;if(($GLOBALS['route']['permission_callback'])($r)!==false)throw new RuntimeException('Private diagnostic exposed');$GLOBALS['allowed']=true;
($GLOBALS['hooks']['pre_http_request'])(false,[],'https://provider.test/path?token=secret');
($GLOBALS['hooks']['http_api_debug'])(null,'response','',[],'https://provider.test/path?token=secret');
$result=($GLOBALS['route']['callback'])($r);$d=$result->get_data();
if($d['body_render_queries']!==2||!is_numeric($d['body_render_ms'])||$d['body_bytes']!==19)throw new RuntimeException('Invalid observations');
if(str_contains(json_encode($d),'secret')||str_contains(json_encode($d),'<main>'))throw new RuntimeException('Diagnostic leaked data');
echo "PAGE_PERFORMANCE_OBSERVER_SUITE_PASS\n";
