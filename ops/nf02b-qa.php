<?php
if (($_SERVER['HTTP_HOST']??'') !== 'staging.badaround.it' || ($_SERVER['REQUEST_METHOD']??'') !== 'POST' || !hash_equals('__TOKEN__',$_SERVER['HTTP_X_NF_QA']??'')) { http_response_code(404); exit; }
header('Content-Type: application/json'); header('Cache-Control: no-store');
if(function_exists('opcache_invalidate')) opcache_invalidate(__DIR__.'/wp-content/plugins/badaround-core/includes/class-badaround-moderation-admin.php',true);
if(function_exists('opcache_invalidate')) opcache_invalidate(__DIR__.'/wp-content/plugins/badaround-core/includes/class-badaround-native-media-fence.php',true);
if(function_exists('opcache_invalidate')) opcache_invalidate(__DIR__.'/wp-content/plugins/badaround-core/includes/class-badaround-native-media-persistence.php',true);
if(function_exists('opcache_invalidate')) opcache_invalidate(__DIR__.'/wp-content/plugins/badaround-core/includes/class-badaround-native-media-service.php',true);
if(function_exists('opcache_invalidate')) opcache_invalidate(__DIR__.'/wp-content/plugins/badaround-core/includes/class-badaround-native-report-intake-service.php',true);
if(function_exists('opcache_invalidate')) opcache_invalidate(__DIR__.'/wp-content/plugins/badaround-core/includes/class-badaround-native-report-rest-controller.php',true);
if(function_exists('opcache_invalidate')) opcache_invalidate(__DIR__.'/wp-content/plugins/badaround-core/includes/class-badaround-report-conditional-contract.php',true);
if(function_exists('opcache_invalidate')) opcache_invalidate(__DIR__.'/wp-content/plugins/badaround-core/includes/class-badaround-report-idempotency-service.php',true);
if(function_exists('opcache_invalidate')) opcache_invalidate(__DIR__.'/wp-content/plugins/badaround-core/includes/class-badaround-report-normalizer.php',true);
if(function_exists('opcache_invalidate')) opcache_invalidate(__DIR__.'/wp-content/plugins/badaround-core/includes/class-badaround-report-repository.php',true);
if(function_exists('opcache_invalidate')) opcache_invalidate(__DIR__.'/wp-content/plugins/badaround-core/includes/class-badaround-report-result.php',true);
if(function_exists('opcache_invalidate')) opcache_invalidate(__DIR__.'/wp-content/plugins/badaround-core/includes/class-badaround-report-v2-normalizer.php',true);
if(function_exists('opcache_invalidate')) opcache_invalidate(__DIR__.'/wp-content/plugins/badaround-core/includes/class-badaround-report-v2-validator.php',true);
if(function_exists('opcache_invalidate')) opcache_invalidate(__DIR__.'/wp-content/plugins/badaround-core/includes/class-badaround-report-validator.php',true);
if(function_exists('opcache_invalidate')) opcache_invalidate(__DIR__.'/wp-content/plugins/badaround-core/includes/class-badaround-report-version-registry.php',true);
require __DIR__.'/wp-load.php';
$results=[]; $fixtures=[];
function ck($v,$name) { global $results; $results[$name]=(bool)$v; if(!$v) throw new RuntimeException($name); }
function fixture() {
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
function code($r) { return is_wp_error($r)?$r->get_error_code():($r['error']['code']??'success'); }
function snapshot() { global $wpdb; return ['fixture'=>hash('sha256',(string)$wpdb->get_var("SELECT content_original FROM {$wpdb->prefix}ba_reports WHERE id=35")), 'event312'=>get_post_status(312),'terms'=>hash('sha256',wp_json_encode($wpdb->get_results("SELECT term_id,name,slug FROM {$wpdb->terms} ORDER BY term_id",ARRAY_A))), 'schema'=>hash('sha256',wp_json_encode($wpdb->get_results("SHOW CREATE TABLE {$wpdb->prefix}ba_reports",ARRAY_N)))]; }
try {
 $before=snapshot(); ck($before['fixture']==='ba4672ec075306b23eb2b6f9c1f68f15676d8c613d8884d87e1aa01db9a72197' && $before['event312']==='pending','historical_fixture_before');
 ck(BadAround_Report_Schema::VERSION==='badaround-report/v1','native_version_stays_v1');
 $repo=new BadAround_Report_Repository(); $gold=new BadAround_Native_Report_Golden_Path_Service();
 foreach(['badaround-report/v1','badaround-report/v2'] as $version) {
  $p=fixture(); $p['schema_version']=$version; $p['submission_id']=wp_generate_uuid4();
  $p['reporter']['email']='nf02b-qa@example.invalid'; $p['content']['description']='NF-UX-02B synthetic backend staging QA — do not publish.';
  $p['location']['public_location_request']='approximate_area'; $p['location']['public_location_request_version']=BadAround_Report_Schema::PUBLIC_LOCATION_REQUEST_VERSION;
  $p['reporter']['social_visibility']='declined'; $p['consents']['social_visibility_version']=BadAround_Report_Schema::SOCIAL_PREFERENCE_VERSION;
  $r=$gold->submit($p); ck(code($r)==='success',$version.'_golden_path_'.code($r));
  $fixtures[]=['schema_version'=>$version,'submission_id'=>$p['submission_id'],'report_id'=>$r['report_id'],'event_id'=>$r['event_id']];
  ck(get_post_status($r['event_id'])==='pending',$version.'_pending');
  $again=$gold->submit($p); ck(code($again)==='success' && $again['duplicate'] && $again['report_id']===$r['report_id'] && $again['event_id']===$r['event_id'],$version.'_retry');
  $changed=$p; $changed['content']['description'].=' changed'; ck(code($gold->submit($changed))==='duplicate_submission',$version.'_active_conflict');
  $cross=$p; $cross['schema_version']=$version==='badaround-report/v1'?'badaround-report/v2':'badaround-report/v1'; ck(code($gold->submit($cross))==='duplicate_submission',$version.'_cross_conflict');
  $raw=$wpdb->get_var($wpdb->prepare("SELECT content_original FROM {$wpdb->prefix}ba_reports WHERE id=%d",$r['report_id'])); $stored=json_decode($raw,true);
  ck($stored['schema_version']===$version && $stored['canonical']['schema_version']===$version,$version.'_persistence');
  ck($stored['canonical']['location']['public_location_request']==='approximate_area' && $stored['canonical']['location']['exact_lat']===41.669,$version.'_geo');
  ck($stored['canonical']['reporter']['social_visibility']==='declined',$version.'_social');
  ck(empty($stored['canonical']['media']['items']) && true===BadAround_Native_Media_Fence::check($r['event_id']),$version.'_no_media_fence');
  $view=(new BadAround_Moderation_Admin())->canonical_report_view($stored); ck(is_array($view),$version.'_admin_read');
  if($version==='badaround-report/v2') { $stale=$p; $stale['vehicle']=['plate'=>'STALE']; ck(code($gold->submit($stale))==='success','v2_stale_retry'); $bad=$p; $bad['unknown']=true; ck(code($gold->submit($bad))==='unknown_field','v2_unknown_rejected'); }
 }
 // Read-only media adapter admission: invalid capability cannot create/pin/bind anything.
 $service=new BadAround_Native_Media_Service();
 foreach(['badaround-report/v1','badaround-report/v2'] as $v) { $p=fixture();$p['schema_version']=$v;$p['submission_id']=wp_generate_uuid4(); $p['media']=['availability'=>'yes','items'=>[['media_id'=>wp_generate_uuid4(),'mime_type'=>'image/png','file_size'=>100,'extension'=>'png']]];
  $r=(new BadAround_Native_Media_Report_Adapter())->submit($p,'invalid'); ck(code($r)!=='success' && !$repo->find_by_native_submission($p['submission_id']),$v.'_media_admission_fails_closed');
 }
 $historical=json_decode($wpdb->get_var("SELECT content_original FROM {$wpdb->prefix}ba_reports WHERE id=35"),true); ck(is_array((new BadAround_Moderation_Admin())->canonical_report_view($historical)),'historical_admin_read');
 $after=snapshot(); ck($before===$after,'historical_taxonomy_schema_unchanged');
 echo wp_json_encode(['pass'=>true,'checks'=>$results,'fixtures'=>$fixtures,'integrity'=>$after,'media_service_ready'=>$service->ready()]);
} catch(Throwable $e) { http_response_code(500); echo wp_json_encode(['pass'=>false,'error'=>$e->getMessage(),'checks'=>$results,'fixtures'=>$fixtures]); }
