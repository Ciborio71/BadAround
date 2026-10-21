<?php
if(!defined('ABSPATH'))exit;
if(function_exists('opcache_invalidate')) opcache_invalidate(WP_CONTENT_DIR.'/plugins/badaround-core/includes/class-badaround-moderation-admin.php',true);
if(function_exists('opcache_invalidate')) opcache_invalidate(WP_CONTENT_DIR.'/plugins/badaround-core/includes/class-badaround-native-media-fence.php',true);
if(function_exists('opcache_invalidate')) opcache_invalidate(WP_CONTENT_DIR.'/plugins/badaround-core/includes/class-badaround-native-media-persistence.php',true);
if(function_exists('opcache_invalidate')) opcache_invalidate(WP_CONTENT_DIR.'/plugins/badaround-core/includes/class-badaround-native-media-service.php',true);
if(function_exists('opcache_invalidate')) opcache_invalidate(WP_CONTENT_DIR.'/plugins/badaround-core/includes/class-badaround-native-report-intake-service.php',true);
if(function_exists('opcache_invalidate')) opcache_invalidate(WP_CONTENT_DIR.'/plugins/badaround-core/includes/class-badaround-native-report-rest-controller.php',true);
if(function_exists('opcache_invalidate')) opcache_invalidate(WP_CONTENT_DIR.'/plugins/badaround-core/includes/class-badaround-report-conditional-contract.php',true);
if(function_exists('opcache_invalidate')) opcache_invalidate(WP_CONTENT_DIR.'/plugins/badaround-core/includes/class-badaround-report-idempotency-service.php',true);
if(function_exists('opcache_invalidate')) opcache_invalidate(WP_CONTENT_DIR.'/plugins/badaround-core/includes/class-badaround-report-normalizer.php',true);
if(function_exists('opcache_invalidate')) opcache_invalidate(WP_CONTENT_DIR.'/plugins/badaround-core/includes/class-badaround-report-repository.php',true);
if(function_exists('opcache_invalidate')) opcache_invalidate(WP_CONTENT_DIR.'/plugins/badaround-core/includes/class-badaround-report-result.php',true);
if(function_exists('opcache_invalidate')) opcache_invalidate(WP_CONTENT_DIR.'/plugins/badaround-core/includes/class-badaround-report-v2-normalizer.php',true);
if(function_exists('opcache_invalidate')) opcache_invalidate(WP_CONTENT_DIR.'/plugins/badaround-core/includes/class-badaround-report-v2-validator.php',true);
if(function_exists('opcache_invalidate')) opcache_invalidate(WP_CONTENT_DIR.'/plugins/badaround-core/includes/class-badaround-report-validator.php',true);
if(function_exists('opcache_invalidate')) opcache_invalidate(WP_CONTENT_DIR.'/plugins/badaround-core/includes/class-badaround-report-version-registry.php',true);

