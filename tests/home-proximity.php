<?php
define('ABSPATH',__DIR__);
function add_action($name,$fn){if($name==='rest_api_init')$fn();}
function register_rest_route($ns,$route,$args){$GLOBALS['route']=$args['callback'];}
function sanitize_title($s){return strtolower(trim($s));}
function get_terms($args){return [];}
function is_wp_error($v){return $v instanceof WP_Error;}
function get_ancestors($id,$tax,$kind){return $id===3?[2,1]:[];}
function get_term($id,$tax){return null;}
function get_template_part($path,$unused,$args){echo $args['title'].'|'.$args['status_label'].';';}
class WP_Error{public $code;public function __construct($code,$message,$data){$this->code=$code;}}
class WP_REST_Response{public $data;public function __construct($data,$status){$this->data=$data;}public function header($key,$v){}}
class BadAround_Discovery_Query{public function discover($args){return ['pages'=>2,'items'=>$args['page']===1?[['id'=>1,'title'=>'Lontano','territory'=>['id'=>9],'public_geo'=>['lat'=>45,'lng'=>9],'event_type'=>null,'thumbnail'=>'','excerpt'=>'','permalink'=>'/far','occurred_date'=>'','occurred_time'=>'','public_place_name'=>'']]:[['id'=>2,'title'=>'Vicino','territory'=>['id'=>3],'public_geo'=>['lat'=>41.9,'lng'=>12.5],'event_type'=>null,'thumbnail'=>'','excerpt'=>'','permalink'=>'/near','occurred_date'=>'','occurred_time'=>'','public_place_name'=>'']]];}}
require __DIR__.'/../wordpress/themes/badaround-child/inc/home-proximity.php';
function check($condition,$label){if(!$condition)throw new RuntimeException($label);echo "PASS $label\n";}
$point=['lat'=>41.9,'lng'=>12.5];$areas=['municipality'=>['id'=>3],'province'=>['id'=>2],'region'=>['id'=>1]];
check(badaround_home_item_scope(['public_geo'=>$point],$point,$areas,[])==='nearby','public nearby');
check(badaround_home_item_scope(['public_geo'=>$point+['radius_m'=>6000]],$point,$areas,[3,2,1])==='municipality','privacy circle not falsely nearby');
check(badaround_home_item_scope([],$point,$areas,[3,2,1])==='municipality','territory-only stays eligible');
check(badaround_home_item_scope([],$point,$areas,[2,1])==='province','province fallback');
check(badaround_home_item_scope([],$point,$areas,[1])==='region','region fallback');
check(badaround_home_item_scope([],$point,$areas,[9])===null,'unrelated excluded');
check(badaround_home_area_name('Città metropolitana di Roma Capitale')==='roma','official province name');
class Request{public function get_body(){return '{}';}public function get_json_params(){return ['position'=>['lat'=>41.9,'lng'=>12.5]];}}
$response=($GLOBALS['route'])(new Request());
check($response->data['count']===1&&str_contains($response->data['html'],'Vicino')&&!str_contains($response->data['html'],'Lontano'),'second page local event retained, national event excluded');
class BadRequest extends Request{public function get_json_params(){return ['position'=>['lat'=>91,'lng'=>12.5]];}}
check(($GLOBALS['route'])(new BadRequest()) instanceof WP_Error,'invalid coordinates rejected');
