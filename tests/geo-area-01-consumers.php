<?php
require __DIR__.'/geo-prec-01a-consumers.php';
foreach([50,100,250,500,1000] as $radius){
 $id=4000+$radius;replay($id,'approximate_area',true,41.62611,12.459279,$radius);
 $authority=BadAround_Public_Geo_Contract::user_authority(['public_geo_governance'=>'user-public-location/v1','canonical'=>['location'=>['public_location_request_version'=>'public-location-request-v2','public_location_request'=>['mode'=>'approximate_area','radius_m'=>$radius]]]]);
 $p=get_post_meta($id,'_ba_public_geo');$proof=get_post_meta($id,'_ba_public_geo_proof');$proof['user_authority']=$authority;$proof['seal']=BadAround_Public_Geo_Projection::signature($id,$p,$authority,$proof['source']);$GLOBALS['meta'][$id]['_ba_public_geo_proof']=$proof;
 $html=render_consumer($id);check(str_contains($html,'data-ba-event-mode="approximate_area"')&&str_contains($html,'data-ba-event-radius="'.$radius.'"'),'actual detail radius '.$radius);
 $geo=$reader->invoke($discovery,$id);check($geo['radius_meters']===$radius&&$geo['mode']==='approximate_area','actual discovery radius '.$radius);
}
echo "GEO_AREA_01_CONSUMERS=PASS\n";
