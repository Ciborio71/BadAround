<?php
/* GEO-PUBLIC-05 real Publication Service, isolated transactional WP harness. No staging access. */
define('ABSPATH', __DIR__ . '/');
function __($x){return $x;}
function absint($v){return abs((int)$v);}
function sanitize_key($v){return strtolower(preg_replace('/[^a-z0-9_\\-]/i','',(string)$v));}
function sanitize_text_field($v){return trim(strip_tags((string)$v));}
function wp_strip_all_tags($v){return strip_tags((string)$v);}
function wp_kses_post($v){return $v;}
function is_wp_error($v){return $v instanceof WP_Error;}
class WP_Error {private $c;function __construct($c,$m=''){$this->c=$c;}function get_error_code(){return $this->c;}}
class WP_Term {public $term_id,$parent,$slug,$name,$taxonomy='ba_territorio';function __construct($id,$parent,$name,$taxonomy='ba_territorio'){$this->term_id=$id;$this->parent=$parent;$this->name=$name;$this->slug=strtolower($name);$this->taxonomy=$taxonomy;}}
class BadAround_Event_Post_Type {const POST_TYPE='ba_evento';const TERRITORY_TAX='ba_territorio';const EVENT_TYPE_TAX='ba_tipo_evento';}
require_once dirname(__DIR__).'/wordpress/plugins/badaround-core/includes/class-badaround-moderation-service.php';
class BadAround_Audit_Log {public static function record(){return true;} public static function transition(){return !$GLOBALS['audit_fail'];} public static function geographic_publication($id,$c,$p){if($GLOBALS['audit_fail'])return false;$GLOBALS['geo_audits'][]=[$id,$c['decision']['mode'],$p['mode'],$p['radius_meters']??null];return true;}}
class DB {public $prefix='wp_',$postmeta='wp_postmeta';function prepare($sql){return $sql;}function get_row($q){return (object)array_merge(['author_name'=>'','author_surname'=>'','author_email'=>'','author_phone'=>'','exact_address'=>'Private Address 99','exact_lat'=>null,'exact_lng'=>null,'full_plate'=>'','content_original'=>''],$GLOBALS['private_fixture']??[]);}function get_var($q){return 0;}function get_results($q){return [];}}
$wpdb=new DB();
$GLOBALS['terms']=[1=>new WP_Term(1,0,'Lazio'),2=>new WP_Term(2,1,'Roma'),3=>new WP_Term(3,2,'Pomezia'),4=>new WP_Term(4,3,'Torvaianica'),5=>new WP_Term(5,0,'Standalone'),10=>new WP_Term(10,0,'Veicoli','ba_tipo_evento'),11=>new WP_Term(11,10,'Furto','ba_tipo_evento')];
$GLOBALS['meta']=[];$GLOBALS['posts']=[];$GLOBALS['object_terms']=[];$GLOBALS['term_meta']=[];
function get_term($id,$tax=''){ $v=$GLOBALS['terms'][$id]??null;return $v&&(!$tax||$v->taxonomy===$tax)?$v:null;}
function get_ancestors($id,$tax,$type='taxonomy'){ $out=[];while(($t=get_term($id,$tax))&&$t->parent){$out[]=$t->parent;$id=$t->parent;}return $out;}
function get_term_meta($id,$key,$single=true){return $GLOBALS['term_meta'][$id][$key]??'';}
function get_post($id){return $GLOBALS['posts'][$id]??null;}
function get_post_meta($id,$key='',$single=true){return $key===''?($GLOBALS['meta'][$id]??[]):($GLOBALS['meta'][$id][$key]??'');}
function update_post_meta($id,$key,$val){$gate=apply_filters('update_post_metadata',null,$id,$key,$val,null);if($gate!==null)return $gate;$GLOBALS['meta'][$id][$key]=$val;return true;}
function delete_post_meta($id,$key){$gate=apply_filters('delete_post_metadata',null,$id,$key,null,null);if($gate!==null)return $gate;unset($GLOBALS['meta'][$id][$key]);return true;}
function wp_get_post_terms($id,$tax){return array_map(fn($i)=>get_term($i,$tax),$GLOBALS['object_terms'][$id][$tax]??[]);}
function wp_set_object_terms($id,$ids,$tax,$append=false){$GLOBALS['object_terms'][$id][$tax]=$ids;return $ids;}
function wp_update_post($x,$error=false){foreach($x as $k=>$v){if($k!=='ID')$GLOBALS['posts'][$x['ID']]->$k=$v;}return $x['ID'];}
function current_user_can($cap,...$args){return !in_array($cap,$GLOBALS['denied']??[],true);} function get_post_status($id){return get_post($id)->post_status;}
$GLOBALS['filters']=[];
function add_filter($name,$fn,$priority=10,$args=1){$GLOBALS['filters'][$name][$priority][]=$fn;}
function remove_filter($name,$fn,$priority=10){foreach($GLOBALS['filters'][$name][$priority]??[] as $k=>$f)if($fn===$f)unset($GLOBALS['filters'][$name][$priority][$k]);}
function apply_filters($name,$v,...$args){$groups=$GLOBALS['filters'][$name]??[];ksort($groups);foreach($groups as $fns)foreach($fns as $fn)$v=$fn($v,...$args);return $v;}
function do_action($name,...$args){$GLOBALS['actions'][]=$name;}
function clean_post_cache($id){} function wp_json_encode($x){return json_encode($x);}
function has_post_thumbnail(){return false;} function set_post_thumbnail(){}
class BadAround_Media_Repository{function approve_received_images_for_event(){return [];}function materialize_approved_public_media_for_event(){return [];}}

