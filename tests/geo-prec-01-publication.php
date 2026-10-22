<?php
// Keep the entire historical transactional publication oracle; then exercise new governance.
require __DIR__ . '/geo-public-05-publication.php';
function gpseed($id,$request){
 g5seed($id,$request,$request);
 unset($GLOBALS['meta'][$id][BadAround_Public_Geo_Contract::DECISION_META]);
 $e=json_decode($GLOBALS['private_fixture']['content_original'],true);
 $e[BadAround_Public_Geo_Contract::GOVERNANCE_KEY]=BadAround_Public_Geo_Contract::USER_AUTHORITY;
 $GLOBALS['private_fixture']['content_original']=json_encode($e);
}
foreach(['exact_point','approximate_area'] as $i=>$request){
 $id=800+$i;gpseed($id,$request);$private=$GLOBALS['private_fixture'];
 check($svc->publish($id)===true,'reporter '.$request.' publishes without moderator geo decision');
 $p=get_post_meta($id,'_ba_public_geo');
 check($p['mode']===$request&&get_post_status($id)==='publish','user choice is authoritative '.$request);
 check(''===get_post_meta($id,'_ba_public_location_decision'),'no synthetic moderator decision persisted');
 check($GLOBALS['private_fixture']===$private,'persisted request and private coordinates immutable');
 check(BadAround_Public_Geo_Projection::public_view($id)['mode']===$request,'public consumer reads sealed projection only');
 $before=serialize([$GLOBALS['meta'][$id],$GLOBALS['posts'][$id],$GLOBALS['geo_audits']]);
 check($svc->publish($id)===true&&serialize([$GLOBALS['meta'][$id],$GLOBALS['posts'][$id],$GLOBALS['geo_audits']])===$before,'idempotent publication and immutable geometry');
 $GLOBALS['meta'][$id]['_ba_public_location_decision']=['mode'=>$request==='exact_point'?'approximate_area':'exact_point'];
 check(is_wp_error($svc->publish($id))&&BadAround_Public_Geo_Projection::public_view($id)===null,'injected moderator override fails closed '.$request);
 unset($GLOBALS['meta'][$id]['_ba_public_location_decision']);
 $proof=$GLOBALS['meta'][$id]['_ba_public_geo_proof'];
 $GLOBALS['meta'][$id]['_ba_public_geo_proof']['user_authority']['mode']=$request==='exact_point'?'approximate_area':'exact_point';
 check(BadAround_Public_Geo_Projection::public_view($id)===null,'tampered public authority proof denied');
 $GLOBALS['meta'][$id]['_ba_public_geo_proof']=$proof;
}
foreach(['exact_point','approximate_area'] as $mode){
 gpseed(810,$mode);$GLOBALS['meta'][810]['_ba_moderation_status']='rejected';
 check(is_wp_error($svc->publish(810))&&g5none(810)&&get_post_status(810)==='pending'&&null===BadAround_Public_Geo_Projection::public_view(810),'whole-report rejection prevents publication '.$mode);
}
foreach(['bad_mode','missing_request','null_governance','future_governance','audit_failure','commit_failure','missing_coords','source_mismatch','private_text','exact_veto'] as $case){
 gpseed(820,'exact_point');$e=json_decode($GLOBALS['private_fixture']['content_original'],true);
 switch($case){
 case 'bad_mode':$e['canonical']['location']['public_location_request']='street';break;
 case 'missing_request':unset($e['canonical']['location']['public_location_request']);break;
 case 'null_governance':$e['public_geo_governance']=null;break;
 case 'future_governance':$e['public_geo_governance']='unknown';break;
 case 'audit_failure':$GLOBALS['audit_fail']=true;break;
 case 'commit_failure':$GLOBALS['commit_fail']=true;break;
 case 'missing_coords':$GLOBALS['private_fixture']['exact_lat']=null;break;
 case 'source_mismatch':$GLOBALS['private_fixture']['exact_lat']=42;break;
 case 'private_text':get_post(820)->post_content='Private Address 99';break;
 case 'exact_veto':$veto=static fn()=>false;add_filter('badaround_public_geo_exact_allowed',$veto,999);break;
 }
 $GLOBALS['private_fixture']['content_original']=json_encode($e);$before=$GLOBALS['private_fixture'];
 check(is_wp_error($svc->publish(820))&&g5none(820)&&get_post_status(820)==='pending'&&$GLOBALS['private_fixture']===$before,'new governance atomic fail-closed '.$case);
 $GLOBALS['audit_fail']=false;$GLOBALS['commit_fail']=false;if(isset($veto)){remove_filter('badaround_public_geo_exact_allowed',$veto,999);unset($veto);}
}
foreach(['f32-c4'=>150,'f32-c5'=>300,'f32-c6'=>1000,'f32-c7'=>3000,'unknown'=>750] as $precision=>$radius){
 gpseed(830,'approximate_area');$GLOBALS['meta'][830]['_ba_public_location_precision']=$precision;
 check($svc->publish(830)===true&&get_post_meta(830,'_ba_public_geo')['radius_meters']===$radius,'certified system radius preserved '.$precision);
 gpseed(831,'exact_point');$GLOBALS['meta'][831]['_ba_public_location_precision']=$precision;
 check($svc->publish(831)===true&&get_post_meta(831,'_ba_public_geo')['mode']==='exact_point','legacy precision cannot override exact '.$precision);
}
foreach([true,1,null,[],['authority'=>'user-public-location/v1','mode'=>true,'request_version'=>'public-location-request-v1']] as $bad){
 check(is_wp_error(BadAround_Public_Geo_Contract::validate_user_authority(['public_location_request'=>'exact_point','public_location_request_version'=>'public-location-request-v1'],$bad)),'malformed authority denied');
}
echo "GEO_PREC_01_PUBLICATION=PASS\n";

