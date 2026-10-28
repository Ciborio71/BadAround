<?php
declare(strict_types=1);
require __DIR__.'/intelligence-discovery-bridge.php';
$events=[];
for($i=1;$i<=103;$i++)$events[]=['id'=>$i,'title'=>'Evento',
 'event_type'=>['name'=>'Veicolo rubato'],'territory'=>['name'=>'Roma'],
 'occurred_date'=>'','public_geo'=>null];
$pagesCalled=[];
$provider=static function(array $filters)use($events,&$pagesCalled):array{
 $pagesCalled[]=$filters['page'];
 return ['items'=>array_slice($events,($filters['page']-1)*100,100),
   'total'=>count($events),'page'=>$filters['page'],'pages'=>2];
};
$out=BadAround_Intelligence_Discovery_Bridge::build($provider,new DateTimeImmutable('2026-10-28'));
if($pagesCalled!==[1,2,1,2]||$out['totals']['observed_reports']!==103||
 $out['totals']['snapshot_complete']!==true||
 $out['source_is_transactionally_consistent']!==false)throw new RuntimeException('bridge contract');
try{BadAround_Intelligence_Discovery_Bridge::build(static fn(array $f):array=>
 ['items'=>[],'total'=>2200,'page'=>1,'pages'=>22],new DateTimeImmutable('2026-10-28'));
 throw new RuntimeException('Unbounded read accepted');}catch(RuntimeException $expected){}
try{BadAround_Intelligence_Discovery_Bridge::build(static fn(array $f):array=>
 ['items'=>[],'total'=>1,'page'=>$f['page'],'pages'=>1],new DateTimeImmutable('2026-10-28'));
 throw new RuntimeException('Incomplete read accepted');}catch(InvalidArgumentException $expected){}
$counter=0;
try{
 BadAround_Intelligence_Discovery_Bridge::build(static function(array $filters)use(&$counter):array{
  $counter++;
  return ['items'=>[['id'=>$counter===1?42:43,'event_type'=>['name'=>'Veicolo rubato'],
     'territory'=>['name'=>'Roma'],'occurred_date'=>'','public_geo'=>null]],
     'page'=>1,'pages'=>1,'total'=>1];
 },new DateTimeImmutable('2026-10-28'));
 throw new RuntimeException('Concurrent drift accepted');
}catch(RuntimeException $expected){
 if($expected->getMessage()==='Concurrent drift accepted')throw $expected;
}
echo "INTELLIGENCE-01H read-only bridge PASS\n";
