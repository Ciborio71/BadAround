<?php
require __DIR__.'/geo-public-05-publication.php';
require_once dirname(__DIR__).'/wordpress/plugins/badaround-core/includes/class-badaround-report-schema.php';
function get_post_type($id){return get_post($id)->post_type;}
g5seed(9300);check($svc->publish(9300)===true,'detail fixture uses controlled publication');
$raw=json_decode($GLOBALS['private_fixture']['content_original'],true);
$raw['canonical']+=['witness'=>['status'=>'seeking'],'vehicle'=>['type'=>'scooter','distinctive_features'=>'Adesivo rosso'],
    'time'=>['mode'=>'exact','date'=>'2026-10-20','knowledge'=>'range','range_start'=>'18:00','range_end'=>'20:00'],
    'reporter'=>['email'=>'secret@example.invalid'],'object'=>['owner_verification_detail'=>'SECRET']];
$GLOBALS['private_fixture']['content_original']=json_encode($raw);
$p=BadAround_Event_Public_Details::candidate(9300);
check($p['seeking_witnesses']&&isset($p['facts']['vehicle.type']),'structured public facts and explicit seeking flag');
check(!isset($p['facts']['vehicle.distinctive_features'])&&!str_contains(json_encode($p),'SECRET')&&!str_contains(json_encode($p),'secret@example.invalid'),'private fields and unreviewed text excluded');
check($p['address']===''&&$p['address_available'],'candidate never exposes private address');
check(is_wp_error(BadAround_Event_Public_Details::approve(9300,'stale')),'stale source rejected');
$GLOBALS['denied']=['ba_moderate_events'];check(is_wp_error(BadAround_Event_Public_Details::approve(9300,$p['source_hash'])),'moderator authorization required');$GLOBALS['denied']=[];
check(is_wp_error(BadAround_Event_Public_Details::approve(9300,$p['source_hash'],true,false)),'address requires explicit attestation');
$result=BadAround_Event_Public_Details::approve(9300,$p['source_hash']);
check(!is_wp_error($result)&&$result['address']===''&&$result['seeking_witnesses'],'facts publish without address escalation'.(is_wp_error($result)?' ERROR='.$result->get_error_code():''));
$result=BadAround_Event_Public_Details::approve(9300,$p['source_hash'],true,true,true);
check(!is_wp_error($result)&&$result['address']==='Private Address 99'&&isset($result['facts']['vehicle.distinctive_features']),'explicit exact address and reviewed text approved');
check($svc->apply_public_details(9300,get_post_meta(9300,BadAround_Event_Public_Details::META,true),$p['source_hash'])===true,'authorized structured address passes owning publication validator');
check(apply_filters('badaround_event_contribution_heading','generic',9300)!=='generic','witness request uses the existing contribution heading');
$GLOBALS['posts'][9300]->post_content.=' Private Address 99';
check(is_wp_error($svc->apply_public_details(9300,get_post_meta(9300,BadAround_Event_Public_Details::META,true),$p['source_hash'])),'address authorization does not allow private address in prose');
$GLOBALS['posts'][9300]->post_content=str_replace(' Private Address 99','',$GLOBALS['posts'][9300]->post_content);
$stored=get_post_meta(9300,BadAround_Event_Public_Details::META,true);$GLOBALS['meta'][9300][BadAround_Event_Public_Details::META]['data']['address']='Injected';
check(BadAround_Event_Public_Details::view(9300)===[],'tampered public snapshot hidden');$GLOBALS['meta'][9300][BadAround_Event_Public_Details::META]=$stored;
$before=get_post_meta(9300,BadAround_Event_Public_Details::META,true);$geo=get_post_meta(9300,'_ba_public_geo',true);$original=$GLOBALS['private_fixture']['content_original'];
$GLOBALS['commit_fail']=true;$failed=BadAround_Event_Public_Details::approve(9300,$p['source_hash']);$GLOBALS['commit_fail']=false;
check(is_wp_error($failed)&&get_post_meta(9300,BadAround_Event_Public_Details::META,true)===$before,'failed commit restores previous public snapshot');
check(get_post_meta(9300,'_ba_public_geo',true)===$geo&&$GLOBALS['private_fixture']['content_original']===$original,'details never change GEO or original report');
$GLOBALS['denied']=['ba_view_private_reports'];check(is_wp_error(BadAround_Event_Public_Details::approve(9300,$p['source_hash'])),'private report permission required');$GLOBALS['denied']=[];
g5seed(9301,'approximate_area','approximate_area');check($svc->publish(9301)===true,'approximate fixture published');
$p=BadAround_Event_Public_Details::candidate(9301);
check(is_wp_error(BadAround_Event_Public_Details::approve(9301,$p['source_hash'],true,true)),'approximate area cannot disclose exact address');
$GLOBALS['term_meta'][11]['_ba_canonical_event_key']='vehicle_stolen';
$GLOBALS['meta'][9300]['_ba_vehicle_make']='Honda';$GLOBALS['meta'][9300]['_ba_vehicle_model']='SH';$GLOBALS['meta'][9300]['_ba_vehicle_color']='Bianco';
$frozen=get_post_meta(9300,'_ba_seo_slug',true);$seo=BadAround_Event_SEO::project(9300);
check(str_contains($seo['h1'],'colore bianco')&&get_post_meta(9300,'_ba_seo_slug',true)===$frozen,'public color enriches H1 without changing frozen URL');
echo "EVENT_PUBLIC_DETAILS_SUITE_PASS\n";
