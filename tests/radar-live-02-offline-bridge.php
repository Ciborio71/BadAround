<?php
declare(strict_types=1);
define('ABSPATH',__DIR__);
class BadAround_Discovery_Query {
 public function discover($filters) {
  $items=array(
   array('id'=>1,'occurred_date'=>'2026-10-29','territory'=>array('slug'=>'roma-roma'),'event_type'=>array('slug'=>'veicolo-rubato'),'excerpt'=>'not-to-share','public_geo'=>array('lat'=>41.9)),
   array('id'=>2,'occurred_date'=>'','territory'=>array('slug'=>'roma-roma'),'event_type'=>array('slug'=>'veicolo-rubato')),
   array('id'=>3,'occurred_date'=>'2026-10-20','territory'=>array('slug'=>'roma-roma'),'event_type'=>array('slug'=>'veicolo-rubato'))
  );
  return array('items'=>$items,'total'=>3,'pages'=>1,'page'=>1);
 }
}
$dir=dirname(__DIR__).'/wordpress/plugins/badaround-core/includes/';
foreach(array('radar-canonical-adapter','intelligence-trends','intelligence-peak-candidates',
 'intelligence-data-quality','radar-view-model','radar-offline-bridge') as $component) {
 require_once $dir.'class-badaround-'.$component.'.php';
}
$result=BadAround_Radar_Offline_Bridge::snapshot(new BadAround_Discovery_Query(),
 new DateTimeImmutable('2026-10-29',new DateTimeZone('Europe/Rome')),7);
if ($result['public_exposure_enabled']!==false || $result['view']['internal_preview_only']!==true) throw new RuntimeException('Exposure');
if ($result['quality']['undated_or_invalid_count']!==1) throw new RuntimeException('Unknown event date');
if ($result['view']['cards'][0]['percentage_change']!==null) throw new RuntimeException('Weak comparison visible');
if ($result['view']['cards'][0]['current_reports']!==1) throw new RuntimeException('Current period wrong');
if ($result['view']['public_alerts']!==array()) throw new RuntimeException('Public alert');
echo 'RADAR-LIVE-02: PASS 5 private offline checks'.PHP_EOL;
