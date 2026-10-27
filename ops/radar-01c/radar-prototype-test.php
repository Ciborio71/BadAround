<?php
declare(strict_types=1);
require __DIR__ . '/radar-prototype.php';
$base=['publication_status'=>'published','provenance'=>'real_verified','occurred_date'=>'2026-10-26','category'=>'veicoli','approved_public_cell'=>'RAD-CELL-001','public_geo_mode'=>'approximate_area','cell_safe_for_publication'=>true];
$events=[];
for($i=1;$i<=3;$i++) $events[]=array_merge($base,['verified_episode_id'=>'incident-'.$i]);
$events[]=array_merge($base,['verified_episode_id'=>'incident-1']); // duplicate
$events[]=array_merge($base,['provenance'=>'qa_fixture','verified_episode_id'=>'qa']);
$events[]=array_merge($base,['occurred_date'=>'','verified_episode_id'=>'unknown']);
$events[]=array_merge($base,['approved_public_cell'=>null,'verified_episode_id'=>'unmapped']);
$now=new DateTimeImmutable('2026-10-27',new DateTimeZone('Europe/Rome'));
$r=BadAround_Radar_Prototype::analyze($events,['period_days'=>30,'radius_km'=>1,'category'=>'all'],$now);
if(count($r['heat_cells'])!==1 || $r['heat_cells'][0]['count']!==3 || $r['alerts']!==[]) throw new RuntimeException('Failed eligible/dedupe/suppression');
if($r['exclusion_summary']!==['test'=>1,'date'=>1,'geo'=>1,'duplicate'=>1,'status'=>0]) throw new RuntimeException('Failed exclusion tracking');
$small=BadAround_Radar_Prototype::analyze(array_slice($events,0,2),[], $now);
if($small['coverage']!=='insufficient_data'||$small['heat_cells']!==[]) throw new RuntimeException('Failed minimum cohort');
$bad=array_merge($base,['occurred_date'=>'2026-02-30']);
$reject=BadAround_Radar_Prototype::analyze([$bad],[], $now);
if($reject['heat_cells']!==[])throw new RuntimeException('Invalid date accepted');
try { BadAround_Radar_Prototype::analyze([],['radius_km'=>25],$now); throw new RuntimeException('Invalid radius accepted'); }
catch(InvalidArgumentException $e) {}
echo "RADAR-01C synthetic tests: PASS\\n";
