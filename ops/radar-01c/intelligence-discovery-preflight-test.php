<?php
declare(strict_types=1);
require __DIR__.'/intelligence-discovery-preflight.php';
function ok(bool $a,string $m):void{if(!$a)throw new RuntimeException($m);}
$base=['id'=>1,'title'=>'Furto auto','event_type'=>['name'=>'Veicolo rubato'],
 'territory'=>['name'=>'Roma'],'occurred_date'=>'','public_geo'=>[
 'source'=>'event_public','version'=>'public-location/v1','mode'=>'approximate_area','lat'=>41.9,'lng'=>12.5]];
$one=$base;
$two=$base;$two['id']=2;$two['occurred_date']='2026-10-22';$two['public_geo']=null;
$three=$base;$three['id']=3;$three['title']='[QA sintetica] Nessun evento reale';
$r=BadAround_Intelligence_Discovery_Preflight::inspect(['items'=>[$one,$two,$three]],new DateTimeImmutable('2026-10-28'));
ok($r['sample_events']===3&&$r['geolocated']===2,'geo count');
ok($r['dated']===1&&$r['dated_and_geolocated']===0,'time geo split');
ok($r['qa_title_suspects']===1,'QA identified');
ok($r['usable_for_incident_counts']===false&&!$r['is_complete_snapshot'],'fail closed');
try{BadAround_Intelligence_Discovery_Preflight::inspect(['bad'=>'shape'],new DateTimeImmutable('2026-10-28'));throw new RuntimeException('bad shape admitted');}
catch(InvalidArgumentException $e){}
echo "INTELLIGENCE-01D Discovery preflight PASS\n";
