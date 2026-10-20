<?php
/** Real service/admin/audit classes, isolated transactional WordPress fixture. */
define('ABSPATH',__DIR__.'/');
function __($x){return $x;} function esc_html__($x){return esc_html($x);}
function esc_html($x){return htmlspecialchars((string)$x,ENT_QUOTES,'UTF-8');}
function esc_attr($x){return esc_html($x);} function esc_url($x){return esc_html($x);}
function absint($x){return abs((int)$x);} function sanitize_key($x){return preg_replace('/[^a-z0-9_-]/','',strtolower($x));}
function get_current_user_id(){return $GLOBALS['actor'];}
function current_user_can($cap,...$args){return !in_array($cap,$GLOBALS['denied'],true);}
function wp_json_encode($x,$flags=0){return json_encode($x,$flags);}
function is_wp_error($x){return $x instanceof WP_Error;}
function current_time($format,$gmt=false){return '2026-10-20 16:00:00';}
function wp_generate_uuid4(){static $i=0;return sprintf('aaaaaaaa-aaaa-4aaa-8aaa-%012d',++$i);}
function wp_salt(){return 'synthetic-only';} function wp_unslash($x){return $x;}
function get_post($id){return isset($GLOBALS['posts'][$id])?(object)$GLOBALS['posts'][$id]:null;}
function get_post_status($id){return $GLOBALS['posts'][$id]['post_status']??'';}
function get_post_meta($id,$key,$single=true){return $GLOBALS['meta'][$id][$key]??'';}
function update_post_meta($id,$key,$value){
 if($GLOBALS['meta_fail'])return false;
 if($key!=='_ba_public_location_decision')throw new Exception('Unexpected write target');
 $GLOBALS['meta'][$id][$key]=$value;return 1;
}
function wp_cache_delete($id,$group){$GLOBALS['cache_deletes']++;return true;}
function wp_verify_nonce($nonce,$action){return $nonce==='nonce:'.$action;}
function wp_nonce_field($action,$name='_wpnonce'){echo '<input type="hidden" name="'.esc_attr($name).'" value="nonce:'.esc_attr($action).'">';}
function get_current_screen(){return (object)['post_type'=>'ba_evento'];}
function admin_url($path=''){return 'https://staging.badaround.it/wp-admin/'.$path;}
function selected($a,$b,$echo=true){$s=$a===$b?'selected="selected"':'';if($echo)echo $s;return $s;}
class WP_Error{private $code;function __construct($code,$message=''){$this->code=$code;}function get_error_code(){return $this->code;}}
class BadAround_Event_Post_Type{const POST_TYPE='ba_evento';const TERRITORY_TAX='ba_territorio';const EVENT_TYPE_TAX='ba_tipo_evento';}
class Geo04_DB {
 public $prefix='wp_',$posts='wp_posts',$postmeta='wp_postmeta',$last_error='',$engine_count=3,$audit_fail=false,$commit_fail=false,$queries=[],$snapshot=null;
 function prepare($sql,...$args){foreach($args as $arg){$v=is_int($arg)?(string)$arg:"'".str_replace("'","''",$arg)."'";$sql=preg_replace('/%[ds]/',$v,$sql,1);}return $sql;}
 function get_var($sql){$this->queries[]=$sql;if(str_contains($sql,'information_schema'))return $this->engine_count;
  if(str_contains($sql,'JSON_CONTAINS_PATH')){preg_match('/event_id = (\d+)/',$sql,$m);return (int)($GLOBALS['reports'][(int)$m[1]]['has_request']??0);}return 0;
 }
 function get_row($sql){$this->queries[]=$sql;
  if(str_contains($sql,'FROM wp_posts')){preg_match('/ID = (\d+)/',$sql,$m);return get_post((int)$m[1]);}
  preg_match('/event_id = (\d+)/',$sql,$m);$r=$GLOBALS['reports'][(int)($m[1]??0)]??null;
  if($r&&preg_match('/AND id = (\d+)/',$sql,$p)&&(int)$p[1]!==$r['id'])return null;
  return $r?(object)$r:null;
 }
 function query($sql){$this->queries[]=$sql;
  if($sql==='START TRANSACTION')$this->snapshot=[$GLOBALS['meta'],$GLOBALS['audit']];
  if($sql==='ROLLBACK'&&$this->snapshot!==null){[$GLOBALS['meta'],$GLOBALS['audit']]=$this->snapshot;$this->snapshot=null;}
  if($sql==='COMMIT'){if($this->commit_fail)return false;$this->snapshot=null;}return 1;
 }
 function insert($table,$row,$formats){if($this->audit_fail){$this->last_error='synthetic failure';return false;}$GLOBALS['audit'][]=$row;return 1;}
}
$GLOBALS['actor']=42;$GLOBALS['denied']=[];$GLOBALS['posts']=[];$GLOBALS['meta']=[];$GLOBALS['reports']=[];$GLOBALS['audit']=[];
$GLOBALS['meta_fail']=false;$GLOBALS['cache_deletes']=0;$wpdb=new Geo04_DB();
$base=dirname(__DIR__).'/wordpress/plugins/badaround-core/includes/';
require $base.'class-badaround-audit-log.php';
require $base.'class-badaround-moderation-service.php';
require $base.'class-badaround-publication-service.php';
require $base.'class-badaround-moderation-admin.php';
function gassert($ok,$message){if(!$ok){fwrite(STDERR,'FAIL: '.$message."\n");exit(1);}echo 'PASS: '.$message."\n";}
function gerror($r,$suffix){return is_wp_error($r)&&$r->get_error_code()==='ba_geo_decision_'.$suffix;}
function gseed($id,$request='exact_point',$status='pending',$moderation='new'){
 $GLOBALS['posts'][$id]=['ID'=>$id,'post_type'=>'ba_evento','post_status'=>$status,'post_title'=>'Synthetic geo moderation','post_content'=>'Synthetic content'];
 $GLOBALS['meta'][$id]=['_ba_moderation_status'=>$moderation,'_ba_primary_report_id'=>$id+1000];
 $GLOBALS['reports'][$id]=['id'=>$id+1000,'source_type'=>'native','valid_json'=>1,'has_request'=>$request===null?0:1,'request_mode'=>$request,'request_version'=>'public-location-request-v1','private_fingerprint'=>'synthetic-private-and-social-unchanged'];
}
function ginput($id,$mode,$reason='',$confirm=false){return ['mode'=>$mode,'reason_code'=>$reason,'exact_confirmed'=>$confirm,'revision'=>BadAround_Geo_Moderation_Service::revision(get_post_meta($id,'_ba_public_location_decision'))];}
function ghtml($id){$a=new BadAround_Moderation_Admin();ob_start();$a->render_geo_decision_box((object)['ID'=>$id]);return ob_get_clean();}
$svc=new BadAround_Geo_Moderation_Service();$admin=new BadAround_Moderation_Admin();
if(in_array('--html',$argv,true)||in_array('--html-approx',$argv,true)){
 $request=in_array('--html-approx',$argv,true)?'approximate_area':'exact_point';gseed(900,$request);$_GET['post']=900;
 echo '<!doctype html><html lang="it"><meta charset="utf-8"><body><form id="post"><input name="public_title" required value="Synthetic">'.ghtml(900).'</form>';
 $admin->render_independent_rejection_form();$admin->render_geo_decision_form();echo '</body></html>';exit;
}
foreach(['exact_point'=>'Punto esatto','approximate_area'=>'Area approssimativa'] as $request=>$label){
 gseed(900,$request);$html=ghtml(900);gassert(str_contains($html,'Richiesta utente: '.$label),'request display '.$request);
 gassert(str_contains($html,'Da valutare — seleziona una decisione'),'no automatic decision');
 gassert(!str_contains($html,'selected="selected"'),'new decision never preselected');
 gassert(!str_contains($html,'<form'),'metabox contains no nested form');
 if($request==='approximate_area')gassert(!str_contains($html,'<option value="exact_point"')&&!str_contains($html,'<option value="street"'),'approximate UI cannot increase precision');
}
gseed(901,null);$html=ghtml(901);gassert(str_contains($html,'criterio legacy')&&!str_contains($html,'<option value="exact_point"'),'legacy UI conservative');
foreach(['ba_moderate_events','ba_view_private_reports','edit_post'] as $cap){$GLOBALS['denied']=[$cap];gassert(gerror($svc->save(901,ginput(901,'municipality','privacy')),'forbidden'),'capability enforced '.$cap);}
$GLOBALS['denied']=[];
gseed(902);foreach([['GET','nonce:ba_save_geo_decision_902'],['POST','invalid'],['POST','nonce:ba_save_geo_decision_903']] as [$method,$nonce]){
 gassert(gerror($admin->process_geo_decision_request(902,ginput(902,'exact_point','',true),$nonce,$method),'forbidden'),'POST and event nonce required');
}
$matrix=['exact_point'=>['exact_point','approximate_area','street','municipality','territory_only'],'approximate_area'=>['approximate_area','municipality','territory_only']];$id=910;
foreach($matrix as $request=>$modes)foreach($modes as $mode){
 $id++;gseed($id,$request);$private=$GLOBALS['reports'][$id];$posts=$GLOBALS['posts'];$count=count($GLOBALS['audit']);
 $r=$admin->process_geo_decision_request($id,ginput($id,$mode,$request===$mode?'':'privacy',$mode==='exact_point'),'nonce:ba_save_geo_decision_'.$id,'POST');
 gassert($r==='saved','valid decision '.$request.' -> '.$mode);
 $d=get_post_meta($id,'_ba_public_location_decision');
 gassert($d['mode']===$mode&&$d['decided_by']===42&&$d['decided_at']==='2026-10-20T16:00:00Z'&&$d['policy_version']==='public-location/v1','mode actor UTC timestamp policy stored');
 gassert($GLOBALS['posts']===$posts&&$GLOBALS['reports'][$id]===$private&&get_post_meta($id,'_ba_moderation_status')==='new','decision preserves state private report and Social');
 gassert(get_post_meta($id,'_ba_public_lat')===''&&get_post_meta($id,'_ba_public_lng')===''&&get_post_meta($id,'_ba_public_geo')==='','no public projection or coordinates');
 gassert(count($GLOBALS['audit'])===$count+1,'audit entry committed');
 $a=end($GLOBALS['audit']);$v=json_decode($a['new_value'],true);
 gassert($v['request']===$request&&$v['precision_reduced']===($request!==$mode)&&$a['actor_user_id']===42,'audit records request reduction and authorized actor');
 gassert(!preg_match('/exact_lat|exact_lng|exact_address|private_fingerprint/',json_encode($a)),'audit contains no private location');
}
foreach(['exact_point'=>['approximate_area','street','municipality','territory_only'],'approximate_area'=>['municipality','territory_only']] as $request=>$modes)foreach($modes as $mode){
 gseed(940,$request);$before=serialize([$GLOBALS['meta'],$GLOBALS['audit']]);gassert(gerror($svc->save(940,ginput(940,$mode)),'reason_required'),'reduction reason mandatory '.$request.' -> '.$mode);
 gassert($before===serialize([$GLOBALS['meta'],$GLOBALS['audit']]),'invalid reduction writes nothing');
}
foreach(['exact_point','street','legacy_generalized_point','approximate_area!',''] as $invalid){gseed(941,'approximate_area');gassert(gerror($svc->save(941,ginput(941,$invalid,'privacy',true)),'precision_forbidden'),'backend rejects upgrade/invalid mode '.$invalid);}
gseed(942);gassert(gerror($svc->save(942,ginput(942,'exact_point')),'exact_confirmation_required'),'exact requires explicit confirmation');
gseed(943,null);gassert(gerror($svc->save(943,ginput(943,'exact_point','',true)),'precision_forbidden'),'legacy cannot gain exact permission');
gassert($svc->save(943,ginput(943,'legacy_generalized_point'))==='saved','legacy generalized decision supported');
gseed(944);$GLOBALS['reports'][944]['request_version']='future-v9';gassert(gerror($svc->save(944,ginput(944,'exact_point','',true)),'request_invalid'),'unknown version fails closed');
gseed(945);$GLOBALS['meta'][945]['_ba_primary_report_id']=999999;gassert(gerror($svc->save(945,ginput(945,'exact_point','',true)),'report_unavailable'),'mismatched primary report rejected');
foreach([['publish','published'],['pending','published'],['publish','approved'],['trash','new']] as [$status,$mod]){gseed(946,'exact_point',$status,$mod);$before=serialize([$GLOBALS['meta'],$GLOBALS['audit']]);gassert(gerror($svc->save(946,ginput(946,'exact_point','',true)),'read_only'),'published/incompatible states read-only');gassert($before===serialize([$GLOBALS['meta'],$GLOBALS['audit']]),'read-only state untouched');gassert(!str_contains(ghtml(946),'Salva decisione geografica'),'read-only UI has no save button');}
gseed(950);$stale=ginput(950,'approximate_area','privacy');gassert($svc->save(950,ginput(950,'exact_point','',true))==='saved','first explicit exact decision saved');
gassert(gerror($svc->save(950,$stale),'stale'),'stale editor cannot overwrite');
$before=get_post_meta(950,'_ba_public_location_decision');$count=count($GLOBALS['audit']);gassert($svc->save(950,ginput(950,'exact_point','',true))==='unchanged'&&count($GLOBALS['audit'])===$count,'identical decision causes no extra audit');
gassert($svc->save(950,ginput(950,'approximate_area','private_home'))==='saved','decision can be revised before publication');
$a=end($GLOBALS['audit']);$old=json_decode($a['old_value'],true);$new=json_decode($a['new_value'],true);
gassert($old['mode']==='exact_point'&&$new['decision']==='approximate_area'&&$new['reason_code']==='private_home','audit retains prior and new decision with reason');
gassert(str_contains($admin->geo_audit_summary((object)$a),'Punto esatto → Area approssimativa'),'audit UI readable transition');
$GLOBALS['actor']=84;gassert($svc->save(950,ginput(950,'approximate_area','private_home'))==='saved'&&get_post_meta(950,'_ba_public_location_decision')['decided_by']===84,'another operator reassessment audited');$GLOBALS['actor']=42;
foreach(['audit','meta','commit'] as $failure){gseed(960);$before=serialize([$GLOBALS['meta'],$GLOBALS['audit']]);$wpdb->audit_fail=$failure==='audit';$GLOBALS['meta_fail']=$failure==='meta';$wpdb->commit_fail=$failure==='commit';$r=$svc->save(960,ginput(960,'exact_point','',true));gassert(is_wp_error($r)&&$before===serialize([$GLOBALS['meta'],$GLOBALS['audit']]),'atomic rollback on '.$failure.' failure');$wpdb->audit_fail=false;$GLOBALS['meta_fail']=false;$wpdb->commit_fail=false;$wpdb->last_error='';}
$wpdb->engine_count=2;gseed(961);gassert(gerror($svc->save(961,ginput(961,'exact_point','',true)),'transaction_unavailable'),'nontransactional storage fails closed without schema mutation');$wpdb->engine_count=3;
$pub=new BadAround_Publication_Service();gassert(is_wp_error($pub->prepare_public_projection(950)),'saved decision cannot enable Publication Service');
gassert(!str_contains(implode('\n',$wpdb->queries),'SELECT exact_lat'),'decision service never retrieves exact private coordinates');
echo "GEO_PUBLIC_04_MODERATION=PASS\n";
