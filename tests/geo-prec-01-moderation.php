<?php
// Preserve the complete legacy moderation and audit oracle, then test new reports.
require __DIR__ . '/geo-public-04-moderation.php';
foreach(['exact_point'=>'Punto esatto','approximate_area'=>'Area approssimativa'] as $mode=>$label){
 gseed(950,$mode);$GLOBALS['reports'][950]['has_governance']=1;$GLOBALS['reports'][950]['governance']='user-public-location/v1';
 $snapshot=serialize([$GLOBALS['reports'],$GLOBALS['meta'],$GLOBALS['audit']]);
 $context=$svc->request_context(950);gassert($context['user_authoritative']&&$context['allowed']===[],'no moderator mode choices '.$mode);
 $html=ghtml(950);gassert(str_contains($html,'Posizione pubblica richiesta:')&&str_contains($html,$label)&&!str_contains($html,'<select')&&!str_contains($html,'exact_confirmed'),'read-only reporter context '.$mode);
 $_GET['post']=950;ob_start();$admin->render_geo_decision_form();$form=ob_get_clean();gassert($form==='','no hidden moderator geo form');
 foreach(['exact_point','approximate_area','street','territory_only','unsupported'] as $override){
  $input=ginput(950,$override,'privacy',true);$input['public_location_request']='unsupported';
  $result=$admin->process_geo_decision_request(950,$input,'nonce:ba_save_geo_decision_950','POST');
  gassert(gerror($result,'user_authoritative')&&serialize([$GLOBALS['reports'],$GLOBALS['meta'],$GLOBALS['audit']])===$snapshot,'crafted POST denied without mutation '.$mode.' -> '.$override);
 }
 // Real publication audit must describe user authority, never a moderator precision decision.
 $l=['public_location_request'=>$mode,'public_location_request_version'=>'public-location-request-v1'];
 $d=BadAround_Public_Geo_Contract::user_authority(['public_geo_governance'=>'user-public-location/v1','canonical'=>['location'=>$l]]);
 gassert(BadAround_Audit_Log::geographic_publication(950,['location'=>$l,'decision'=>$d],['mode'=>$mode,'version'=>'public-location/v1'])===1,'new publication audit stored');
 $audit=json_decode(end($GLOBALS['audit'])['new_value'],true);
 gassert(!isset($audit['moderator_decision'])&&$audit['geo_authority']==='user-public-location/v1'&&$audit['user_request']===$mode,'no moderator decision in new audit');
}
gseed(952,'exact_point');$GLOBALS['reports'][952]['has_governance']=1;$GLOBALS['reports'][952]['governance']='future-policy';
gassert(is_wp_error($svc->request_context(952)),'unknown governance fails closed rather than legacy fallback');
echo "GEO_PREC_01_MODERATION=PASS\n";
