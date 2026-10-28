<?php
declare(strict_types=1);
define('ABSPATH','/test/');
function wp_timezone():DateTimeZone{return new DateTimeZone('Europe/Rome');}
class BadAround_Discovery_Query {
 public int $calls=0;
 public bool $mutate=false;
 public function discover(array $filters):array {
  $this->calls++;
  $type=$this->mutate && $this->calls>1?'Veicolo danneggiato':'Veicolo rubato';
  return ['items'=>[['id'=>123,'event_type'=>['name'=>$type],
   'territory'=>['name'=>'Roma'],'occurred_date'=>'2026-10-22']],
   'total'=>1,'page'=>$filters['page'],'pages'=>1];
 }
}
require __DIR__.'/../../wordpress/plugins/badaround-core/includes/class-badaround-intelligence-read-model.php';
$d=new BadAround_Discovery_Query();
$s=BadAround_Intelligence_Read_Model::snapshot($d);
if($d->calls!==2||$s['report_count']!==1||$s['occurred_date_available']!==1||
   $s['certified_episode_count']!==false||$s['public_alerts']!==[])
 throw new RuntimeException('Read model failure');
$d=new BadAround_Discovery_Query();$d->mutate=true;
try{BadAround_Intelligence_Read_Model::snapshot($d);throw new RuntimeException('Drift accepted');}
catch(RuntimeException $e){if($e->getMessage()==='Drift accepted')throw $e;}
echo "INTELLIGENCE-01J WordPress Core passive read model PASS\n";
