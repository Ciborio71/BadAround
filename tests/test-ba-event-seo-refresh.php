<?php
/** Explicit administrator test migration against the disposable transactional WP harness. */
require __DIR__.'/test-ba-event-seo.php';
function badaround_child_maintenance_enabled(){return $GLOBALS['seo_maintenance']??true;}
BadAround_Event_SEO::register_test_refresh();$route=$GLOBALS['seo_route'];
check($route['methods']==='POST','test refresh cannot be invoked by a public GET');
$GLOBALS['denied']=['manage_options'];check(!$route['permission_callback'](['id'=>9000]),'ordinary moderator cannot migrate test URLs');$GLOBALS['denied']=[];
$GLOBALS['seo_maintenance']=false;
check(is_wp_error($route['callback'](['id'=>9000,'apply'=>true,'expected_slug'=>'irrelevant'])),'test migration disabled outside maintenance');$GLOBALS['seo_maintenance']=true;
$before=serialize([$GLOBALS['meta'],$GLOBALS['posts']]);$preview=$route['callback'](['id'=>9000,'apply'=>false]);
check($preview instanceof WP_REST_Response&&$before===serialize([$GLOBALS['meta'],$GLOBALS['posts']]),'preview has no writes');
check(is_wp_error($route['callback'](['id'=>9000,'apply'=>true,'expected_slug'=>'stale']))&&$before===serialize([$GLOBALS['meta'],$GLOBALS['posts']]),'stale source rejected before mutation');
$old=get_post(9000)->post_name;$identity=get_post_meta(9000,BadAround_Event_SEO::URL_ID,true);
$GLOBALS['commit_fail']=true;$failure=$route['callback'](['id'=>9000,'apply'=>true,'expected_slug'=>$old]);$GLOBALS['commit_fail']=false;
check(is_wp_error($failure)&&$before===serialize([$GLOBALS['meta'],$GLOBALS['posts']]),'failed commit rolls back post and metadata');
$result=$route['callback'](['id'=>9000,'apply'=>true,'expected_slug'=>$old]);
check($result instanceof WP_REST_Response&&$result->data['redirect_created']===false,'explicit refresh succeeds without redirect registration');
check(get_post_meta(9000,BadAround_Event_SEO::URL_ID,true)===$identity&&get_post_meta(9000,'_ba_seo_test_backup_v2',true)['slug']===$old,'stable public identity preserved and original URL backed up');
check(get_post_meta(9000,'_wp_old_slug',true)==='','no old-slug redirect metadata written');
echo "EVENT_SEO_REFRESH_SUITE_PASS\n";
