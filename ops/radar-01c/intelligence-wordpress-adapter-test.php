<?php
declare(strict_types=1);
// Minimal WordPress boundary stub: no database or live publication actions.
function wp_timezone():DateTimeZone{return new DateTimeZone('Europe/Rome');}
class BadAround_Discovery_Query {
 public array $requests=[];
 public function discover(array $filters):array {
  $this->requests[]=$filters;
  return ['items'=>[['id'=>333,'title'=>'Segnalazione pubblica',
    'event_type'=>['name'=>'Veicolo rubato'],'territory'=>['name'=>'Roma'],
    'occurred_date'=>'','public_geo'=>null]],'total'=>1,'page'=>$filters['page'],'pages'=>1];
 }
}
require __DIR__.'/intelligence-wordpress-adapter.php';
$service=new BadAround_Discovery_Query();
$result=BadAround_Intelligence_WordPress_Adapter::describePublishedReports($service);
if($result['runtime']!=='canonical_wordpress_discovery'||
   $result['totals']['observed_reports']!==1||
   $result['totals']['certified_incident_counts']!==false||
   count($service->requests)!==2)
 throw new RuntimeException('WordPress integration contract');
try{BadAround_Intelligence_WordPress_Adapter::describePublishedReports(new stdClass());
 throw new RuntimeException('Accepted foreign service');}
catch(InvalidArgumentException $ok){}
echo "INTELLIGENCE-01I WordPress integration seam PASS\n";
