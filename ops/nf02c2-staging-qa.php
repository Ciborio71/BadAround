<?php
if(!defined('ABSPATH'))exit;
if(function_exists('opcache_invalidate'))opcache_invalidate(WP_PLUGIN_DIR.'/badaround-core/includes/class-badaround-report-persistence-service.php',true);
add_action('rest_api_init',function(){
 $permission=static function(){return ($_SERVER['HTTP_HOST']??'')==='staging.badaround.it'&&current_user_can('manage_options');};
 register_rest_route('nf02c2-qa/v1','/ready',['methods'=>'GET','permission_callback'=>$permission,'callback'=>static function(){return ['ready'=>true,'candidate'=>'0d1db0d7fab7f2facbe6b07ccff002b9e884cade'];}]);
 register_rest_route('nf02c2-qa/v1','/run',['methods'=>'POST','permission_callback'=>$permission,'callback'=>static function(){
  $result_path=__DIR__.'/__RESULT_FILE__';if(is_file($result_path)){$previous=json_decode(file_get_contents($result_path),true);return new WP_REST_Response($previous,!empty($previous['pass'])?200:500);}
  $option='nf02c2_qa_once_'.hash('sha256','__TOKEN__');if(!add_option($option,time(),'',false))return new WP_REST_Response(['pass'=>false,'error'=>'QA already running'],409);
  @set_time_limit(120);global $wpdb,$nf2_checks,$nf2_fixtures;$nf2_checks=[];$nf2_fixtures=[];
  ob_start();
function nf02c2_ck($ok,$name){global $nf2_checks;$nf2_checks[$name]=(bool)$ok;if(!$ok)throw new RuntimeException($name);}
function nf02c2_code($r){return is_wp_error($r)?$r->get_error_code():($r['error']['code']??'success');}
function nf02c2_row($id){global $wpdb;return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ba_reports WHERE id=%d",$id),ARRAY_A);}
function nf02c2_hash($v){return hash('sha256',wp_json_encode($v));}
function nf02c2_snapshot(){
 global $wpdb;$protected=[];
 foreach(range(35,43) as $id){$r=nf02c2_row($id);$eid=(int)$r['event_id'];$protected[$id]=['row_hash'=>nf02c2_hash($r),'content_hash'=>hash('sha256',$r['content_original']),'event_id'=>$eid,'event_status'=>get_post_status($eid),'event_hash'=>nf02c2_hash($wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->posts} WHERE ID=%d",$eid),ARRAY_A)),'meta_hash'=>nf02c2_hash($wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->postmeta} WHERE post_id=%d ORDER BY meta_id",$eid),ARRAY_A)),'relations_hash'=>nf02c2_hash($wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->term_relationships} WHERE object_id=%d ORDER BY term_taxonomy_id",$eid),ARRAY_A))];}
 $schema=[];$tables=$wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($wpdb->prefix.'ba_').'%'));sort($tables);foreach($tables as $t){$schema[$t]=preg_replace('/AUTO_INCREMENT=[0-9]+/','AUTO_INCREMENT=COUNTER',wp_json_encode($wpdb->get_results('SHOW CREATE TABLE `'.str_replace('`','``',$t).'`',ARRAY_N)));}
 return ['protected'=>$protected,'schema'=>nf02c2_hash($schema),'terms'=>nf02c2_hash($wpdb->get_results("SELECT t.term_id,t.name,t.slug,tt.taxonomy,tt.parent,tt.description FROM {$wpdb->terms} t JOIN {$wpdb->term_taxonomy} tt ON tt.term_id=t.term_id ORDER BY t.term_id,tt.taxonomy",ARRAY_A)),'media_sessions'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ba_native_media_sessions"),'media_items'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ba_native_media_items")];
}
function nf02c2_fixture() {
	return array(
		'schema_version'=>'badaround-report/v1',
		'submission_id'=>'123e4567-e89b-42d3-a456-426614174000',
		'event'=>array('category'=>'hazard','subtype'=>'hazard_road_obstruction'),
		'reporter'=>array(
			'relationship'=>'territory_observer','first_name'=>'QA','last_name'=>'NF02C2',
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

function nf02c2_gold($repo,$tax=null){return new BadAround_Native_Report_Golden_Path_Service(new BadAround_Native_Report_Intake_Service(null,null,$repo),new BadAround_Report_Persistence_Service($repo,$tax?:new BadAround_Event_Taxonomy_Map()),$repo);}
function nf02c2_event_state($id){global $wpdb;return nf02c2_hash(['post'=>$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->posts} WHERE ID=%d",$id),ARRAY_A),'meta'=>$wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->postmeta} WHERE post_id=%d ORDER BY meta_id",$id),ARRAY_A),'terms'=>$wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->term_relationships} WHERE object_id=%d ORDER BY term_taxonomy_id",$id),ARRAY_A)]);}
class NF02C2LinkPause extends BadAround_Report_Repository {public $pause=true;public function link_event($rid,$eid){if($this->pause){$this->pause=false;throw new RuntimeException('NF02C2_TEST_INTERRUPT');}return parent::link_event($rid,$eid);}}
class NF02C2FaultTax extends BadAround_Event_Taxonomy_Map {
 public $scenario,$repo,$territory,$fired=false,$calls=0;
 public function __construct($s,$r,$t){$this->scenario=$s;$this->repo=$r;$this->territory=$t;}
 public function assign($event,$report){
  ++$this->calls;if($this->fired)return parent::assign($event,$report);$this->fired=true;
  $rid=(int)get_post_meta($event,'_ba_primary_report_id',true);nf02c2_ck($rid>43&&str_starts_with($report['content']['description'],'NF-UX-02C.2 synthetic'),'fault_only_new_synthetic_'.$rid);
  if($this->scenario==='subtype_only'){$r=parent::assign($event,$report);if(is_wp_error($r))return $r;}
  elseif($this->scenario==='territory_only'){$r=wp_set_object_terms($event,[$this->territory],BadAround_Event_Post_Type::TERRITORY_TAX,false);if(is_wp_error($r))return $r;}
  elseif($this->scenario==='linked_empty'){nf02c2_ck((bool)$this->repo->link_event($rid,$event),'controlled_link_before_tax_'.$rid);}
  elseif($this->scenario==='conflict'){$c=$this->resolve_category($report['event']['category']);$wrong=$this->resolve_subtype('hazard_fire_or_smoke',$c);nf02c2_ck($c&&$wrong,'existing_conflict_terms_'.$rid);$r=wp_set_object_terms($event,[(int)$c->term_id,(int)$wrong->term_id],BadAround_Event_Post_Type::EVENT_TYPE_TAX,false);if(is_wp_error($r))return $r;}
  throw new RuntimeException('NF02C2_TEST_INTERRUPT');
 }
}
try{
 nf02c2_ck(hash_file('sha256',WP_PLUGIN_DIR.'/badaround-core/includes/class-badaround-report-persistence-service.php')==='fa2a5129ab3e852c1eca6052cda3bdbce7a47f8cd209f939f2421de22205c820','candidate_runtime_hash');
 nf02c2_ck(hash_file('sha256',WP_PLUGIN_DIR.'/badaround-core/includes/class-badaround-report-repository.php')==='75064b889a61b35b507ec326b343aa5c48ba22d90c3c051b72eb828042892c3b','c1_repository_hash');
 $before=nf02c2_snapshot();
 $known=[35=>'ba4672ec075306b23eb2b6f9c1f68f15676d8c613d8884d87e1aa01db9a72197',36=>'174ca8086de49d2b56331f122bf85a0e0710a617fb9704a9950932840577140e',37=>'b9402a51ba76f79225b4efcd2ec58d2d22979f9f6b18324585ecc355ced8a048',38=>'c658cfc2b5ecf3bfd14c52d055b73a56dc7263d64e1f758b16c2ea701b38c5ae',39=>'e518683b80e4f3461eff0c711723842e5c6cb52af7a5e5da981d4c6d9b99ab1a',40=>'616fc2aa4760e9a72c9a95013ff484ba12798f8e12d3b784a35a59ed8f734914',41=>'d317ca66b002bcf79fe9122f60e0de4b89e1431bf7e0986b94bd1c20a56a1db6',42=>'f46e2c28d27afe0d43d947aafffd3eccf0fc6a1b6a3b00ef88fe6cd3c9973ef8',43=>'7ab7dad73ff3b67506fcd43715374e9e46207d25061eca9a376cd507d140f2f5'];
 foreach($known as $id=>$hash)nf02c2_ck(($before['protected'][$id]['content_hash']??'')===$hash&&$before['protected'][$id]['event_status']==='pending','protected_before_'.$id);
 nf02c2_ck(BadAround_Report_Schema::VERSION==='badaround-report/v1','native_schema_stays_v1');
 $map=new BadAround_Event_Taxonomy_Map();
 foreach(['badaround-report/v1','badaround-report/v2'] as $v){
  foreach(['empty','subtype_only','territory_only','both_unlinked','linked_empty','normal'] as $scenario){
   $p=nf02c2_fixture();$p['schema_version']=$v;$p['submission_id']=wp_generate_uuid4();$p['reporter']['first_name']='QA';$p['reporter']['last_name']='NF02C2';$p['reporter']['email']='nf02c2-qa@example.invalid';$p['reporter']['phone']='';$p['content']['description']='NF-UX-02C.2 synthetic staging recovery QA / '.$scenario.' / '.$v.' / DO NOT PUBLISH.';
   $p['location']['public_location_request']='approximate_area';$p['location']['public_location_request_version']=BadAround_Report_Schema::PUBLIC_LOCATION_REQUEST_VERSION;$p['reporter']['social_visibility']='declined';$p['consents']['social_visibility_version']=BadAround_Report_Schema::SOCIAL_PREFERENCE_VERSION;
   $canonical=BadAround_Report_Version_Registry::prepare($p);nf02c2_ck(!is_wp_error($canonical),$v.'_'.$scenario.'_valid');$territory=(new BadAround_Territory_Resolver())->resolve_path($canonical['location']);nf02c2_ck(!is_wp_error($territory)&&$territory>0,$v.'_'.$scenario.'_territory_resolved');
   $repo=$scenario==='both_unlinked'?new NF02C2LinkPause():new BadAround_Report_Repository();$tax=in_array($scenario,['normal','both_unlinked'])?null:new NF02C2FaultTax($scenario,$repo,$territory);$gold=nf02c2_gold($repo,$tax);
   $index=count($nf2_fixtures);$nf2_fixtures[]=['schema_version'=>$v,'submission_id'=>$p['submission_id'],'case'=>$scenario,'report_id'=>0,'event_id'=>0];$interrupted=false;
   try{$first=$gold->submit($p);}catch(RuntimeException $e){if($e->getMessage()!=='NF02C2_TEST_INTERRUPT')throw $e;$interrupted=true;}
   $rid=(int)$repo->find_by_native_submission($p['submission_id']);$nf2_fixtures[$index]['report_id']=$rid;nf02c2_ck($rid>43,$v.'_'.$scenario.'_new_report');$events=get_posts(['post_type'=>'ba_evento','post_status'=>'any','fields'=>'ids','posts_per_page'=>3,'meta_key'=>'_ba_primary_report_id','meta_value'=>$rid]);nf02c2_ck(count($events)===1,$v.'_'.$scenario.'_one_inserted_event');$eid=(int)$events[0];$nf2_fixtures[$index]['event_id']=$eid;
   nf02c2_ck($scenario==='normal'?!$interrupted&&nf02c2_code($first)==='success':$interrupted,$v.'_'.$scenario.'_expected_boundary');
   $row=nf02c2_row($rid);$snapshot=$row['content_original'];$expected_type=[(int)$map->resolve_category('hazard')->term_id,(int)$map->resolve_subtype('hazard_road_obstruction')->term_id];sort($expected_type);
   $actual_type=wp_get_object_terms($eid,BadAround_Event_Post_Type::EVENT_TYPE_TAX,['fields'=>'ids']);$actual_territory=wp_get_object_terms($eid,BadAround_Event_Post_Type::TERRITORY_TAX,['fields'=>'ids']);nf02c2_ck(!is_wp_error($actual_type)&&!is_wp_error($actual_territory),$v.'_'.$scenario.'_terms_read');
   if($scenario==='empty'||$scenario==='linked_empty')nf02c2_ck(!$actual_type&&!$actual_territory,$v.'_'.$scenario.'_initially_no_terms');
   if($scenario==='subtype_only')nf02c2_ck(count($actual_type)===2&&!$actual_territory,$v.'_'.$scenario.'_initial_subtype_only');
   if($scenario==='territory_only')nf02c2_ck(!$actual_type&&array_map('intval',$actual_territory)===[(int)$territory],$v.'_'.$scenario.'_initial_territory_only');
   if($scenario==='both_unlinked')nf02c2_ck(count($actual_type)===2&&count($actual_territory)===1&&!$repo->event_id_for_report($rid),$v.'_'.$scenario.'_initial_terms_without_link');
   if($scenario==='linked_empty')nf02c2_ck((int)$repo->event_id_for_report($rid)===$eid,$v.'_'.$scenario.'_initial_link_exists');
   if($scenario==='empty'){
    nf02c2_ck($repo->persist_canonical_private_data($rid,$canonical)===true&&nf02c2_row($rid)['content_original']===$snapshot,$v.'_c1_identical_retry');$stable=nf02c2_row($rid);$active=$canonical;$active['content']['description'].=' ACTIVE REPLACEMENT';nf02c2_ck($repo->persist_canonical_private_data($rid,$active)===false&&nf02c2_row($rid)===$stable,$v.'_c1_active_rejected_unchanged');
    $cross=$p;$cross['schema_version']=$v==='badaround-report/v1'?'badaround-report/v2':'badaround-report/v1';$cross=BadAround_Report_Version_Registry::prepare($cross);nf02c2_ck(!is_wp_error($cross),$v.'_c1_cross_independently_valid');nf02c2_ck($repo->persist_canonical_private_data($rid,$cross)===false&&nf02c2_row($rid)===$stable,$v.'_c1_cross_version_rejected_unchanged');
   }
   $done=$gold->submit($p);nf02c2_ck(nf02c2_code($done)==='success'&&(int)$done['report_id']===$rid&&(int)$done['event_id']===$eid,$v.'_'.$scenario.'_same_identity_recovery');
   $types=array_map('intval',wp_get_object_terms($eid,BadAround_Event_Post_Type::EVENT_TYPE_TAX,['fields'=>'ids']));sort($types);$territories=array_map('intval',wp_get_object_terms($eid,BadAround_Event_Post_Type::TERRITORY_TAX,['fields'=>'ids']));nf02c2_ck($types===$expected_type,$v.'_'.$scenario.'_subtype_complete');nf02c2_ck($territories===[(int)$territory],$v.'_'.$scenario.'_territory_complete');
   nf02c2_ck((int)$repo->event_id_for_report($rid)===$eid&&(int)get_post_meta($eid,'_ba_primary_report_id',true)===$rid,$v.'_'.$scenario.'_linkage_complete');nf02c2_ck(get_post_status($eid)==='pending'&&get_post_meta($eid,'_ba_moderation_status',true)==='new',$v.'_'.$scenario.'_pending_new');
   nf02c2_ck(nf02c2_row($rid)['content_original']===$snapshot,$v.'_'.$scenario.'_canonical_unchanged');$stored=json_decode($snapshot,true);nf02c2_ck($stored['schema_version']===$v&&$stored['canonical']['schema_version']===$v,$v.'_'.$scenario.'_version_storage');nf02c2_ck($stored['canonical']['location']['exact_lat']===41.669&&$stored['canonical']['location']['public_location_request']==='approximate_area',$v.'_'.$scenario.'_private_geo_preserved');nf02c2_ck($stored['canonical']['reporter']['social_visibility']==='declined',$v.'_'.$scenario.'_social_private');
   nf02c2_ck(empty($stored['canonical']['media']['items'])&&true===BadAround_Native_Media_Fence::check($eid),$v.'_'.$scenario.'_no_media_fence');nf02c2_ck(!metadata_exists('post',$eid,'_ba_public_geo')&&!metadata_exists('post',$eid,'_ba_public_lat')&&!metadata_exists('post',$eid,'_ba_public_lng'),$v.'_'.$scenario.'_no_public_projection');
   if($tax&&$scenario==='empty')nf02c2_ck($tax->calls===2,$v.'_original_gate_assignment_calls_1_to_2');
   $state=nf02c2_event_state($eid);$receipt=$gold->submit($p);nf02c2_ck(nf02c2_code($receipt)==='success'&&(int)$receipt['event_id']===$eid&&nf02c2_event_state($eid)===$state,$v.'_'.$scenario.'_response_loss_complete_retry_no_event_rewrite');
   $changed=$p;$changed['content']['description'].=' active change';nf02c2_ck(nf02c2_code($gold->submit($changed))==='duplicate_submission',$v.'_'.$scenario.'_active_conflict');
   $nf2_fixtures[$index]['content_sha256']=hash('sha256',$snapshot);$nf2_fixtures[$index]['event_state_sha256']=$state;
  }
 }
 // One dedicated, unlinked synthetic event with incompatible existing subtype.
 $p=nf02c2_fixture();$p['schema_version']='badaround-report/v2';$p['submission_id']=wp_generate_uuid4();$p['reporter']['email']='nf02c2-qa@example.invalid';$p['reporter']['phone']='';$p['content']['description']='NF-UX-02C.2 synthetic incompatible subtype QA / DO NOT PUBLISH.';$canonical=BadAround_Report_Version_Registry::prepare($p);$territory=(new BadAround_Territory_Resolver())->resolve_path($canonical['location']);$repo=new BadAround_Report_Repository();$tax=new NF02C2FaultTax('conflict',$repo,$territory);$gold=nf02c2_gold($repo,$tax);
 try{$gold->submit($p);throw new LogicException('Conflict fault not reached');}catch(RuntimeException $e){if($e->getMessage()!=='NF02C2_TEST_INTERRUPT')throw $e;}
 $rid=(int)$repo->find_by_native_submission($p['submission_id']);$events=get_posts(['post_type'=>'ba_evento','post_status'=>'any','fields'=>'ids','posts_per_page'=>3,'meta_key'=>'_ba_primary_report_id','meta_value'=>$rid]);nf02c2_ck($rid>43&&count($events)===1,'conflict_fixture_created');$eid=(int)$events[0];$nf2_fixtures[]=['schema_version'=>'badaround-report/v2','submission_id'=>$p['submission_id'],'case'=>'incompatible_subtype','report_id'=>$rid,'event_id'=>$eid];$before_conflict=nf02c2_event_state($eid);$canonical_bytes=nf02c2_row($rid)['content_original'];$bad=$gold->submit($p);nf02c2_ck(nf02c2_code($bad)==='persistence_failed'&&($bad['error']['details']['source_code']??'')==='ba_persistence_event_completion_taxonomy','incompatible_state_rejected');nf02c2_ck(nf02c2_event_state($eid)===$before_conflict&&nf02c2_row($rid)['content_original']===$canonical_bytes&&!$repo->event_id_for_report($rid),'incompatible_state_not_overwritten');nf02c2_ck(get_post_status($eid)==='pending','incompatible_fixture_pending');
 foreach(['badaround-report/v1','badaround-report/v2'] as $v)foreach(['exact_point','approximate_area','territory_only'] as $mode){$p=nf02c2_fixture();$p['schema_version']=$v;if($mode==='territory_only'){unset($p['location']['exact_lat'],$p['location']['exact_lng']);$p['time']=['mode'=>'unknown'];}else{$p['location']['public_location_request']=$mode;$p['location']['public_location_request_version']=BadAround_Report_Schema::PUBLIC_LOCATION_REQUEST_VERSION;}$n=BadAround_Report_Version_Registry::prepare($p);nf02c2_ck(!is_wp_error($n),$v.'_geo_'.$mode);if($mode!=='territory_only')nf02c2_ck($n['location']['exact_lat']===41.669&&$n['location']['public_location_request']===$mode,$v.'_geo_preserved_'.$mode);}
 $media=new BadAround_Native_Media_Service();nf02c2_ck($media->ready()===false,'media_remains_fail_closed');
 foreach(['badaround-report/v1','badaround-report/v2'] as $v){$p=nf02c2_fixture();$p['schema_version']=$v;$p['submission_id']=wp_generate_uuid4();$p['media']=['availability'=>'yes','items'=>[['media_id'=>wp_generate_uuid4(),'mime_type'=>'image/png','file_size'=>100,'extension'=>'png']]];$r=(new BadAround_Native_Media_Report_Adapter())->submit($p,'invalid');nf02c2_ck(nf02c2_code($r)!=='success'&&!(new BadAround_Report_Repository())->find_by_native_submission($p['submission_id']),$v.'_media_admission_rejected_no_report');}
 foreach($nf2_fixtures as $f){$repository=new BadAround_Report_Repository();$id=$repository->native_source_identity($f['submission_id']);$count=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}ba_reports WHERE source_type='native' AND source_form_id=%d AND source_entry_id=%d",$id['form_id'],$id['entry_id']));$events=get_posts(['post_type'=>'ba_evento','post_status'=>'any','fields'=>'ids','posts_per_page'=>3,'meta_key'=>'_ba_primary_report_id','meta_value'=>$f['report_id']]);nf02c2_ck($count===1&&count($events)===1&&(int)$events[0]===$f['event_id']&&get_post_status($f['event_id'])==='pending','one_report_one_pending_event_'.$f['report_id']);}
 foreach(range(35,43) as $id){$r=nf02c2_row($id);$payload=json_decode($r['content_original'],true);nf02c2_ck(is_array((new BadAround_Moderation_Admin())->canonical_report_view($payload)),'historical_admin_read_'.$id);}
 $after=nf02c2_snapshot();nf02c2_ck($before===$after,'protected_schema_taxonomy_media_unchanged');
 echo wp_json_encode(['pass'=>true,'checks'=>$nf2_checks,'fixtures'=>$nf2_fixtures,'before'=>$before,'after'=>$after,'media_service_ready'=>false]);
}catch(Throwable $e){echo wp_json_encode(['pass'=>false,'error'=>$e->getMessage(),'checks'=>$nf2_checks,'fixtures'=>$nf2_fixtures]);}
  $raw=ob_get_clean();$result=json_decode($raw,true);if(!is_array($result))$result=['pass'=>false,'error'=>'Non-JSON QA output','output_sha256'=>hash('sha256',$raw)];
  if(false===file_put_contents($result_path,wp_json_encode($result),LOCK_EX))return new WP_REST_Response(['pass'=>false,'error'=>'Cannot preserve result'],500);chmod($result_path,0600);delete_option($option);return new WP_REST_Response($result,!empty($result['pass'])?200:500);
 }]);
});
