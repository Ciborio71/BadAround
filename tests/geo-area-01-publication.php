<?php
require __DIR__.'/geo-prec-01-publication.php';
function area_seed($id,$mode,$radius=null){
 gpseed($id,$mode);$e=json_decode($GLOBALS['private_fixture']['content_original'],true);
 $e['canonical']['location']['public_location_request_version']=BadAround_Public_Geo_Contract::RADIUS_REQUEST_VERSION;
 $e['canonical']['location']['public_location_request']=['mode'=>$mode];
 if($radius!==null)$e['canonical']['location']['public_location_request']['radius_m']=$radius;
 $GLOBALS['private_fixture']['content_original']=json_encode($e);return $e['canonical']['location'];
}
foreach([50,100,250,500,1000] as $radius){
 $id=1400+$radius;$location=area_seed($id,'approximate_area',$radius);$before=$GLOBALS['private_fixture'];
 $result=$svc->publish($id);check($result===true,'radius-aware publication '.$radius.(is_wp_error($result)?' ERROR='.$result->get_error_code():''));
 $p=get_post_meta($id,'_ba_public_geo');$v=BadAround_Public_Geo_Projection::public_view($id);
 check($p['mode']==='approximate_area'&&$p['radius_meters']===$radius&&$v['radius_m']===$radius,'approved radius exactly reporter choice '.$radius);
 check(abs($p['lat']-$location['exact_lat'])>0.000001||abs($p['lng']-$location['exact_lng'])>0.000001,'public center generalized '.$radius);
 $phi=deg2rad($location['exact_lat']);$dphi=deg2rad($p['lat']-$location['exact_lat']);$dl=deg2rad($p['lng']-$location['exact_lng']);$distance=6378137*2*asin(sqrt(sin($dphi/2)**2+cos($phi)*cos(deg2rad($p['lat']))*sin($dl/2)**2));
 check($distance>1&&$distance<$radius,'source inside approved circle without exact center leak '.$radius);
 check($before===$GLOBALS['private_fixture']&&get_post_meta($id,'_ba_public_location_decision')==='','no request mutation or moderator decision');
 $all=serialize([$GLOBALS['meta'],$GLOBALS['posts'],$GLOBALS['geo_audits']]);check($svc->publish($id)===true&&$all===serialize([$GLOBALS['meta'],$GLOBALS['posts'],$GLOBALS['geo_audits']]),'stable repeat publication '.$radius);
 // Even a correctly re-signed inconsistent radius must not override the user descriptor.
 $proof=get_post_meta($id,'_ba_public_geo_proof');$bad=$p;$bad['radius_meters']=$radius===100?500:100;
 $GLOBALS['meta'][$id]['_ba_public_geo']=$bad;$GLOBALS['meta'][$id]['_ba_public_radius_m']=$bad['radius_meters'];$proof['seal']=BadAround_Public_Geo_Projection::signature($id,$bad,$proof['user_authority'],$proof['source']);$GLOBALS['meta'][$id]['_ba_public_geo_proof']=$proof;
 check(BadAround_Public_Geo_Projection::public_view($id)===null,'signed radius mismatch rejected '.$radius);
}
$l=area_seed(2601,'exact_point');check($svc->publish(2601)===true&&BadAround_Public_Geo_Projection::public_view(2601)['radius_m']===0,'new exact without active radius');
$l['public_location_request']['radius_m']=null;check(BadAround_Public_Geo_Contract::validate_request($l)===true,'explicit exact null accepted');
$l['public_location_request']['radius_m']=500;check(is_wp_error(BadAround_Public_Geo_Contract::validate_request($l)),'exact non-null radius rejected');
foreach([null,0,75,-100,500.5,500.0,'500',true,[],['radius_m'=>500]] as $invalid){$l=area_seed(2602,'approximate_area');$l['public_location_request']['radius_m']=$invalid;check(is_wp_error(BadAround_Public_Geo_Contract::validate_request($l)),'invalid strict radius '.json_encode($invalid));}
$l=area_seed(2603,'approximate_area',500);$l['public_location_request_version']='public-location-request-v3';check(is_wp_error(BadAround_Public_Geo_Contract::validate_request($l)),'unknown version fails closed');
check(BadAround_Public_Geo_Projection::radius('f32-c6')===1000,'historical system radius unchanged');
echo "GEO_AREA_01_PUBLICATION=PASS\n";
