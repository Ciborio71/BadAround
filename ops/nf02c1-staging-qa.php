<?php
if(!defined('ABSPATH'))exit;
if(function_exists('opcache_invalidate')) opcache_invalidate(WP_CONTENT_DIR.'/plugins/badaround-core/includes/class-badaround-report-repository.php',true);

add_action('rest_api_init',function(){
 register_rest_route('nf02c1-qa/v1','/ready',['methods'=>'GET','permission_callback'=>function(){return current_user_can('manage_options');},'callback'=>function(){return new WP_REST_Response(['ready'=>true,'candidate'=>'38c82e3472fc4adb0ab80f8fd1281373aa1ab1e0'],200);}]);
 register_rest_route('nf02c1-qa/v1','/run',[
  'methods'=>'POST',
  'permission_callback'=>function($request){return ($_SERVER['HTTP_HOST']??'')==='staging.badaround.it' && current_user_can('manage_options');},
  'callback'=>function(){
   if(!add_option('nf02c1_qa_once_'.hash('sha256','__TOKEN__'),time(),'',false))return new WP_REST_Response(['pass'=>false,'error'=>'Harness already executed'],409);
   global $wpdb,$results,$fixtures;
   ob_start();
$results=[]; $fixtures=[];
function nf02c1_ck($v,$name) { global $results; $results[$name]=(bool)$v; if(!$v) throw new RuntimeException($name); }
function nf02c1_fixture() {
	return array(
		'schema_version'=>'badaround-report/v1',
		'submission_id'=>'123e4567-e89b-42d3-a456-426614174000',
		'event'=>array('category'=>'hazard','subtype'=>'hazard_road_obstruction'),
		'reporter'=>array(
			'relationship'=>'territory_observer','first_name'=>'QA','last_name'=>'NF02C1',
			'email'=>'MARIO@example.invalid','phone'=>'',
			'public_identity_mode'=>'anonymous','contact_preference'=>'none'
		),
		'location'=>array(
			'exact_address'=>'Via Roma 10, Pomezia','exact_lat'=>41.669,'exact_lng'=>12.501,
			'place_id'=>'place-test','area_label'=>'Pomezia',
			'region'=>'Lazio','province'=>'Roma','municipality'=>'Pomezia','locality'=>'Torvaianica',
			'public_precision'=>'area',
			'place_type'=>'road_sidewalk','immediate_danger'=>'no'
		),
		'time'=>array('mode'=>'exact','date'=>'2026-10-01','knowledge'=>'unknown'),
		'content'=>array('description'=>'Ostacolo presente sulla carreggiata.'),
		'damage'=>array('status'=>'no'),
		'authority'=>array('status'=>'no'),
		'media'=>array('availability'=>'no','items'=>array()),
		'reward'=>array('status'=>'none'),
		'consents'=>array(
			'truthfulness'=>true,'media_rights'=>true,'publication_rules'=>true,
			'terms'=>true,'privacy'=>true,'version'=>'native-v1'
		),
	);
}
function nf02c1_code($r) { return is_wp_error($r)?$r->get_error_code():($r['error']['code']??'success'); }
function nf02c1_snapshot() {
 global $wpdb;
 $protected=[];foreach([35,36,37,38,39] as $id) { $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ba_reports WHERE id=%d",$id),ARRAY_A);$protected[$id]=['row_hash'=>hash('sha256',wp_json_encode($row)),'content_hash'=>hash('sha256',$row['content_original']),'event_id'=>(int)$row['event_id'],'event_status'=>get_post_status($row['event_id'])]; }
 $schema=[];$tables=$wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($wpdb->prefix.'ba_').'%'));sort($tables);foreach($tables as $table){$schema[$table]=preg_replace('/AUTO_INCREMENT=[0-9]+/','AUTO_INCREMENT=COUNTER',wp_json_encode($wpdb->get_results('SHOW CREATE TABLE `'.str_replace('`','``',$table).'`',ARRAY_N)));}
 return ['fixture'=>$protected[35]['content_hash'],'event312'=>$protected[35]['event_status'],'protected'=>$protected,'terms'=>hash('sha256',wp_json_encode($wpdb->get_results("SELECT t.term_id,t.name,t.slug,tt.taxonomy,tt.parent,tt.description FROM {$wpdb->terms} t JOIN {$wpdb->term_taxonomy} tt ON tt.term_id=t.term_id ORDER BY t.term_id,tt.taxonomy",ARRAY_A))),'schema'=>hash('sha256',wp_json_encode($schema)),'media_sessions'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ba_native_media_sessions"),'media_items'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ba_native_media_items")];
}
function nf02c1_row($id) { global $wpdb;return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ba_reports WHERE id=%d",$id),ARRAY_A); }
class NF02C1UncertainRepository extends BadAround_Report_Repository {
 public $uncertain=true;
 public function persist_canonical_private_data($id,$canonical,$payload=null) { $ok=parent::persist_canonical_private_data($id,$canonical,$payload);if($ok && $this->uncertain){$this->uncertain=false;return false;}return $ok; }
}
class NF02C1EventPause extends BadAround_Report_Persistence_Service {
 public $pause=true;
 public function persist($id,$canonical,$source=[],$options=[]) { if($this->pause){$this->pause=false;return new WP_Error('nf02c1_controlled_event_pause');}return parent::persist($id,$canonical,$source,$options); }
}

try {
 nf02c1_ck(hash_file('sha256',WP_PLUGIN_DIR.'/badaround-core/includes/class-badaround-report-repository.php')==='75064b889a61b35b507ec326b343aa5c48ba22d90c3c051b72eb828042892c3b','approved_runtime_hash');
 $before=nf02c1_snapshot(); nf02c1_ck($before['fixture']==='ba4672ec075306b23eb2b6f9c1f68f15676d8c613d8884d87e1aa01db9a72197' && $before['event312']==='pending','historical_fixture_before');
 nf02c1_ck(BadAround_Report_Schema::VERSION==='badaround-report/v1','native_version_stays_v1');
 $repo=new BadAround_Report_Repository(); $gold=new BadAround_Native_Report_Golden_Path_Service();
 foreach(['badaround-report/v1','badaround-report/v2'] as $version) {
  $p=nf02c1_fixture(); $p['schema_version']=$version; $p['submission_id']=wp_generate_uuid4();
  $p['reporter']['email']='nf02c1-qa@example.invalid'; $p['content']['description']='NF-UX-02C.1 synthetic backend staging QA — do not publish.';
  $p['location']['public_location_request']='approximate_area'; $p['location']['public_location_request_version']=BadAround_Report_Schema::PUBLIC_LOCATION_REQUEST_VERSION;
  $p['reporter']['social_visibility']='declined'; $p['consents']['social_visibility_version']=BadAround_Report_Schema::SOCIAL_PREFERENCE_VERSION;
  $canonical=BadAround_Report_Version_Registry::prepare($p);nf02c1_ck(!is_wp_error($canonical),$version.'_canonical_valid');
  $idem=new BadAround_Report_Idempotency_Service($repo);$reservation=$idem->reserve($p['submission_id'],$canonical);nf02c1_ck(!is_wp_error($reservation)&&$reservation['report_id']>0,$version.'_reservation');$rid=$reservation['report_id'];
  $fixtures[]=['schema_version'=>$version,'submission_id'=>$p['submission_id'],'report_id'=>$rid,'event_id'=>0,'case'=>'direct_immutability'];$fixture_index=count($fixtures)-1;
  try {
   $reserved=nf02c1_row($rid);$opposite=$p;$opposite['schema_version']=$version==='badaround-report/v1'?'badaround-report/v2':'badaround-report/v1';$opposite=BadAround_Report_Version_Registry::prepare($opposite);nf02c1_ck(!is_wp_error($opposite),'independently_valid_opposite_'.$version);
   nf02c1_ck($repo->persist_canonical_private_data($rid,$opposite)===false && nf02c1_row($rid)===$reserved,$version.'_reserved_version_rejected');
   if($version==='badaround-report/v2'){$changed=$canonical;$changed['content']['description'].=' active replacement';nf02c1_ck($repo->persist_canonical_private_data($rid,$changed)===false && nf02c1_row($rid)===$reserved,'v2_reserved_active_rejected');}
   nf02c1_ck($repo->persist_canonical_private_data($rid,$canonical)===true,$version.'_first_persist');
   $first=nf02c1_row($rid);if($version==='badaround-report/v2')nf02c1_ck($first['content_original']===$reserved['content_original']&&!empty($first['author_name']),'v2_first_materialization_preserves_reserved_bytes');
   nf02c1_ck($repo->persist_canonical_private_data($rid,$canonical)===true,$version.'_direct_identical_retry');$stable=nf02c1_row($rid);
   $changed=$canonical;$changed['content']['description'].=' active replacement';nf02c1_ck($repo->persist_canonical_private_data($rid,$changed)===false,$version.'_direct_active_rejected');nf02c1_ck(nf02c1_row($rid)===$stable,$version.'_entire_row_unchanged_after_active');
   nf02c1_ck($repo->persist_canonical_private_data($rid,$opposite)===false,$version.'_direct_cross_version_rejected');nf02c1_ck(nf02c1_row($rid)===$stable,$version.'_entire_row_unchanged_after_version');
   $override=json_decode($stable['content_original'],true);$override['canonical']['content']['description']='Forged override';nf02c1_ck($repo->persist_canonical_private_data($rid,$canonical,$override)===false&&nf02c1_row($rid)===$stable,$version.'_envelope_override_rejected');
  }finally{$idem->release($p['submission_id']);}
  $r=$gold->submit($p); nf02c1_ck(nf02c1_code($r)==='success'&&$r['report_id']===$rid,$version.'_golden_path_'.nf02c1_code($r));
  $fixtures[$fixture_index]['event_id']=$r['event_id'];

  nf02c1_ck(get_post_status($r['event_id'])==='pending',$version.'_pending');
  $again=$gold->submit($p); nf02c1_ck(nf02c1_code($again)==='success' && $again['duplicate'] && $again['report_id']===$r['report_id'] && $again['event_id']===$r['event_id'],$version.'_retry');
  $changed=$p; $changed['content']['description'].=' changed'; nf02c1_ck(nf02c1_code($gold->submit($changed))==='duplicate_submission',$version.'_active_conflict');
  $cross=$p; $cross['schema_version']=$version==='badaround-report/v1'?'badaround-report/v2':'badaround-report/v1'; nf02c1_ck(nf02c1_code($gold->submit($cross))==='duplicate_submission',$version.'_cross_conflict');
  $raw=$wpdb->get_var($wpdb->prepare("SELECT content_original FROM {$wpdb->prefix}ba_reports WHERE id=%d",$r['report_id'])); $stored=json_decode($raw,true);
  nf02c1_ck($stored['schema_version']===$version && $stored['canonical']['schema_version']===$version,$version.'_persistence');
  nf02c1_ck($stored['canonical']['location']['public_location_request']==='approximate_area' && $stored['canonical']['location']['exact_lat']===41.669,$version.'_geo');
  nf02c1_ck($stored['canonical']['reporter']['social_visibility']==='declined',$version.'_social');
  nf02c1_ck(empty($stored['canonical']['media']['items']) && true===BadAround_Native_Media_Fence::check($r['event_id']),$version.'_no_media_fence');
  $view=(new BadAround_Moderation_Admin())->canonical_report_view($stored); nf02c1_ck(is_array($view),$version.'_admin_read');
  if($version==='badaround-report/v2') { $stale=$p; $stale['vehicle']=['type'=>'car']; nf02c1_ck(nf02c1_code($gold->submit($stale))==='success','v2_stale_retry'); $bad=$p; $bad['unknown']=true; nf02c1_ck(nf02c1_code($gold->submit($bad))==='unknown_field','v2_unknown_rejected'); }
 }
 // Focused recovery: caller observes false after canonical SQL has committed.
 foreach(['badaround-report/v1','badaround-report/v2'] as $version) {
  $p=nf02c1_fixture();$p['schema_version']=$version;$p['submission_id']=wp_generate_uuid4();$p['reporter']['email']='nf02c1-qa@example.invalid';$p['content']['description']='NF-UX-02C.1 synthetic uncertain outcome and event recovery QA — do not publish.';
  $ur=new NF02C1UncertainRepository();$ps=$version==='badaround-report/v2'?new NF02C1EventPause($ur):new BadAround_Report_Persistence_Service($ur);$ug=new BadAround_Native_Report_Golden_Path_Service(new BadAround_Native_Report_Intake_Service(null,null,$ur),$ps,$ur);
  $failed=$ug->submit($p);$rid=$ur->find_by_native_submission($p['submission_id']);nf02c1_ck(nf02c1_code($failed)!=='success'&&$rid>0&&!$ur->event_id_for_report($rid),$version.'_uncertain_first_outcome');$committed=nf02c1_row($rid)['content_original'];nf02c1_ck(is_array(json_decode($committed,true)['canonical']??null),$version.'_uncertain_write_actually_committed');
  $fixtures[]=['schema_version'=>$version,'submission_id'=>$p['submission_id'],'report_id'=>$rid,'event_id'=>0,'case'=>'uncertain_write_recovery'];$idx=count($fixtures)-1;
  if($version==='badaround-report/v2'){$paused=$ug->submit($p);nf02c1_ck(nf02c1_code($paused)!=='success'&&!$ur->event_id_for_report($rid),'v2_report_exists_event_absent');nf02c1_ck(nf02c1_row($rid)['content_original']===$committed,'v2_event_pause_keeps_snapshot');}
  $done=$ug->submit($p);nf02c1_ck(nf02c1_code($done)==='success'&&$done['report_id']===$rid,$version.'_uncertain_recovery_same_report');$fixtures[$idx]['event_id']=$done['event_id'];$again=$ug->submit($p);nf02c1_ck(nf02c1_code($again)==='success'&&$again['event_id']===$done['event_id'],$version.'_recovery_stable_event');nf02c1_ck(get_post_status($done['event_id'])==='pending'&&nf02c1_row($rid)['content_original']===$committed,$version.'_recovery_pending_same_snapshot');
 }
 foreach(['badaround-report/v1','badaround-report/v2'] as $version)foreach(['exact_point','approximate_area','territory_only'] as $mode){$p=nf02c1_fixture();$p['schema_version']=$version;if($mode==='territory_only'){unset($p['location']['exact_lat'],$p['location']['exact_lng']);$p['time']=['mode'=>'unknown'];}else{$p['location']['public_location_request']=$mode;$p['location']['public_location_request_version']=BadAround_Report_Schema::PUBLIC_LOCATION_REQUEST_VERSION;}$n=BadAround_Report_Version_Registry::prepare($p);nf02c1_ck(!is_wp_error($n),$version.'_geo_'.$mode);if($mode!=='territory_only')nf02c1_ck($n['location']['exact_lat']===41.669&&$n['location']['public_location_request']===$mode,$version.'_geo_preserved_'.$mode);}
 // Exercise all frozen subtype oracles against the deployed validator without persisting them.
 $contract=json_decode(file_get_contents(WP_PLUGIN_DIR.'/badaround-core/contracts/nf-conditional-v1.0.json'),true);
 foreach($contract['regression_oracle'] as $oracle) {
  $o=['schema_version'=>'badaround-report/v2','submission_id'=>wp_generate_uuid4(),'consents'=>['version'=>'native-v1']];
  foreach($oracle['minimal_user_values'] as $path=>$value) { $parts=explode('.',$path); $ptr=&$o; foreach($parts as $part) { if(!isset($ptr[$part]))$ptr[$part]=[]; $ptr=&$ptr[$part]; } $ptr=$value;unset($ptr); }
  nf02c1_ck(!is_wp_error(BadAround_Report_Version_Registry::prepare($o)),'oracle_'.$oracle['subtype']);
  if($oracle['subtype']==='animal_missing') { $o['damage']=['status'=>'not_applicable'];$n=BadAround_Report_Version_Registry::prepare($o);nf02c1_ck(!is_wp_error($n) && !isset($n['damage']),'lost_animal_damage_hidden'); }
 }
 // Read-only media adapter admission: invalid capability cannot create/pin/bind anything.
 $service=new BadAround_Native_Media_Service();
 foreach(['badaround-report/v1','badaround-report/v2'] as $v) { $p=nf02c1_fixture();$p['schema_version']=$v;$p['submission_id']=wp_generate_uuid4(); $p['media']=['availability'=>'yes','items'=>[['media_id'=>wp_generate_uuid4(),'mime_type'=>'image/png','file_size'=>100,'extension'=>'png']]];
  $r=(new BadAround_Native_Media_Report_Adapter())->submit($p,'invalid'); nf02c1_ck(nf02c1_code($r)!=='success' && !$repo->find_by_native_submission($p['submission_id']),$v.'_media_admission_fails_closed');
 }
 nf02c1_ck($service->ready()===false,'media_remains_not_ready');
 $historical=json_decode($wpdb->get_var("SELECT content_original FROM {$wpdb->prefix}ba_reports WHERE id=35"),true); nf02c1_ck(is_array((new BadAround_Moderation_Admin())->canonical_report_view($historical)),'historical_admin_read');
 foreach($fixtures as $f){$count=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} po ON po.ID=pm.post_id WHERE pm.meta_key='_ba_primary_report_id' AND pm.meta_value=%s AND po.post_type='ba_evento'",(string)$f['report_id']));nf02c1_ck($count===1 && get_post_status($f['event_id'])==='pending','single_pending_event_report_'.$f['report_id']);}
 $after=nf02c1_snapshot(); nf02c1_ck($before===$after,'historical_taxonomy_schema_unchanged');
 echo wp_json_encode(['pass'=>true,'checks'=>$results,'fixtures'=>$fixtures,'integrity'=>$after,'media_service_ready'=>$service->ready()]);
} catch(Throwable $e) {  echo wp_json_encode(['pass'=>false,'error'=>$e->getMessage(),'checks'=>$results,'fixtures'=>$fixtures]); }

   $raw=ob_get_clean();$result=json_decode($raw,true);
   if(!is_array($result))$result=['pass'=>false,'error'=>'non-JSON harness output'];
   $result_path=__DIR__.'/__RESULT_FILE__';if(false===file_put_contents($result_path,wp_json_encode($result),LOCK_EX))return new WP_REST_Response(['pass'=>false,'error'=>'Cannot preserve QA result'],500);chmod($result_path,0600);
   delete_option('nf02c1_qa_once_'.hash('sha256','__TOKEN__'));
   return new WP_REST_Response($result,!empty($result['pass'])?200:500);
  }
 ]);
});
