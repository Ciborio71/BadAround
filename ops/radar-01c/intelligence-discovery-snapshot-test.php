<?php
declare(strict_types=1);
require __DIR__.'/intelligence-discovery-snapshot.php';
require __DIR__.'/intelligence-public-report-summary.php';
function verifySnapshot(bool $condition,string $name):void{if(!$condition)throw new RuntimeException($name);}
$a=['id'=>11,'event_type'=>['name'=>'Veicolo rubato'],'territory'=>['name'=>'Roma'],'occurred_date'=>''];
$b=['id'=>12,'event_type'=>['name'=>'Veicolo rubato'],'territory'=>['name'=>'Pomezia'],'occurred_date'=>'2026-10-22'];
$one=['items'=>[$a],'total'=>2,'page'=>1,'pages'=>2];
$two=['items'=>[$b],'total'=>2,'page'=>2,'pages'=>2];
$result=BadAround_Intelligence_Discovery_Snapshot::assemble([$two,$one]);
$s=BadAround_Intelligence_Public_Report_Summary::build($result,new DateTimeImmutable('2026-10-28'));
verifySnapshot($s['snapshot_complete']&&$s['observed_reports']===2,'Complete two-page feed');
$errors=[[$one],[$one,$one],[$one,array_merge($two,['total'=>3])],[$one,array_merge($two,['items'=>[$a]])]];
foreach($errors as $i=>$chunks){
 try{BadAround_Intelligence_Discovery_Snapshot::assemble($chunks);throw new RuntimeException('Accepted invalid fixture '.$i);}
 catch(InvalidArgumentException $e){}
}
verifySnapshot($result['snapshot_consistency']==='metadata_only_not_transactional','No atomic snapshot claim');
echo "INTELLIGENCE-01F paginated snapshot gate PASS\n";
