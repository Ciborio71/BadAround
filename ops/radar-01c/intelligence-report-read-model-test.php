<?php
declare(strict_types=1);
require __DIR__.'/intelligence-report-read-model.php';
$a=['id'=>100,'title'=>'Veicolo rubato','event_type'=>['name'=>'Veicolo rubato'],'territory'=>['name'=>'Roma'],'occurred_date'=>'','public_geo'=>null];
$b=['id'=>101,'title'=>'Veicolo rubato','event_type'=>['name'=>'Veicolo rubato'],'territory'=>['name'=>'Torino'],'occurred_date'=>'2026-10-21','public_geo'=>null];
$p=[['items'=>[$a],'page'=>1,'pages'=>2,'total'=>2],['items'=>[$b],'page'=>2,'pages'=>2,'total'=>2]];
$r=BadAround_Intelligence_Report_Read_Model::fromPages($p,new DateTimeImmutable('2026-10-28'));
if($r['totals']['observed_reports']!==2||$r['quality']['dated']!==1||$r['quality']['geolocated']!==0||
  $r['public_alerts']!==[]||$r['heatmap_cells']!==[]||$r['source_is_transactionally_consistent']!==false)
 throw new RuntimeException('read model contract');
try{BadAround_Intelligence_Report_Read_Model::fromPages([$p[0]],new DateTimeImmutable('2026-10-28'));throw new RuntimeException('accepted incomplete');}
catch(InvalidArgumentException $ok){}
echo "INTELLIGENCE-01G offline read model PASS\n";
