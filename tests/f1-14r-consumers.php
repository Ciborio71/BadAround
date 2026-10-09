<?php
/* F1.14R-S8: executable discovery geometry contract + territorial and map source invariants.
 * Synthetic test only. Does not access real WordPress or staging fixtures.
 */
require __DIR__ . '/f1-14r-publication-contract.php';
require dirname(__DIR__) . '/wordpress/plugins/badaround-core/includes/class-badaround-discovery-query.php';

function get_the_terms($id,$tax) { return wp_get_post_terms($id,$tax); }
$reflection = new ReflectionClass('BadAround_Discovery_Query');
$discovery = $reflection->newInstanceWithoutConstructor();
$geo_method = $reflection->getMethod('public_geo');
$geo_method->setAccessible(true);

$event=201;
seed($event,4,'f16-c8');
get_post($event)->post_status='publish';
$GLOBALS['meta'][$event]['_ba_moderation_status']='published';
check(null === $geo_method->invoke($discovery,$event),'consumer 1 no coordinates yields null geometry');
check('publish'===get_post($event)->post_status,'consumer 1 public nonspatial status preserved');
check('published'===get_post_meta($event,'_ba_moderation_status'),'consumer 1 moderation state preserved');
$GLOBALS['term_meta'][4]=['_ba_center_lat'=>41.6,'_ba_center_lng'=>12.5,'_ba_geo_verified'=>''];
check(null === $geo_method->invoke($discovery,$event),'consumer 1 rejects unverified center');
$GLOBALS['term_meta'][4]['_ba_geo_verified']='1';
$verified = $geo_method->invoke($discovery,$event);
check(is_array($verified) && $verified['source']==='territory_center' && $verified['radius_m']>=100,'consumer 1 verified centroid explicitly allowed');
$GLOBALS['term_meta'][4]=[];
$with_coords=202;
seed($with_coords,4,'f16-c4','2026-10-08',['_ba_public_lat'=>41.6,'_ba_public_lng'=>12.5,'_ba_public_radius_m'=>150]);
$point=$geo_method->invoke($discovery,$with_coords);
check(is_array($point)&&$point['source']==='event_public'&&$point['lat']===41.6&&$point['lng']===12.5,'consumer 1 existing public coordinates preserved');

$layout=file_get_contents(dirname(__DIR__).'/wordpress/themes/badaround-child/template-parts/territory-layout.php');
check(str_contains($layout,"'post_status'         => 'publish'") &&
 str_contains($layout,"'_ba_moderation_status'") &&
 str_contains($layout,"'tax_query'") &&
 !str_contains($layout,"'_ba_public_lat'") &&
 !str_contains($layout,"'_ba_public_lng'"),'consumer 2 territory query accepts published coordinate-less event');
check(!preg_match('/if\s*\(\s*!\s*\$when\s*\)\s*\{\s*\$when\s*=\s*get_the_date\(\)/',$layout),
 'consumer 2 does not fabricate occurred date');
$map=file_get_contents(dirname(__DIR__).'/wordpress/themes/badaround-child/assets/js/map.js');
check(str_contains($map,'item.public_geo') && str_contains($map,'if (!geo ||'),
 'map skips null public_geo in item-marker iteration');
echo "F1_14R_CONSUMERS=PASS\n";
