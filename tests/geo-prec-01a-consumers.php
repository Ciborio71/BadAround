<?php
/** Real PHP template + real public seal reader; isolated replay, never staging writes. */
require __DIR__ . '/geo-prec-01-publication.php';
require_once dirname(__DIR__) . '/wordpress/plugins/badaround-core/includes/class-badaround-discovery-query.php';
function the_post() {}
function get_the_ID() { return $GLOBALS['render_id']; }
function get_the_title() { return 'Synthetic public consumer QA'; }
function get_the_terms($id,$tax) { return wp_get_post_terms($id,$tax); }
function get_post_field($field,$id) { return get_post($id)->$field ?? ''; }
function home_url($path) { return 'https://example.invalid'.$path; }
function esc_html($s) { return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8'); }
function esc_attr($s) { return esc_html($s); }
function esc_url($s) { return esc_html($s); }
function wp_trim_words($s,$n,$more='') { return $s; }
function the_content() { echo esc_html(get_post_field('post_content',get_the_ID())); }
function get_the_post_thumbnail_url() { return ''; }
class ConsumerDB extends Geo05DB {
 public $classification=0,$queries=[];
 function get_var($sql) {
  $this->queries[]=$sql;
  if(!str_contains($sql,'SELECT CASE WHEN JSON_VALID(content_original) THEN JSON_CONTAINS_PATH'))throw new Exception('Unexpected private data query');
  return $this->classification;
 }
 function get_row($sql) { throw new Exception('Public consumers must not fetch private report rows'); }
}
function replay($id,$mode,$governed,$lat,$lng,$radius) {
 g5seed($id,$mode,$mode);
 $GLOBALS['posts'][$id]->post_status='publish';
 $GLOBALS['meta'][$id]['_ba_moderation_status']='published';
 $p=['version'=>'public-location/v1','mode'=>$mode,'lat'=>$lat,'lng'=>$lng,'radius_meters'=>$radius];
 $d=$governed ? ['governance'=>'user-public-location/v1','version'=>'public-location/v1','mode'=>$mode] : get_post_meta($id,'_ba_public_location_decision');
 // Use the production descriptor shape, not an invented governance contract.
 if($governed) {
  $d=BadAround_Public_Geo_Contract::user_authority(['public_geo_governance'=>'user-public-location/v1','canonical'=>['location'=>['public_location_request'=>$mode,'public_location_request_version'=>'public-location-request-v1']]]);
  unset($GLOBALS['meta'][$id]['_ba_public_location_decision']);
 }
 $proof=['source'=>'synthetic-source-hmac','seal'=>BadAround_Public_Geo_Projection::signature($id,$p,$d,'synthetic-source-hmac')];
 if($governed)$proof['user_authority']=$d;
 $GLOBALS['meta'][$id]['_ba_public_geo']=$p;$GLOBALS['meta'][$id]['_ba_public_geo_proof']=$proof;
 foreach(['lat','lng','radius_meters'] as $k)$GLOBALS['meta'][$id][['_ba_public_lat','_ba_public_lng','_ba_public_radius_m'][array_search($k,['lat','lng','radius_meters'])]]=$p[$k];
}
function render_consumer($id,$template=null) {
 $GLOBALS['render_id']=$id;$before=serialize([$GLOBALS['meta'],$GLOBALS['posts'],$GLOBALS['geo_audits']]);
 ob_start();include ($template ?? dirname(__DIR__).'/wordpress/themes/badaround-child/template-parts/single-event-layout.php');$html=ob_get_clean();
 check($before===serialize([$GLOBALS['meta'],$GLOBALS['posts'],$GLOBALS['geo_audits']]),'template does not mutate any fixture/meta/audit');
 foreach(['Private Address 99','synthetic-source-hmac','user_authority','exact_address','exact_lat','exact_lng'] as $secret)check(!str_contains($html,$secret),'no private/proof exposure '.$secret);
 return $html;
}
$wpdb=new ConsumerDB();
$discovery=new BadAround_Discovery_Query();$reader=new ReflectionMethod($discovery,'public_geo');
// Public geometry copied read-only from staging, 2026-10-23. Test-only seals use wp_salt=test.
foreach([[352,'exact_point',true,41.6208388,12.4626387,0],[354,'approximate_area',true,41.62611,12.459279,1000],[303,'exact_point',false,41.8991622,12.4730719,0],[304,'approximate_area',false,41.89553,12.479214,1000]] as [$id,$mode,$gov,$lat,$lng,$radius]) {
 replay($id,$mode,$gov,$lat,$lng,$radius);$h=render_consumer($id);
 check(str_contains($h,'data-ba-event-mode="'.$mode.'"')&&str_contains($h,'data-ba-event-radius="'.$radius.'"'),'real template correct mode/radius #'.$id);
 $label=$mode==='exact_point'?'Posizione esatta':'Posizione approssimativa';
 check(str_contains($h,'Mappa: '.$label)&&str_contains($h,'<small>'.$label.'</small>'),'real template copy and map label #'.$id);
 if($mode==='exact_point')check(!str_contains($h,'Posizione approssimativa'),'no approximate fallback for exact #'.$id);
 $geo=$reader->invoke($discovery,$id);check($geo['mode']===$mode&&$geo['radius_meters']===$radius&&$geo['lat']===$lat&&$geo['lng']===$lng,'discovery same sealed projection #'.$id);
}
foreach(['missing_projection','unknown_authority','source_mismatch','mirror_mismatch','injected_decision','missing_all_public_metadata','unknown_envelope_no_projection','unpublished'] as $case) {
 replay(352,'exact_point',true,41.6208388,12.4626387,0);$wpdb->classification=0;
 switch($case) {
 case 'missing_projection':unset($GLOBALS['meta'][352]['_ba_public_geo']);break;
 case 'unknown_authority':$GLOBALS['meta'][352]['_ba_public_geo_proof']['user_authority']['authority']='unknown';break;
 case 'source_mismatch':$GLOBALS['meta'][352]['_ba_public_geo_proof']['source']='tampered';break;
 case 'mirror_mismatch':$GLOBALS['meta'][352]['_ba_public_lat']=42;break;
 case 'injected_decision':$GLOBALS['meta'][352]['_ba_public_location_decision']=['mode'=>'approximate_area'];break;
 case 'missing_all_public_metadata':case 'unknown_envelope_no_projection':
  unset($GLOBALS['meta'][352]['_ba_public_geo'],$GLOBALS['meta'][352]['_ba_public_geo_proof']);
  $GLOBALS['meta'][352]['_ba_public_radius_m']=1000;$wpdb->classification=1;break;
 case 'unpublished':$GLOBALS['posts'][352]->post_status='pending';break;
 }
 $h=render_consumer(352);check(!str_contains($h,'data-ba-map-context="detail"')&&!str_contains($h,'Posizione approssimativa')&&str_contains($h,'Zona territoriale'),'detail fail closed '.$case);
 check($reader->invoke($discovery,352)===null,'discovery fail closed '.$case);
}
$wpdb->classification=0;seed(269);$GLOBALS['posts'][269]->post_status='publish';$GLOBALS['meta'][269]['_ba_moderation_status']='published';
$h=render_consumer(269);check(!str_contains($h,'data-ba-map-context="detail"')&&str_contains($h,'Zona territoriale')&&$reader->invoke($discovery,269)===null,'historical territory-only unchanged');
seed(290,4,'f16-c8','',['_ba_public_lat'=>41.866107,'_ba_public_lng'=>12.436241,'_ba_public_radius_m'=>1000]);$GLOBALS['posts'][290]->post_status='publish';
$h=render_consumer(290);check(str_contains($h,'Posizione approssimativa')&&str_contains($h,'data-ba-event-mode=""')&&$reader->invoke($discovery,290)['radius_m']===1000,'pre-versioned unmarked legacy mirror behavior unchanged');
// Compare complete historical HTML with the exact pre-remediation template.
$baseline=tempnam(sys_get_temp_dir(),'geo-prec-01a-');
try {
 $old=shell_exec('git show 48bab2229c00fb28f01d54117dd1278074f10d69:wordpress/themes/badaround-child/template-parts/single-event-layout.php');
 check(is_string($old)&&str_contains($old,'<?php'),'baseline template loaded');file_put_contents($baseline,$old);
 foreach([[303,'exact_point',41.8991622,12.4730719,0],[304,'approximate_area',41.89553,12.479214,1000]] as [$id,$mode,$lat,$lng,$radius]) {
  replay($id,$mode,false,$lat,$lng,$radius);
  check(render_consumer($id)===render_consumer($id,$baseline),'historical complete HTML byte-identical #'.$id);
 }
 check(render_consumer(269)===render_consumer(269,$baseline),'territory-only complete HTML byte-identical');
 check(render_consumer(290)===render_consumer(290,$baseline),'pre-versioned legacy complete HTML byte-identical');
 replay(352,'exact_point',true,41.6208388,12.4626387,0);
 $bad=render_consumer(352,$baseline);$fixed=render_consumer(352);
 check(str_contains($bad,'data-ba-event-mode=""')&&str_contains($bad,'Posizione approssimativa')&&str_contains($fixed,'data-ba-event-mode="exact_point"'),'original staging defect reproduced and prevented through real template');
} finally { unlink($baseline); }
echo "GEO_PREC_01A_REAL_TEMPLATE_AND_DISCOVERY=PASS\n";
