<?php
/** INTELLIGENCE-01N: date-format parity with the live Discovery public projection. */
declare(strict_types=1);
define('ABSPATH','/test/');
function wp_timezone(){return new DateTimeZone('Europe/Rome');}
class BadAround_Discovery_Query {
 public function discover($filters) {
  $dates=array('10/10/2026','07/10/2026','05/10/2026','04/10/2026','22/09/2026','2026-10-22','32/10/2026','2027-10-22','');
  $items=array();
  foreach($dates as $i=>$date) {
   $items[]=array('id'=>$i+1,'event_type'=>array('name'=>'Veicolo rubato'),
    'territory'=>array('name'=>'Roma'),'occurred_date'=>$date);
  }
  return array('items'=>$items,'total'=>count($items),'page'=>1,'pages'=>1);
 }
}
require __DIR__.'/../wordpress/plugins/badaround-core/includes/class-badaround-intelligence-read-model.php';
$r=BadAround_Intelligence_Read_Model::snapshot(new BadAround_Discovery_Query());
if($r['report_count']!==9||$r['occurred_date_available']!==6||
   $r['by_event_type']['Veicolo rubato']!==9||
   $r['certified_episode_count']!==false||$r['public_alerts']!==array()) {
 throw new RuntimeException('Date parity regression: '.json_encode($r));
}
echo "INTELLIGENCE-01N dates DD/MM/YYYY and YYYY-MM-DD PASS\n";
