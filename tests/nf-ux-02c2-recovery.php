<?php
require __DIR__.'/native-media-harness.php';
require_once $inc.'class-badaround-report-v2-validator.php';
$checks=0;
function rc($ok,$message){global $checks;++$checks;if(!$ok)throw new RuntimeException($message);}
function rp($version){$p=f13_fixture();$p['schema_version']=$version;$p['submission_id']=wp_generate_uuid4();return $p;}
function rgold($repo,$tax=null,$service=null){return new BadAround_Native_Report_Golden_Path_Service(new BadAround_Native_Report_Intake_Service(null,null,$repo),$service?:new BadAround_Report_Persistence_Service($repo,$tax?:new TestTax(),new TestTerritory(),new BadAround_Media_Repository(),new TestProjection()),$repo);}
function rrow($id){global $db;return $db->get_row($db->prepare('SELECT * FROM test_ba_reports WHERE id=%d',$id),ARRAY_A);}
function rsource($r){return $r['error']['details']['source_code']??'';}
function rcount($repo,$p,$rid,$event){global $db;$identity=$repo->native_source_identity($p['submission_id']);rc((int)$db->get_var($db->prepare("SELECT COUNT(*) FROM test_ba_reports WHERE source_type='native' AND source_form_id=%d AND source_entry_id=%d",$identity['form_id'],$identity['entry_id']))===1,'one UUID/report');rc(get_posts(['meta_value'=>$rid])===[$event],'one stable event');}
function rcomplete($rid,$event){$types=wp_get_object_terms($event,'ba_tipo_evento');sort($types);rc($types===[13,45],'category and subtype complete');rc(wp_get_object_terms($event,'ba_territorio')===[5],'deepest canonical territory only');rc(get_post_status($event)==='pending'&&get_post_meta($event,'_ba_moderation_status',true)==='new','pending/new only');rc((int)get_post_meta($event,'_ba_primary_report_id',true)===$rid,'event owner stable');}
function rinterrupt($callback){try{$callback();throw new LogicException('Fault not reached');}catch(RuntimeException $e){rc($e->getMessage()==='NF02C2_INTERRUPT','controlled interruption reached');}finally{unset($GLOBALS['test_before_insert'],$GLOBALS['test_after_insert'],$GLOBALS['test_after_terms']);}}
class RLinkRepo extends BadAround_Report_Repository {public $stage='';public function link_event($rid,$eid){if($this->stage==='before'){$this->stage='';throw new RuntimeException('NF02C2_INTERRUPT');}$r=parent::link_event($rid,$eid);if($this->stage==='after'){$this->stage='';throw new RuntimeException('NF02C2_INTERRUPT');}return $r;}}
foreach(['badaround-report/v1','badaround-report/v2'] as $version){
 foreach(['before_insert','after_insert','after_subtype','after_territory','before_link','after_link','response_loss'] as $stage){
  $p=rp($version);$repo=new RLinkRepo();$gold=rgold($repo);$before=count($GLOBALS['posts']);
  if($stage==='response_loss'){$discarded=$gold->submit($p);rc(($discarded['status']??'')==='success','first receipt intentionally discarded');}
  else{
   if($stage==='before_insert')$GLOBALS['test_before_insert']=static function(){throw new RuntimeException('NF02C2_INTERRUPT');};
   if($stage==='after_insert')$GLOBALS['test_after_insert']=static function(){throw new RuntimeException('NF02C2_INTERRUPT');};
   if(in_array($stage,['after_subtype','after_territory']))$GLOBALS['test_after_terms']=static function($e,$t)use($stage){if($t===($stage==='after_subtype'?'ba_tipo_evento':'ba_territorio'))throw new RuntimeException('NF02C2_INTERRUPT');};
   if($stage==='before_link')$repo->stage='before';if($stage==='after_link')$repo->stage='after';
   rinterrupt(fn()=>$gold->submit($p));
  }
  $rid=$repo->find_by_native_submission($p['submission_id']);rc($rid>0,'report persisted before event attempt');$snapshot=rrow($rid)['content_original'];$events=get_posts(['meta_value'=>$rid]);rc(count($events)===($stage==='before_insert'?0:1),'interruption exact event count');$original=$events[0]??null;
  $r=$gold->submit($p);rc(($r['status']??'')==='success',$version.' recovery '.$stage);$event=$r['event_id'];rc($original===null||$event===$original,'original event ID recovered');rc(count($GLOBALS['posts'])===$before+1,'no orphan duplicate');rcomplete($rid,$event);rc(rrow($rid)['content_original']===$snapshot,'snapshot bytes unchanged');rc((int)$repo->event_id_for_report($rid)===$event,'link verified');rcount($repo,$p,$rid,$event);
  $terms=$GLOBALS['test_term_writes']??0;$meta=$GLOBALS['test_meta_writes']??0;$post=$GLOBALS['posts'][$event];$again=$gold->submit($p);rc(($again['event_id']??0)===$event,'completed retry stable identity');rc($post===$GLOBALS['posts'][$event]&&$terms===($GLOBALS['test_term_writes']??0)&&$meta===($GLOBALS['test_meta_writes']??0),'completed retry no term/meta rewrites');
 }
 // Explicit partial states, including a territory-first state not produced by normal ordering.
 foreach(['empty','subtype_only','territory_only','both','category_only','missing_meta','linked_empty','linked_subtype_only','linked_complete'] as $state){
  $p=rp($version);$repo=new BadAround_Report_Repository();$gold=rgold($repo);$GLOBALS['test_after_insert']=static function(){throw new RuntimeException('NF02C2_INTERRUPT');};rinterrupt(fn()=>$gold->submit($p));$rid=$repo->find_by_native_submission($p['submission_id']);$event=get_posts(['meta_value'=>$rid])[0];$snapshot=rrow($rid)['content_original'];
  $type=in_array($state,['subtype_only','both','linked_subtype_only','linked_complete','missing_meta'])?[13,45]:($state==='category_only'?[13]:[]);$territory=in_array($state,['territory_only','both','linked_complete','missing_meta'])?[5]:[];
  $GLOBALS['posts'][$event]['terms']=['ba_tipo_evento'=>$type,'ba_territorio'=>$territory];if(str_starts_with($state,'linked_'))$repo->link_event($rid,$event);if($state==='missing_meta')unset($GLOBALS['posts'][$event]['meta_input']['_ba_source_submission_id'],$GLOBALS['posts'][$event]['meta_input']['_ba_time_precision']);
  $r=$gold->submit($p);rc(($r['status']??'')==='success',$version.' partial '.$state);rc($r['event_id']===$event,'partial identity');rcomplete($rid,$event);rcount($repo,$p,$rid,$event);rc($snapshot===rrow($rid)['content_original'],'partial canonical immutable');
 }
 // Inspect ALL domains before repairs; conflicts leave the event itself byte-stable.
 foreach(['subtype','territory','ancestor_territory','owner','metadata','post_type','unlinked_published','linked_published_new','corrupt_moderation','array_metadata'] as $conflict){
  $p=rp($version);$repo=new BadAround_Report_Repository();$gold=rgold($repo);$GLOBALS['test_after_insert']=static function(){throw new RuntimeException('NF02C2_INTERRUPT');};rinterrupt(fn()=>$gold->submit($p));$rid=$repo->find_by_native_submission($p['submission_id']);$event=get_posts(['meta_value'=>$rid])[0];
  if($conflict==='subtype')$GLOBALS['posts'][$event]['terms']['ba_tipo_evento']=[13,999];
  if($conflict==='territory')$GLOBALS['posts'][$event]['terms']['ba_territorio']=[999];
  if($conflict==='ancestor_territory')$GLOBALS['posts'][$event]['terms']['ba_territorio']=[4];
  if($conflict==='owner'){$repo->link_event($rid,$event);$GLOBALS['posts'][$event]['meta_input']['_ba_primary_report_id']=$rid+999;}
  if($conflict==='array_metadata')$GLOBALS['posts'][$event]['meta_input']['_ba_time_precision']=['corrupted'];
  if($conflict==='metadata')$GLOBALS['posts'][$event]['meta_input']['_ba_time_precision']='corrupted';
  if($conflict==='post_type'){$repo->link_event($rid,$event);$GLOBALS['posts'][$event]['post_type']='post';}
  if($conflict==='unlinked_published')$GLOBALS['posts'][$event]['post_status']='publish';
  if($conflict==='linked_published_new'){$repo->link_event($rid,$event);$GLOBALS['posts'][$event]['post_status']='publish';}
  if($conflict==='corrupt_moderation'){$repo->link_event($rid,$event);$GLOBALS['posts'][$event]['meta_input']['_ba_moderation_status']='corrupted';}
  $before=$GLOBALS['posts'][$event];$snapshot=rrow($rid)['content_original'];$n=count($GLOBALS['posts']);$r=$gold->submit($p);rc(($r['status']??'')==='error'&&str_starts_with(rsource($r),'ba_persistence_event_completion_'),'deterministic conflict '.$conflict);rc($before===$GLOBALS['posts'][$event]&&count($GLOBALS['posts'])===$n,'no conflict coercion/delete/duplicate');rc($snapshot===rrow($rid)['content_original'],'conflict canonical stable');
 }
}
// A missing mapping and ambiguous candidates must fail instead of creating another event.
class RMissingMap extends TestTax {function resolve_subtype($k,$c=null){return null;}}
$p=rp('badaround-report/v2');$repo=new BadAround_Report_Repository();$gold=rgold($repo);$GLOBALS['test_after_insert']=static function(){throw new RuntimeException('NF02C2_INTERRUPT');};rinterrupt(fn()=>$gold->submit($p));$rid=$repo->find_by_native_submission($p['submission_id']);$event=get_posts(['meta_value'=>$rid])[0];$before=$GLOBALS['posts'][$event];$r=rgold($repo,new RMissingMap())->submit($p);rc(rsource($r)==='ba_persistence_event_completion_mapping'&&$before===$GLOBALS['posts'][$event],'missing canonical term no mutation');
$duplicate=wp_insert_post($before);$n=count($GLOBALS['posts']);$r=$gold->submit($p);rc(rsource($r)==='ba_persistence_event_completion_ambiguous_event'&&count($GLOBALS['posts'])===$n,'ambiguous candidates no third event');
// A direct recovery caller cannot initialize from a canonical snapshot differing from storage.
$p=rp('badaround-report/v2');$repo=new BadAround_Report_Repository();$gold=rgold($repo);$done=$gold->submit($p);$rid=$done['report_id'];$event=$done['event_id'];$canonical=BadAround_Report_Version_Registry::prepare($p);$changed=$canonical;$changed['content']['description']='Changed private canonical';$svc=new BadAround_Report_Persistence_Service($repo,new TestTax(),new TestTerritory(),new BadAround_Media_Repository(),new TestProjection());$before=$GLOBALS['posts'][$event];$result=$svc->persist($rid,$changed,['type'=>'native','submission_id'=>$p['submission_id']],['territory_term_id'=>5]);rc(is_wp_error($result)&&$result->get_error_code()==='ba_persistence_event_completion_canonical'&&$before===$GLOBALS['posts'][$event],'direct recovery canonical mismatch rejected');
// Established moderation/publication remains outside initialization repair.
$GLOBALS['posts'][$event]['post_status']='publish';$GLOBALS['posts'][$event]['meta_input']['_ba_moderation_status']='published';$GLOBALS['posts'][$event]['meta_input']['_ba_event_status']='resolved';$before=$GLOBALS['posts'][$event];$result=$gold->submit($p);rc(($result['status']??'')==='success'&&$before===$GLOBALS['posts'][$event],'historical completed publication retry unchanged, no reset');
// Compare the entire normal Golden Path outcome against exact pre-C.2 service bytes.
$baseline='38c82e3472fc4adb0ab80f8fd1281373aa1ab1e0';$pipes=[];$proc=proc_open(['git','show',$baseline.':wordpress/plugins/badaround-core/includes/class-badaround-report-persistence-service.php'],[1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__));$code=stream_get_contents($pipes[1]);fclose($pipes[1]);fclose($pipes[2]);rc(proc_close($proc)===0,'exact baseline available');eval(substr(str_replace('BadAround_Report_Persistence_Service','RBaselinePersistence',$code),5));
function rnormal($p,$old){global $db;$db->query('DELETE FROM test_ba_reports');$db->query('DELETE FROM test_options');$GLOBALS['posts']=[];$repo=new BadAround_Report_Repository();$service=$old?new RBaselinePersistence($repo,new TestTax(),new TestTerritory(),new BadAround_Media_Repository(),new TestProjection()):null;$gold=rgold($repo,null,$service);$r=$gold->submit($p);rc(($r['status']??'')==='success','normal Golden Path succeeds');$again=$gold->submit($p);rc($again['event_id']===$r['event_id'],'normal retry stable');$post=$GLOBALS['posts'][$r['event_id']];$post['meta_input']['_ba_primary_report_id']='report-id';$post['post_title']='generated-report-title';$row=rrow($r['report_id']);return [$post,$row['content_original'],$row['status'],$r['persistence'],$again['persistence']];}
foreach(['badaround-report/v1','badaround-report/v2'] as $v){$p=rp($v);rc(rnormal($p,true)===rnormal($p,false),'normal Golden Path differential '.$v);}
echo "NF_UX_02C2_RECOVERY=PASS assertions={$checks} versions=2 crash_boundaries=7 partial_states=9 conflict_states=10 differential_versions=2\n";
rmdir($root);rmdir($public);
