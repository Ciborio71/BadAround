<?php
/* F1.14R targeted contract checks using the existing MAP-02R WordPress test harness.
 * Do not access staging or mutate real fixtures.
 */
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
class BadAround_Moderation_Service {const STATUS_APPROVED='approved';}
class BadAround_Audit_Log {public static function record(){return true;}}
class DB {public $prefix='wp_',$postmeta='wp_postmeta';function prepare($sql){return $sql;}function get_row($q){return (object)array_merge(['author_name'=>'','author_surname'=>'','author_email'=>'','author_phone'=>'','exact_address'=>'Private Address 99','exact_lat'=>null,'exact_lng'=>null,'full_plate'=>'','content_original'=>''],$GLOBALS['private_fixture']??[]);}function get_var($q){return 0;}function get_results($q){return [];}}
$wpdb=new DB();
$GLOBALS['terms']=[1=>new WP_Term(1,0,'Lazio'),2=>new WP_Term(2,1,'Roma'),3=>new WP_Term(3,2,'Pomezia'),4=>new WP_Term(4,3,'Torvaianica'),5=>new WP_Term(5,0,'Standalone'),10=>new WP_Term(10,0,'Veicoli','ba_tipo_evento'),11=>new WP_Term(11,10,'Furto','ba_tipo_evento')];
$GLOBALS['meta']=[];$GLOBALS['posts']=[];$GLOBALS['object_terms']=[];$GLOBALS['term_meta']=[];
function get_term($id,$tax=''){ $v=$GLOBALS['terms'][$id]??null;return $v&&(!$tax||$v->taxonomy===$tax)?$v:null;}
function get_ancestors($id,$tax,$type='taxonomy'){ $out=[];while(($t=get_term($id,$tax))&&$t->parent){$out[]=$t->parent;$id=$t->parent;}return $out;}
function get_term_meta($id,$key,$single=true){return $GLOBALS['term_meta'][$id][$key]??'';}
function get_post($id){return $GLOBALS['posts'][$id]??null;}
function get_post_meta($id,$key,$single=true){return $GLOBALS['meta'][$id][$key]??'';}
function update_post_meta($id,$key,$val){$GLOBALS['meta'][$id][$key]=$val;return true;}
function delete_post_meta($id,$key){unset($GLOBALS['meta'][$id][$key]);return true;}
function wp_get_post_terms($id,$tax){return array_map(fn($i)=>get_term($i,$tax),$GLOBALS['object_terms'][$id][$tax]??[]);}
function wp_set_object_terms($id,$ids,$tax,$append=false){$GLOBALS['object_terms'][$id][$tax]=$ids;return $ids;}
function wp_update_post($x,$error=false){foreach($x as $k=>$v){if($k!=='ID')$GLOBALS['posts'][$x['ID']]->$k=$v;}return $x['ID'];}
function current_user_can(){return true;} function get_post_status($id){return get_post($id)->post_status;}
function add_filter(){}function remove_filter(){}function do_action(){}
function wp_salt($v='auth'){return 'test';}
function current_time($v,$gmt=false){return '2026-10-09 12:00:00';}
require_once dirname(__DIR__).'/wordpress/plugins/badaround-core/includes/class-badaround-publication-service.php';
function check($ok,$msg){if(!$ok){fwrite(STDERR,"FAIL $msg\n");exit(1);}echo "PASS $msg\n";}
function seed($id,$territory=4,$mode='f16-c8',$date='',$coords=[]){
 $GLOBALS['posts'][$id]=(object)['ID'=>$id,'post_type'=>'ba_evento','post_status'=>'pending','post_title'=>'Segnalazione da moderare #17','post_content'=>''];
 $GLOBALS['object_terms'][$id]=['ba_territorio'=>$territory?[$territory]:[],'ba_tipo_evento'=>[11]];
 $GLOBALS['meta'][$id]=array_merge(['_ba_moderation_status'=>'approved','_ba_time_precision'=>$mode,'_ba_occurred_date'=>$date,'_ba_public_place_name'=>$territory?get_term($territory)->name:'','_ba_public_location_precision'=>'f32-c4'],$coords);
}
$svc=new BadAround_Publication_Service();
seed(101);check(true===$svc->prepare_public_projection(101),'territory-only preparation');check(true===$svc->validate_public_projection(101),'unknown time and territory-only validate');check(''===get_post_meta(101,'_ba_public_lat'),'no fabricated latitude');check(!str_contains(get_post(101)->post_content,'Private Address'),'private address absent');
seed(102,4,'f16-c4','2026-10-08');check(true===$svc->prepare_public_projection(102)&&true===$svc->validate_public_projection(102),'known date and territory-only');
seed(103,0);check(is_wp_error($svc->validate_public_projection(103)),'missing territory fails');
seed(104,5);check(is_wp_error($svc->validate_public_projection(104)),'incomplete canonical chain fails');
seed(105,4,'f16-c4','2026-10-08',['_ba_public_lat'=>41.6,'_ba_public_lng'=>12.5,'_ba_public_radius_m'=>150]);check(true===$svc->prepare_public_projection(105)&&true===$svc->validate_public_projection(105),'valid existing public coordinates pass');
seed(106,4,'f16-c8','',['_ba_public_place_name'=>'Private Address 99']);check(is_wp_error($svc->validate_public_projection(106)),'exact private address in projection fails');
seed(107,4,'f16-c8','',['_ba_public_lat'=>41.6,'_ba_public_lng'=>12.5,'_ba_public_radius_m'=>150]);check(true===$svc->prepare_public_projection(107)&&true===$svc->validate_public_projection(107),'valid coordinates and unknown time pass');
seed(108,4,'f16-c8','',['_ba_public_lat'=>41.6]);check(is_wp_error($svc->validate_public_projection(108)),'partial coordinates fail');
seed(109,4,'corrupt_mode');check(is_wp_error($svc->validate_public_projection(109)),'malformed time fails closed');
seed(110,4,'f16-c4');check(is_wp_error($svc->validate_public_projection(110)),'exact time without date fails closed');
function error_is($result,$expected,$name) {
 $actual=is_wp_error($result)?$result->get_error_code():'NOT_ERROR';
 check($actual===$expected,$name.' expected='.$expected.' actual='.$actual);
}
seed(111,0);get_post(111)->post_title='Segnalazione pubblicabile';get_post(111)->post_content='Descrizione pubblicabile';
error_is($svc->validate_public_projection(111),'ba_publication_territory_missing','A missing canonical territory');
seed(112,0);get_post(112)->post_title='Segnalazione pubblicabile';get_post(112)->post_content='Descrizione pubblicabile';$GLOBALS['meta'][112]['_ba_public_place_name']='Private Address 99';
error_is($svc->validate_public_projection(112),'ba_publication_territory_missing','B private address alone is not territory');
seed(113);check(true===$svc->prepare_public_projection(113),'C malformed time prepared');$GLOBALS['meta'][113]['_ba_time_precision']='corrupt_mode';
error_is($svc->validate_public_projection(113),'ba_publication_time_invalid','C malformed time rejected');
seed(114);check(true===$svc->prepare_public_projection(114),'C missing time prepared');$GLOBALS['meta'][114]['_ba_time_precision']='';
error_is($svc->validate_public_projection(114),'ba_publication_date_missing','C absent time provenance rejected');
$GLOBALS['private_fixture']=['exact_lat'=>41.6,'exact_lng'=>12.5];
seed(115,4,'f16-c8','',['_ba_public_lat'=>41.6,'_ba_public_lng'=>12.5,'_ba_public_radius_m'=>150]);
check(true===$svc->prepare_public_projection(115),'D exact-coordinate scenario prepared');
error_is($svc->validate_public_projection(115),'ba_publication_exact_coordinates_detected','D exact coordinates leak rejected');
unset($GLOBALS['private_fixture']);
$GLOBALS['private_fixture']=['full_plate'=>'AB123CD'];
seed(116);check(true===$svc->prepare_public_projection(116),'E full-plate scenario prepared');get_post(116)->post_content='Plate AB123CD exposed';$GLOBALS['meta'][116]['_ba_vehicle_plate_masked']='AB***CD';
error_is($svc->validate_public_projection(116),'ba_publication_plate_not_masked','E full plate leak rejected');
unset($GLOBALS['private_fixture']);
$GLOBALS['private_fixture']=['author_email'=>'private-reporter@example.invalid'];
seed(117);check(true===$svc->prepare_public_projection(117),'F PII scenario prepared');get_post(117)->post_content='Email private-reporter@example.invalid leaked';
error_is($svc->validate_public_projection(117),'ba_publication_private_data_detected','F reporter PII leak rejected');
unset($GLOBALS['private_fixture']);
seed(118,5);get_post(118)->post_title='Segnalazione pubblicabile';get_post(118)->post_content='Descrizione pubblicabile';
error_is($svc->validate_public_projection(118),'ba_publication_territory_missing','G invalid territory-only hierarchy rejected');
echo "F1_14R_TARGETED_CONTRACT=PASS\n";
