<?php
/** Reproduce classic admin publication with the real B2 filters and B3 service. */
require __DIR__.'/event-dynamic-seo.php';
require_once getenv('BA_PUBLICATION_SERVICE_FILE')?dirname(getenv('BA_PUBLICATION_SERVICE_FILE')).'/class-badaround-moderation-admin.php':dirname(__DIR__).'/wordpress/plugins/badaround-core/includes/class-badaround-moderation-admin.php';
if(!function_exists('remove_action')){function remove_action($name,$fn,$priority=10){remove_filter($name,$fn,$priority);}}
$admin=new BadAround_Moderation_Admin();$GLOBALS['fixture_moderation']=$admin;
add_filter('wp_insert_post_data',array($admin,'prevent_b2_publish'),20,2);
$GLOBALS['wp_core_post_hooks']=true;$_POST=[];
$expect_old=in_array('--expect-old-failure',$argv,true);
foreach(['exact_point','approximate_area'] as $i=>$mode){
 $id=9800+$i;dynamic_fixture($id,$mode);$before=serialize([$GLOBALS['meta'][$id],get_post($id)]);
 $result=$svc->publish($id);
 if($expect_old){
  check(is_wp_error($result)&&$result->get_error_code()==='ba_publication_geo_state_failed','prior runtime reproduces classic SEO status failure '.$mode);
  check($before===serialize([$GLOBALS['meta'][$id],get_post($id)]),'old failure atomically rolls back '.$mode);
 }else{
  check($result===true&&get_post_status($id)==='publish'&&get_post_meta($id,'_ba_moderation_status')==='published','classic B3 plus SEO finalization retains publish state '.$mode);
  check(!is_wp_error(BadAround_Public_Geo_Projection::verified($id))&&BadAround_Event_SEO::active($id),'verified geometry and frozen SEO remain valid '.$mode);
  check(!apply_filters('badaround_allow_event_publish',false,$id)&&!apply_filters('badaround_allow_event_publish',false,$id+100),'scoped publication authorization removed '.$mode);
  $slug=get_post($id)->post_name;check($svc->publish($id)===true&&get_post($id)->post_name===$slug,'repeat publication remains idempotent '.$mode);
 }
}
if(!$expect_old){
 dynamic_fixture(9804,'approximate_area');$before=serialize([$GLOBALS['meta'][9804],get_post(9804)]);$GLOBALS['commit_fail']=true;$result=$svc->publish(9804);$GLOBALS['commit_fail']=false;
 check(is_wp_error($result)&&$before===serialize([$GLOBALS['meta'][9804],get_post(9804)]),'commit failure still rolls back with classic hooks');
 check(!apply_filters('badaround_allow_event_publish',false,9804),'failed publication leaves no authorization');
 dynamic_fixture(9805);wp_update_post(['ID'=>9805,'post_status'=>'publish']);check(get_post_status(9805)==='pending','direct B2 publication remains blocked');
}
echo $expect_old?"CLASSIC_PUBLICATION_OLD_FAILURE_REPRODUCED\n":"CLASSIC_PUBLICATION_FIXED_PASS\n";