add_action('rest_api_init',function(){
 register_rest_route('nf02b-qa/v1','/run',[
  'methods'=>'POST',
  'permission_callback'=>function($request){return ($_SERVER['HTTP_HOST']??'')==='staging.badaround.it' && hash_equals('__TOKEN__',(string)$request->get_header('x-nf-qa'));},
  'callback'=>function(){
   global $wpdb,$results,$fixtures;
   ob_start();
$results=[]; $fixtures=[];
function nf02b_ck($v,$name) { global $results; $results[$name]=(bool)$v; if(!$v) throw new RuntimeException($name); }
function nf02b_fixture() {
	return array(
		'schema_version'=>'badaround-report/v1',
		'submission_id'=>'123e4567-e89b-42d3-a456-426614174000',
		'event'=>array('category'=>'hazard','subtype'=>'hazard_road_obstruction'),
		'reporter'=>array(
			'relationship'=>'territory_observer','first_name'=>' Mario ','last_name'=>' Rossi ',
			'email'=>'MARIO@example.invalid','phone'=>'+39 333 1234567',
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
function nf02b_code($r) { return is_wp_error($r)?$r->get_error_code():($r['error']['code']??'success'); }
function nf02b_snapshot() { global $wpdb; return ['fixture'=>hash('sha256',(string)$wpdb->get_var("SELECT content_original FROM {$wpdb->prefix}ba_reports WHERE id=35")), 'event312'=>get_post_status(312),'terms'=>hash('sha256',wp_json_encode($wpdb->get_results("SELECT term_id,name,slug FROM {$wpdb->terms} ORDER BY term_id",ARRAY_A))), 'schema'=>hash('sha256',preg_replace('/AUTO_INCREMENT=[0-9]+/','AUTO_INCREMENT=COUNTER',wp_json_encode($wpdb->get_results("SHOW CREATE TABLE {$wpdb->prefix}ba_reports",ARRAY_N))))]; }
try {
 $before=nf02b_snapshot(); nf02b_ck($before['fixture']==='ba4672ec075306b23eb2b6f9c1f68f15676d8c613d8884d87e1aa01db9a72197' && $before['event312']==='pending','historical_fixture_before');
 nf02b_ck(BadAround_Report_Schema::VERSION==='badaround-report/v1','native_version_stays_v1');
 $repo=new BadAround_Report_Repository(); $gold=new BadAround_Native_Report_Golden_Path_Service();
 foreach(['badaround-report/v1','badaround-report/v2'] as $version) {
  $p=nf02b_fixture(); $p['schema_version']=$version; $p['submission_id']=wp_generate_uuid4();
  $p['reporter']['email']='nf02b-qa@example.invalid'; $p['content']['description']='NF-UX-02B synthetic backend staging QA — do not publish.';
  $p['location']['public_location_request']='approximate_area'; $p['location']['public_location_request_version']=BadAround_Report_Schema::PUBLIC_LOCATION_REQUEST_VERSION;
  $p['reporter']['social_visibility']='declined'; $p['consents']['social_visibility_version']=BadAround_Report_Schema::SOCIAL_PREFERENCE_VERSION;
  $r=$gold->submit($p); nf02b_ck(nf02b_code($r)==='success',$version.'_golden_path_'.nf02b_code($r));
  $fixtures[]=['schema_version'=>$version,'submission_id'=>$p['submission_id'],'report_id'=>$r['report_id'],'event_id'=>$r['event_id']];
  nf02b_ck(get_post_status($r['event_id'])==='pending',$version.'_pending');
  $again=$gold->submit($p); nf02b_ck(nf02b_code($again)==='success' && $again['duplicate'] && $again['report_id']===$r['report_id'] && $again['event_id']===$r['event_id'],$version.'_retry');
  $changed=$p; $changed['content']['description'].=' changed'; nf02b_ck(nf02b_code($gold->submit($changed))==='duplicate_submission',$version.'_active_conflict');
  $cross=$p; $cross['schema_version']=$version==='badaround-report/v1'?'badaround-report/v2':'badaround-report/v1'; nf02b_ck(nf02b_code($gold->submit($cross))==='duplicate_submission',$version.'_cross_conflict');
  $raw=$wpdb->get_var($wpdb->prepare("SELECT content_original FROM {$wpdb->prefix}ba_reports WHERE id=%d",$r['report_id'])); $stored=json_decode($raw,true);
  nf02b_ck($stored['schema_version']===$version && $stored['canonical']['schema_version']===$version,$version.'_persistence');
  nf02b_ck($stored['canonical']['location']['public_location_request']==='approximate_area' && $stored['canonical']['location']['exact_lat']===41.669,$version.'_geo');
  nf02b_ck($stored['canonical']['reporter']['social_visibility']==='declined',$version.'_social');
  nf02b_ck(empty($stored['canonical']['media']['items']) && true===BadAround_Native_Media_Fence::check($r['event_id']),$version.'_no_media_fence');
  $view=(new BadAround_Moderation_Admin())->canonical_report_view($stored); nf02b_ck(is_array($view),$version.'_admin_read');
  if($version==='badaround-report/v2') { $stale=$p; $stale['vehicle']=['type'=>'car']; nf02b_ck(nf02b_code($gold->submit($stale))==='success','v2_stale_retry'); $bad=$p; $bad['unknown']=true; nf02b_ck(nf02b_code($gold->submit($bad))==='unknown_field','v2_unknown_rejected'); }
 }
 // Exercise all frozen subtype oracles against the deployed validator without persisting them.
 $contract=json_decode(file_get_contents(WP_PLUGIN_DIR.'/badaround-core/contracts/nf-conditional-v1.0.json'),true);
 foreach($contract['regression_oracle'] as $oracle) {
  $o=['schema_version'=>'badaround-report/v2','submission_id'=>wp_generate_uuid4(),'consents'=>['version'=>'native-v1']];
  foreach($oracle['minimal_user_values'] as $path=>$value) { $parts=explode('.',$path); $ptr=&$o; foreach($parts as $part) { if(!isset($ptr[$part]))$ptr[$part]=[]; $ptr=&$ptr[$part]; } $ptr=$value;unset($ptr); }
  nf02b_ck(!is_wp_error(BadAround_Report_Version_Registry::prepare($o)),'oracle_'.$oracle['subtype']);
  if($oracle['subtype']==='animal_missing') { $o['damage']=['status'=>'not_applicable'];$n=BadAround_Report_Version_Registry::prepare($o);nf02b_ck(!is_wp_error($n) && !isset($n['damage']),'lost_animal_damage_hidden'); }
 }
 // Read-only media adapter admission: invalid capability cannot create/pin/bind anything.
 $service=new BadAround_Native_Media_Service();
 foreach(['badaround-report/v1','badaround-report/v2'] as $v) { $p=nf02b_fixture();$p['schema_version']=$v;$p['submission_id']=wp_generate_uuid4(); $p['media']=['availability'=>'yes','items'=>[['media_id'=>wp_generate_uuid4(),'mime_type'=>'image/png','file_size'=>100,'extension'=>'png']]];
  $r=(new BadAround_Native_Media_Report_Adapter())->submit($p,'invalid'); nf02b_ck(nf02b_code($r)!=='success' && !$repo->find_by_native_submission($p['submission_id']),$v.'_media_admission_fails_closed');
 }
 if($service->ready()) {
  foreach(['badaround-report/v1','badaround-report/v2'] as $v) {
   $p=nf02b_fixture();$p['schema_version']=$v;$p['submission_id']=wp_generate_uuid4();$p['content']['description']='NF-UX-02B synthetic media backend QA — do not publish.';$p['reporter']['email']='nf02b-qa@example.invalid';
   $session=$service->create($p['submission_id'],BadAround_Native_Media_Capability::encode(random_bytes(32)),hash('sha256','NF02B-QA'));nf02b_ck(!is_wp_error($session),$v.'_media_session');
   $im=imagecreatetruecolor(2,2);ob_start();imagepng($im);$bytes=ob_get_clean();imagedestroy($im);$stream=fopen('php://temp','w+b');fwrite($stream,$bytes);rewind($stream);
   try {$up=$service->upload($session['media_session_id'],wp_generate_uuid4(),$session['capability'],$stream,'synthetic.png',hash('sha256','NF02B-QA'));} finally {fclose($stream);}
   nf02b_ck(!is_wp_error($up),$v.'_media_upload');$p['media']=['availability'=>'yes','items'=>[$up['descriptor']]];
   $canonical=BadAround_Report_Version_Registry::prepare($p);nf02b_ck(!is_wp_error($canonical),$v.'_media_canonical');
   nf02b_ck(!is_wp_error($service->pin($canonical,$session['capability'],$repo)),$v.'_media_pin');nf02b_ck(!$repo->find_by_native_submission($p['submission_id']),$v.'_pin_before_report');
   $cross=nf02b_fixture();$cross['submission_id']=$p['submission_id'];$cross['schema_version']=$v==='badaround-report/v1'?'badaround-report/v2':'badaround-report/v1';nf02b_ck(nf02b_code($gold->submit($cross))==='duplicate_submission',$v.'_media_pre_report_cross_conflict');
   $adapter=new BadAround_Native_Media_Report_Adapter();$r=$adapter->submit($p,$session['capability']);nf02b_ck(nf02b_code($r)==='success',$v.'_media_binding_'.nf02b_code($r));
   $fixtures[]=['schema_version'=>$v,'submission_id'=>$p['submission_id'],'report_id'=>$r['report_id'],'event_id'=>$r['event_id'],'media_session_id'=>$session['media_session_id']];
   nf02b_ck(get_post_status($r['event_id'])==='pending' && is_wp_error(BadAround_Native_Media_Fence::check($r['event_id'])),$v.'_media_fence');
   $retry=$adapter->submit($p,$session['capability']);nf02b_ck(nf02b_code($retry)==='success' && $retry['duplicate'] && $retry['event_id']===$r['event_id'],$v.'_media_retry');
  }
 }
 $historical=json_decode($wpdb->get_var("SELECT content_original FROM {$wpdb->prefix}ba_reports WHERE id=35"),true); nf02b_ck(is_array((new BadAround_Moderation_Admin())->canonical_report_view($historical)),'historical_admin_read');
 $after=nf02b_snapshot(); nf02b_ck($before===$after,'historical_taxonomy_schema_unchanged');
 echo wp_json_encode(['pass'=>true,'checks'=>$results,'fixtures'=>$fixtures,'integrity'=>$after,'media_service_ready'=>$service->ready()]);
} catch(Throwable $e) {  echo wp_json_encode(['pass'=>false,'error'=>$e->getMessage(),'checks'=>$results,'fixtures'=>$fixtures]); }

   $raw=ob_get_clean();$result=json_decode($raw,true);
   if(!is_array($result))$result=['pass'=>false,'error'=>'non-JSON harness output'];
   return new WP_REST_Response($result,!empty($result['pass'])?200:500);
  }
 ]);
});
