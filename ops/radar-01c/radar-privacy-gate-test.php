<?php
declare(strict_types=1);
require __DIR__.'/radar-privacy-gate.php';
$now=new DateTimeImmutable('2026-10-27',new DateTimeZone('Europe/Rome'));
$base=['publication_status'=>'published','provenance'=>'real_verified',
'approved_public_area'=>'AREA-TEST-001','cell_safe_for_publication'=>true,
'public_geo_mode'=>'approximate_area','area_assignment_audited'=>true,
'geometry_precision_verified'=>true,'occurred_date'=>'2026-10-25'];
$data=[];
for($i=0;$i<5;$i++)$data[]=array_merge($base,['verified_episode_id'=>'episode-'.$i]);
$one=BadAround_Radar_Privacy_Gate::aggregate($data,['area_id'=>'AREA-TEST-001'],$now);
if($one['coverage']!=='candidate_cohort_only'||$one['heat_cells']!==[]||isset($one['count']))throw new RuntimeException('Cohort leak');
foreach ([['area_id'=>'AREA-TEST-001','category'=>'veicoli'],['area_id'=>'AREA-TEST-001','radius_km'=>1],['area_id'=>'AREA-TEST-001','start_date'=>'2026-10-20'],['lat'=>41.9,'lng'=>12.5]] as $query) {
 try {BadAround_Radar_Privacy_Gate::aggregate($data,$query,$now);throw new RuntimeException('Query bypass');}
 catch(InvalidArgumentException $e){}
}
$data[]=array_merge($base,['verified_episode_id'=>'episode-0']);
$data[]=array_merge($base,['verified_episode_id'=>'synthetic','provenance'=>'qa_fixture']);
$data[]=array_merge($base,['verified_episode_id'=>'invalid-geo','geometry_precision_verified'=>false]);
$data[]=array_merge($base,['verified_episode_id'=>'missing-date','occurred_date'=>'']);
$two=BadAround_Radar_Privacy_Gate::aggregate($data,['area_id'=>'AREA-TEST-001'],$now);
if($two!==$one)throw new RuntimeException('Excluded events affect output');
$small=BadAround_Radar_Privacy_Gate::aggregate(array_slice($data,0,4),['area_id'=>'AREA-TEST-001'],$now);
if($small['coverage']!=='insufficient_data')throw new RuntimeException('Small cohort disclosed');
echo "RADAR-DATA-04 synthetic privacy facade: PASS\\n";
