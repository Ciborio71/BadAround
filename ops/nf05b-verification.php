<?php
if(!defined('ABSPATH')||($_SERVER['HTTP_HOST']??'')!=='staging.badaround.it')return;
add_action('rest_api_init',static function(){register_rest_route('nf05b-qa/v1','/verify',[
'methods'=>'POST','permission_callback'=>static function($r){return current_user_can('manage_options')&&hash_equals('a00e5e30d7ff281989177f836a8663310ea606a98e513e0d65558bc74f0153f3',hash('sha256',(string)$r->get_param('token')))?true:new WP_Error('nf05b_denied','QA authorization required.',['status'=>403]);},
'callback'=>static function($request){global $wpdb;nocache_headers();header('Cache-Control: private, no-store, max-age=0');try{
$report=(int)$request->get_param('report_id');if(!in_array($report,[69,70,71],true))throw new RuntimeException('QA_REPORT_NOT_AUTHORIZED');
$before=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ba_reports WHERE id=%d",$report),ARRAY_A);$envelope=json_decode($before['content_original']??'',true);$p=$envelope['canonical']??[];$event=(int)($before['event_id']??0);
if(($p['schema_version']??'')!=='badaround-report/v2'||($p['reporter']['email']??'')!=='nfux05b@example.invalid'||($p['reporter']['social_visibility']??'')!=='declined'||!str_starts_with($p['content']['description']??'','NF-UX-05B ')||get_post_status($event)!=='pending'||!empty($p['media']['items']))throw new RuntimeException('QA_SCOPE_GUARD');
$definition=BadAround_Report_Conditional_Contract::definition();$media=(new BadAround_Native_Media_Config())->enabled();
$v1=false;foreach($wpdb->get_col("SELECT content_original FROM {$wpdb->prefix}ba_reports WHERE id BETWEEN 35 AND 55 ORDER BY id") as $raw){$stored=json_decode($raw,true)['canonical']??[];if(($stored['schema_version']??'')==='badaround-report/v1'){$v1=!is_wp_error(BadAround_Report_Version_Registry::prepare($stored));break;}}
$retry=(new BadAround_Native_Report_Golden_Path_Service())->submit($p);
$after=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ba_reports WHERE id=%d",$report),ARRAY_A);
$result=['report'=>$report,'event'=>$event,'retry'=>$retry,'snapshot_unchanged'=>$before===$after,'report_count'=>(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}ba_reports WHERE id=%d",$report)),'event_pending'=>get_post_status($event)==='pending','media_enabled'=>$media,'v1_valid'=>$v1,'contract_sha'=>BadAround_Report_Conditional_Contract::SHA256,'definition_rows'=>count($definition['matrix'])];
return new WP_REST_Response($result,200);
}finally{unlink(__FILE__);}
}]);});
