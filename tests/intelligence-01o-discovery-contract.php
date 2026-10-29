<?php
/** INTELLIGENCE-01O: CI-validated regression using a sanitized public Discovery-shaped snapshot. */
declare(strict_types=1);
define('ABSPATH', '/synthetic-test/');
function wp_timezone(): DateTimeZone { return new DateTimeZone('Europe/Rome'); }
class BadAround_Discovery_Query {
 public int $calls=0;
 public function discover(array $filters):array {
  $this->calls++;
  $types=['Veicolo rubato'=>6,'Rifiuti abbandonati o discarica'=>9,
   'Fumo Incendio O Situazione Anomala'=>1,'Danno O Incidente Durante La Sosta'=>2,
   'Ostacolo Pericoloso Sulla Carreggiata'=>3,'Albero, ramo o elemento pericolante'=>1];
  $areas=['Roma'=>10,'Orbassano'=>1,'Pomezia'=>5,'Torvaianica'=>6];
  $dates=['10/10/2026','07/10/2026','05/10/2026','04/10/2026','22/09/2026'];
  $t=[];foreach($types as $name=>$n)for($i=0;$i<$n;$i++)$t[]=$name;
  $a=[];foreach($areas as $name=>$n)for($i=0;$i<$n;$i++)$a[]=$name;
  $items=[];for($i=0;$i<22;$i++)$items[]=[
   'id'=>$i+1,'event_type'=>['name'=>$t[$i]],
   'territory'=>['name'=>$a[$i]],'occurred_date'=>$dates[$i]??''
  ];
  return ['items'=>$items,'total'=>22,'page'=>1,'pages'=>1];
 }
}
require __DIR__.'/../wordpress/plugins/badaround-core/includes/class-badaround-intelligence-read-model.php';
$service=new BadAround_Discovery_Query();
$results=BadAround_Intelligence_Read_Model::snapshot($service);
if($service->calls!==2||$results['report_count']!==22||$results['occurred_date_available']!==5)
 throw new RuntimeException('Report/date totals inconsistent');
foreach(['Veicolo rubato'=>6,'Rifiuti abbandonati o discarica'=>9] as $name=>$expected) {
 if(($results['by_event_type'][$name]??null)!==$expected)throw new RuntimeException('Type counts inconsistent');
}
foreach(['Roma'=>10,'Orbassano'=>1,'Pomezia'=>5,'Torvaianica'=>6] as $name=>$expected) {
 if(($results['by_territory_label'][$name]??null)!==$expected)throw new RuntimeException('Territory counts inconsistent');
}
if($results['certified_episode_count']!==false||$results['transactional_snapshot']!==false||$results['public_alerts']!==[])
 throw new RuntimeException('Descriptive results misrepresented');
echo "INTELLIGENCE-01O real-shaped public Discovery contract PASS\n";