function wp_salt($v='auth'){return 'test';}
function current_time($v,$gmt=false){return '2026-10-09 12:00:00';}
require_once dirname(__DIR__).'/wordpress/plugins/badaround-core/includes/class-badaround-publication-service.php';
function check($ok,$msg){if(!$ok){fwrite(STDERR,"FAIL $msg\n");exit(1);}echo "PASS $msg\n";}
function seed($id,$territory=4,$mode='f16-c8',$date='',$coords=[]){
 $GLOBALS['posts'][$id]=(object)['ID'=>$id,'post_type'=>'ba_evento','post_status'=>'pending','post_title'=>'Segnalazione da moderare #17','post_content'=>''];
 $GLOBALS['object_terms'][$id]=['ba_territorio'=>$territory?[$territory]:[],'ba_tipo_evento'=>[11]];
 $GLOBALS['meta'][$id]=array_merge(['_ba_moderation_status'=>'approved','_ba_time_precision'=>$mode,'_ba_occurred_date'=>$date,'_ba_public_place_name'=>$territory?get_term($territory)->name:'','_ba_public_location_precision'=>'f32-c4'],$coords);
}
class Geo05DB extends DB {
 public $posts='wp_posts',$term_relationships='wp_term_relationships',$last_error='',$snapshot;
 function prepare($sql,...$args){foreach($args as $arg){$v=is_int($arg)?(string)$arg:"'".str_replace("'","''",$arg)."'";$sql=preg_replace('/%[ds]/',$v,$sql,1);}return $sql;}
 function get_var($sql){if(str_contains($sql,'information_schema'))return 'InnoDB';if(str_contains($sql,'JSON_CONTAINS_PATH')){ $l=json_decode($GLOBALS['private_fixture']['content_original']??'',true)['canonical']['location']??[];return (int)(isset($l['public_location_request'])||isset($l['public_location_request_version']));}return 0;}
 function get_row($sql){if(str_contains($sql,'FROM wp_posts')){preg_match('/ID=(\d+)/',$sql,$m);return get_post((int)$m[1]);}return parent::get_row($sql);}
 function query($sql){if($sql==='START TRANSACTION')$this->snapshot=serialize([$GLOBALS['meta'],$GLOBALS['posts'],$GLOBALS['object_terms'],$GLOBALS['geo_audits']]);if($sql==='ROLLBACK')[$GLOBALS['meta'],$GLOBALS['posts'],$GLOBALS['object_terms'],$GLOBALS['geo_audits']]=unserialize($this->snapshot);if($sql==='COMMIT'&&($GLOBALS['commit_fail']??false))return false;return 1;}
}
$wpdb=new Geo05DB();$GLOBALS['geo_audits']=[];$GLOBALS['audit_fail']=false;$GLOBALS['actions']=[];
$svc=new BadAround_Publication_Service();
BadAround_Publication_Service::register_geo_guards();
function g5seed($id,$request='exact_point',$mode='exact_point'){
 seed($id);$GLOBALS['meta'][$id]['_ba_primary_report_id']=$id+1000;
 $GLOBALS['meta'][$id]['_ba_public_location_precision']='f32-c6';
 $l=['public_location_request'=>$request,'public_location_request_version'=>'public-location-request-v1','exact_lat'=>41.8991622,'exact_lng'=>12.4730719];
 $GLOBALS['private_fixture']=['id'=>$id+1000,'exact_lat'=>$l['exact_lat'],'exact_lng'=>$l['exact_lng'],'exact_address'=>'Private Address 99','content_original'=>json_encode(['canonical'=>['location'=>$l]])];
 $GLOBALS['meta'][$id]['_ba_public_location_decision']=['version'=>'public-location/v1','policy_version'=>'public-location/v1','mode'=>$mode,'reason'=>$request===$mode?'':'Tutela della privacy','reason_code'=>$request===$mode?'':'privacy','exact_confirmed'=>$mode==='exact_point','decided_at'=>'2026-10-20T18:00:00Z','decided_by'=>42];
}
function g5none($id){return ''===get_post_meta($id,'_ba_public_lat')&&''===get_post_meta($id,'_ba_public_lng')&&''===get_post_meta($id,'_ba_public_geo');}
foreach([['exact_point','exact_point'],['exact_point','approximate_area'],['approximate_area','approximate_area']] as $i=>[$req,$mode]){
 $id=500+$i;g5seed($id,$req,$mode);$private=$GLOBALS['private_fixture'];
 check(is_wp_error($svc->prepare_public_projection($id))&&g5none($id),'direct preparation without B3 cannot create '.$mode);
 $GLOBALS['meta'][$id]['_ba_moderation_status']='new';check(is_wp_error($svc->publish($id))&&g5none($id),'pending decision without B2/B3 creates no coordinates');$GLOBALS['meta'][$id]['_ba_moderation_status']='approved';
 $r=$svc->publish($id);check($r===true,'controlled B3 '.$req.' -> '.$mode.(is_wp_error($r)?' ERROR='.$r->get_error_code():''));
 $p=get_post_meta($id,'_ba_public_geo');check($p['mode']===$mode&&get_post_status($id)==='publish'&&get_post_meta($id,'_ba_moderation_status')==='published','explicit projection and B3 lifecycle');
 check($GLOBALS['private_fixture']===$private,'private exact source preserved');
 check(!str_contains(get_post($id)->post_content,'Private Address')&&get_post_meta($id,'_ba_public_address')==='','no automatic exact textual address');
 if($mode==='exact_point')check($p['lat']===41.8991622&&$p['lng']===12.4730719&&$p['radius_meters']===0,'exact matches private and uses explicit mode');
 else check(abs($p['lat']-41.8991622)>0.00001||abs($p['lng']-12.4730719)>0.00001,'approximate center differs from private');
 $a=count($GLOBALS['geo_audits']);check($svc->publish($id)===true&&$p===get_post_meta($id,'_ba_public_geo')&&count($GLOBALS['geo_audits'])===$a,'repeat B3 stable geometry and no duplicated geo audit');
 check(BadAround_Public_Geo_Projection::public_view($id)===BadAround_Public_Geo_Projection::public_view($id),'stable repeated public-only reads');
 $GLOBALS['meta'][$id]['_ba_public_lat']=0;check(BadAround_Public_Geo_Projection::public_view($id)===null&&is_wp_error($svc->publish($id)),'tampered projection fails closed without reprojection');
}
foreach(['missing_decision','approx_upgrade','invalid_mode','invalid_policy','missing_coords','missing_request','missing_confirmation','safety_veto','unauthorized','audit_failure','commit_failure','private_text'] as $case){
 $id=510;g5seed($id);$before=$GLOBALS['private_fixture'];
 switch($case){
 case 'missing_decision':unset($GLOBALS['meta'][$id]['_ba_public_location_decision']);break;
 case 'approx_upgrade':$l=json_decode($GLOBALS['private_fixture']['content_original'],true);$l['canonical']['location']['public_location_request']='approximate_area';$GLOBALS['private_fixture']['content_original']=json_encode($l);break;
 case 'invalid_mode':$GLOBALS['meta'][$id]['_ba_public_location_decision']['mode']='point';break;
 case 'invalid_policy':$GLOBALS['meta'][$id]['_ba_public_location_decision']['policy_version']='v999';break;
 case 'missing_coords':$GLOBALS['private_fixture']['exact_lat']=null;break;
 case 'missing_request':$l=json_decode($GLOBALS['private_fixture']['content_original'],true);unset($l['canonical']['location']['public_location_request'],$l['canonical']['location']['public_location_request_version']);$GLOBALS['private_fixture']['content_original']=json_encode($l);break;
 case 'missing_confirmation':$GLOBALS['meta'][$id]['_ba_public_location_decision']['exact_confirmed']=false;break;
 case 'safety_veto':$veto=static fn($allowed)=>false;add_filter('badaround_public_geo_exact_allowed',$veto,999);break;
 case 'unauthorized':$GLOBALS['denied']=['publish_ba_eventi'];break;
 case 'audit_failure':$GLOBALS['audit_fail']=true;break;
 case 'commit_failure':$GLOBALS['commit_fail']=true;break;
 case 'private_text':get_post($id)->post_content='Private Address 99';break;
 }
 $r=$svc->publish($id);check(is_wp_error($r)&&g5none($id)&&get_post_status($id)==='pending','fail closed and atomic rollback '.$case);
 $GLOBALS['audit_fail']=false;$GLOBALS['commit_fail']=false;$GLOBALS['denied']=[];if(isset($veto)){remove_filter('badaround_public_geo_exact_allowed',$veto,999);unset($veto);}
}
foreach(['territory_only','municipality'] as $mode){g5seed(520,'exact_point',$mode);check($svc->publish(520)===true&&''===get_post_meta(520,'_ba_public_lat')&&null===BadAround_Public_Geo_Projection::public_view(520),'coordinate-free '.$mode.' with no centroid');}
g5seed(521,'approximate_area','approximate_area');check(BadAround_Publication_Service::guard_geo_write(null,521,'_ba_public_lat',41.8991622)===false,'moderation/intake/REST cannot write versioned mirrors');check(BadAround_Publication_Service::guard_geo_write(null,521,'_ba_public_geo',[])===false,'only B3 owns versioned projection writes');check(update_post_meta(521,'_ba_public_lat',41.8991622)===false&&g5none(521),'registered metadata hook blocks actual unauthorized write');
foreach(['f32-c4'=>150,'f32-c5'=>300,'f32-c6'=>1000,'f32-c7'=>3000,'unknown'=>750] as $precision=>$radius){$l=['exact_lat'=>89.9999,'exact_lng'=>179.9999];$d=['mode'=>'approximate_area'];$p=BadAround_Public_Geo_Projection::build(530,$l,$d,$precision);check($p['radius_meters']===$radius&&BadAround_Public_Geo_Projection::pair($p['lat'],$p['lng']),'versioned radius and valid spherical jitter '.$precision);}
unset($GLOBALS['private_fixture']);seed(540);check($svc->prepare_public_projection(540)===true&&$svc->validate_public_projection(540)===true,'legacy territory-only unchanged');
$discovery=file_get_contents(dirname(__DIR__).'/wordpress/plugins/badaround-core/includes/class-badaround-discovery-query.php');$segment=explode('private function public_geo( $event_id ) {',$discovery)[1];$segment=explode('private function deepest_term',$segment)[0];check(!str_contains($segment,'exact_lat')&&!str_contains($segment,'ba_reports'),'Discovery never reads private coordinates');
echo "GEO_PUBLIC_05_PUBLICATION=PASS\n";
