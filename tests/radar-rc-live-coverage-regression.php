<?php
/** Synthetic regression for observed public Discovery coverage (17/22 undated). */
declare(strict_types=1);
define('ABSPATH',__DIR__);
class BadAround_Discovery_Query {
 public function discover($args) {
  $rows=[];
  for ($i=1;$i<=22;$i++) {
   $rows[]=['id'=>$i,'occurred_date'=>$i<=17?'':'2026-10-29',
    'territory'=>['slug'=>'roma-roma'],'event_type'=>['slug'=>'veicolo-rubato'],
    'public_geo'=>['lat'=>41.9,'lng'=>12.4],'excerpt'=>'not for analytics'];
  }
  return ['items'=>$rows,'total'=>22,'pages'=>1,'page'=>1];
 }
}
$dir=dirname(__DIR__).'/wordpress/plugins/badaround-core/includes/';
foreach (['radar-canonical-adapter','intelligence-trends','intelligence-peak-candidates',
 'intelligence-data-quality','radar-view-model','radar-offline-bridge'] as $unit) {
 require_once $dir.'class-badaround-'.$unit.'.php';
}
foreach([7,30] as $days) {
 $result=BadAround_Radar_Offline_Bridge::snapshot(
  new BadAround_Discovery_Query(),
  new DateTimeImmutable('2026-10-30',new DateTimeZone('Europe/Rome')),$days);
 $q=$result['quality'];
 if($q['undated_or_invalid_count']!==17 || $q['date_coverage_count']!==5 ||
    $q['date_coverage_ratio']!==round(5/22,4)) throw new RuntimeException('Coverage mismatch');
 foreach($result['view']['cards'] as $card) {
  if($card['comparison_displayable'] || $card['percentage_change']!==null ||
   $card['delta_reports']!==null) throw new RuntimeException('Unreliable comparison leaked');
 }
 if($result['view']['public_alerts']!==[] || $result['public_exposure_enabled']!==false)
  throw new RuntimeException('Public exposure');
 echo "PASS period $days and incomplete-date suppression\n";
}
echo "RADAR-RC-SNAPSHOT: PASS 2 coverage scenarios\n";
