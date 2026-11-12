<?php
require __DIR__.'/test-ba-event-seo.php';
function badaround_child_maintenance_enabled(){return $GLOBALS['retry_maintenance']??true;}
class PublicationRetryDB extends Geo05DB{function get_var($sql){if(str_contains($sql,'SELECT SHA2(content_original,256)'))return hash('sha256',$GLOBALS['private_fixture']['content_original']);return parent::get_var($sql);}}
$wpdb=new PublicationRetryDB();BadAround_Publication_Service::register_publication_retry();$route=$GLOBALS['seo_route'];
check($route['methods']==='POST','publication retry is POST only');
g5seed(9850,'approximate_area','approximate_area');$input=['id'=>9850,'expected_report_id'=>9851,'expected_source_hash'=>hash('sha256',$GLOBALS['private_fixture']['content_original'])];
foreach(['manage_options','ba_moderate_events','publish_ba_eventi','ba_view_private_reports','edit_post'] as $cap){$GLOBALS['denied']=[$cap];check(!$route['permission_callback']($input),'retry denies missing capability '.$cap);}$GLOBALS['denied']=[];
check($route['permission_callback']($input),'retry permits authorized admin');
$before=serialize([$GLOBALS['meta'],$GLOBALS['posts']]);
$preview=$route['callback']($input);check($preview instanceof WP_REST_Response&&$preview->data['ready']&&$before===serialize([$GLOBALS['meta'],$GLOBALS['posts']]),'retry preview makes no writes');
$GLOBALS['retry_maintenance']=false;check(is_wp_error($route['callback']($input)),'retry is maintenance only');$GLOBALS['retry_maintenance']=true;
check(is_wp_error($route['callback'](array_merge($input,['apply'=>true,'expected_report_id'=>1]))),'retry rejects changed primary report');
check(is_wp_error($route['callback'](array_merge($input,['apply'=>true,'expected_source_hash'=>'stale']))),'retry rejects changed source');
$GLOBALS['meta'][9850]['_ba_moderation_status']='new';check(is_wp_error($route['callback'](array_merge($input,['apply'=>true]))),'retry cannot approve an unmoderated event');$GLOBALS['meta'][9850]['_ba_moderation_status']='approved';
$result=$route['callback'](array_merge($input,['apply'=>true]));check($result instanceof WP_REST_Response&&$result->data['status']==='publish'&&$result->data['moderation']==='published','retry uses owning publication service');
$slug=get_post(9850)->post_name;$again=$route['callback'](array_merge($input,['apply'=>true]));check($again instanceof WP_REST_Response&&get_post(9850)->post_name===$slug,'retry does not duplicate published event');
echo "PUBLICATION_RETRY_PASS\n";
