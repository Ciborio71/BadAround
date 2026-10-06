<?php
define('ABSPATH',__DIR__.'/');

function __($x){return $x;} function absint($v){return abs((int)$v);}
function sanitize_key($v){return strtolower(preg_replace('/[^a-z0-9_\-]/i','',(string)$v));}
function sanitize_text_field($v){return trim(strip_tags((string)$v));}
function is_wp_error($v){return $v instanceof WP_Error;} function current_time($t,$g=false){return '2026-10-06 12:00:00';}
function wp_generate_uuid4(){return 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';}

class WP_Error{private $c; function __construct($c,$m=''){ $this->c=$c;} function get_error_code(){return $this->c;}}
class WP_Term{public $term_id,$parent,$slug; function __construct($id,$parent,$slug){$this->term_id=$id;$this->parent=$parent;$this->slug=$slug;}}

class BadAround_Event_Post_Type{const POST_TYPE='ba_evento';const EVENT_TYPE_TAX='ba_tipo_evento';const TERRITORY_TAX='ba_territorio';}
class BadAround_Audit_Log{public static $r=[]; static function record(){self::$r[]=func_get_args();return true;} static function technical_error(){self::$r[]=func_get_args();return true;}}

$GLOBALS['f14_posts']=[];$GLOBALS['f14_terms']=[];$GLOBALS['f14_deleted']=[];$GLOBALS['f14_fail_insert']=false;
function wp_insert_post($a,$err=false){ if($GLOBALS['f14_fail_insert']) return new WP_Error('insert_failed'); $id=900+count($GLOBALS['f14_posts']);$a['ID']=$id;$GLOBALS['f14_posts'][$id]=$a;return $id;}
function wp_delete_post($id,$force=false){$GLOBALS['f14_deleted'][]=$id;unset($GLOBALS['f14_posts'][$id]);return true;}
function wp_set_object_terms($id,$terms,$tax,$append=false){$GLOBALS['f14_terms'][$id][$tax]=$terms;return $terms;}
function get_posts($args){$ids=[];foreach($GLOBALS['f14_posts'] as $id=>$p){if(isset($p['meta_input']['_ba_primary_report_id']) && (int)$p['meta_input']['_ba_primary_report_id']===(int)$args['meta_query'][0]['value'])$ids[]=$id;}return $ids;}

class BadAround_Report_Schema{public static function category_subtypes(){return ['hazard'=>['hazard_road_obstruction']];}}
class F14Repo{
 public $event=0,$linked=0,$status='validated',$locked=false;
 function event_id_for_report($r){return $this->event;}
 function link_event($r,$e){$this->event=$e;$this->linked++;return true;}
 function acquire_native_lock($s,$t=0){if($this->locked)return false;$this->locked=true;return true;}
 function release_native_lock($s){$this->locked=false;}
 function mark_intake_status($r,$s){$this->status=$s;return true;}
}
class F14Tax{
 public $fail=false; public $legacy=false;
 function assign($id,$r){if($this->fail)return new WP_Error('tax_fail');$GLOBALS['f14_terms'][$id]['ba_tipo_evento']=[13,45];return ['category_term_id'=>13,'subtype_term_id'=>45];}
}
class F14Territory{public $id=4;function resolve($a,$b){return $this->id;}}
class F14Media{
 public $linked=0,$unlinked=0,$private=true;
 function link_validated_private_media($r,$e,$items){$this->linked++;return [501];}
 function unlink_event($r,$e){$this->unlinked++;return true;}
}
class F14Pub{public $calls=0;function prepare_public_projection($e){$this->calls++;return true;}}

require_once dirname(__DIR__).'/wordpress/plugins/badaround-core/includes/class-badaround-report-persistence-service.php';
require_once dirname(__DIR__).'/wordpress/plugins/badaround-core/includes/class-badaround-native-report-golden-path-service.php';

function f14_assert($ok,$m){if(!$ok){fwrite(STDERR,"FAIL: $m\n");exit(1);}echo "PASS: $m\n";}
function report(){return [
 'submission_id'=>'123e4567-e89b-42d3-a456-426614174000',
 'event'=>['category'=>'hazard','subtype'=>'hazard_road_obstruction'],
 'location'=>['area_label'=>'Pomezia','exact_address'=>'Via Roma 10','exact_lat'=>41.1,'exact_lng'=>12.2,'public_precision'=>'area'],
 'time'=>['mode'=>'exact','date'=>'2026-10-01'],
 'vehicle'=>['plate_raw'=>'AB123CD'],
 'media'=>['items'=>[['media_id'=>501,'mime_type'=>'image/jpeg','file_size'=>1000,'extension'=>'jpg']]],
 'reward'=>['status'=>'none']
];}

$repo=new F14Repo();$tax=new F14Tax();$territory=new F14Territory();$media=new F14Media();$pub=new F14Pub();
$svc=new BadAround_Report_Persistence_Service($repo,$tax,$territory,$media,$pub);
$out=$svc->persist(77,report(),['type'=>'native','submission_id'=>report()['submission_id']],['prepare_public_projection'=>false]);
f14_assert(!is_wp_error($out) && $out['event_id']>0,'valid native report creates one event');
$id=$out['event_id'];
f14_assert('pending'===$GLOBALS['f14_posts'][$id]['post_status'],'initial event status is pending/Da moderare');
f14_assert(isset($GLOBALS['f14_terms'][$id]['ba_tipo_evento']) && $GLOBALS['f14_terms'][$id]['ba_tipo_evento']===[13,45],'canonical category/subtype assigned');
f14_assert(isset($GLOBALS['f14_terms'][$id]['ba_territorio']),'canonical territory assigned');
$meta=$GLOBALS['f14_posts'][$id]['meta_input'];
f14_assert(!isset($meta['exact_lat'])&&!isset($meta['exact_lng'])&&!isset($meta['full_plate'])&&!isset($meta['author_email']),'private fields are not copied to event meta');
f14_assert(false===strpos(json_encode($meta),'AB123CD'),'full plate is not public event meta');
f14_assert(false===strpos(json_encode($meta),'41.1')&&false===strpos(json_encode($meta),'12.2'),'precise coordinates are not public event meta');
f14_assert(1===$media->linked && true===$media->private,'original media remains private and linked');
f14_assert(0===$pub->calls,'native persistence does not prepare or publish public projection');
f14_assert(1===$repo->linked && $repo->event===$id,'report to event link is stable');

$again=$svc->persist(77,report(),['type'=>'native','submission_id'=>report()['submission_id']],[]);
f14_assert($again['duplicate'] && $again['event_id']===$id,'retry after success creates zero duplicate events');
f14_assert(1===count($GLOBALS['f14_posts']),'duplicate submission leaves exactly one event');

$repo2=new F14Repo();$tax2=new F14Tax();$tax2->fail=true;$media2=new F14Media();
$failSvc=new BadAround_Report_Persistence_Service($repo2,$tax2,new F14Territory(),$media2,new F14Pub());
$failed=$failSvc->persist(88,report(),['type'=>'native','submission_id'=>report()['submission_id']],[]);
f14_assert(is_wp_error($failed) && !empty($GLOBALS['f14_deleted']),'taxonomy failure compensates by deleting partial event');
f14_assert(0===$repo2->event,'partial failure does not link report to event');

$GLOBALS['f14_fail_insert']=true;
$repo3=new F14Repo();$insertFail=(new BadAround_Report_Persistence_Service($repo3,new F14Tax(),new F14Territory(),new F14Media(),new F14Pub()))->persist(99,report(),['type'=>'native'],[]);
$GLOBALS['f14_fail_insert']=false;
f14_assert(is_wp_error($insertFail)&&0===$repo3->event,'event creation failure leaves report retryable');

class F14Intake{public $result;function __construct($r){$this->result=$r;}function intake($p){return $this->result;}}
$repo4=new F14Repo();$intakeResult=['status'=>'success','submission_id'=>report()['submission_id'],'report_id'=>111,'normalized_payload'=>report()];
$p4=new BadAround_Report_Persistence_Service($repo4,new F14Tax(),new F14Territory(),new F14Media(),new F14Pub());
$gold=new BadAround_Native_Report_Golden_Path_Service(new F14Intake($intakeResult),$p4,$repo4);
$g1=$gold->submit(report());
f14_assert(isset($g1['event_id'])&&$g1['event_id']>0,'native golden path links validated intake to event');
$g2=$gold->submit(report());
f14_assert($g2['event_id']===$g1['event_id'],'golden path retry is idempotent');

$busy=new F14Repo();$busy->locked=true;
$busyGold=new BadAround_Native_Report_Golden_Path_Service(new F14Intake($intakeResult),new BadAround_Report_Persistence_Service($busy,new F14Tax(),new F14Territory(),new F14Media(),new F14Pub()),$busy);
$b=$busyGold->submit(report());
f14_assert('error'===$b['status']&&'submission_in_progress'===$b['error']['code'],'concurrent persistence request is rejected before event creation');

$source=file_get_contents(dirname(__DIR__).'/wordpress/plugins/badaround-core/includes/class-badaround-report-persistence-service.php');
f14_assert(false===strpos($source,'wp_insert_term'),'persistence service cannot create taxonomy terms');
f14_assert(false===stripos($source,'wpforms6:'),'persistence service contains no legacy WPForms taxonomy identity');
f14_assert(false===strpos($source,"'post_status'  => 'publish'"),'persistence service cannot auto-publish');
f14_assert(!class_exists('BadAround_WPForms_Report_Adapter',false),'F1.4 native tests require no WPForms runtime');

$actions=array_map(function($x){return isset($x[2])?$x[2]:'';},BadAround_Audit_Log::$r);
foreach(['native_report_persist_started','native_report_created','event_creation_started','event_created','taxonomy_assigned','persistence_completed'] as $a){f14_assert(in_array($a,$actions,true),'audit contains '.$a);}

echo "F1.4_DUPLICATE_REPORTS=0\nF1.4_DUPLICATE_EVENTS=0\nF1.4_AUTO_PUBLISH=0\nF1.4 Native Persistence tests complete.\n";