function sanitize_textarea_field($v){return trim(strip_tags((string)$v));}
function get_current_user_id(){return 42;}
function wp_unslash($v){return $v;}
function wp_verify_nonce($nonce,$action){return $nonce==='nonce:'.$action;}
function add_action($n,$f,$p=10,$a=1){add_filter($n,$f,$p,$a);}
function remove_action($n,$f,$p=10){remove_filter($n,$f,$p);}
require_once dirname(__DIR__).'/wordpress/plugins/badaround-core/includes/class-badaround-moderation-admin.php';
$moderator=new BadAround_Moderation_Service();$admin=new BadAround_Moderation_Admin();
foreach(['exact_point','approximate_area'] as $mode)foreach(['approved','rejected'] as $target){
 gpseed(840,$mode);$GLOBALS['meta'][840]['_ba_moderation_status']='new';$private=$GLOBALS['private_fixture'];
 if($target==='rejected')check(is_wp_error($moderator->transition(840,$target,'')),'rejection reason still mandatory');
 check($moderator->transition(840,$target,$target==='rejected'?'Synthetic QA rejection':'')===true,'whole-report real moderation transition '.$mode.' '.$target);
 check(get_post_status(840)==='pending'&&g5none(840)&&$GLOBALS['private_fixture']===$private&&''===get_post_meta(840,'_ba_public_location_decision'),'B2 never publishes or changes user request');
 $_POST=['ba_public_fields_nonce'=>'nonce:ba_save_public_fields_840','mode'=>'unsupported','public_location_request'=>'unsupported','ba_public_meta'=>['_ba_public_lat'=>'0','_ba_public_lng'=>'0','_ba_public_radius_m'=>'9999','_ba_public_location_decision'=>['mode'=>'unsupported'],'_ba_public_location_precision'=>'f32-c7','public_geo_governance'=>'legacy']];
 $admin->save_public_fields(840,get_post(840));
 check(g5none(840)&&get_post_status(840)==='pending'&&$GLOBALS['private_fixture']===$private&&''===get_post_meta(840,'_ba_public_location_decision'),'ordinary crafted save cannot mutate request, decision, geometry or publication state');
 if($target==='approved'){
  check($svc->publish(840)===true&&get_post_meta(840,'_ba_public_geo')['mode']===$mode,'approval publishes reporter mode '.$mode);
  $projection=get_post_meta(840,'_ba_public_geo');$proof=get_post_meta(840,'_ba_public_geo_proof');
  $admin->save_public_fields(840,get_post(840));
  check(get_post_status(840)==='publish'&&get_post_meta(840,'_ba_public_geo')===$projection&&get_post_meta(840,'_ba_public_geo_proof')===$proof&&$GLOBALS['private_fixture']===$private,'published ordinary save preserves frozen geometry and private request');
 }else{check(is_wp_error($svc->publish(840))&&g5none(840),'rejection remains not publishable');}
}
echo "GEO_PREC_01_LIFECYCLE=PASS cases=4\n";
