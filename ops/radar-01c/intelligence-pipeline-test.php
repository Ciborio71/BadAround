<?php
declare(strict_types=1);
require __DIR__.'/intelligence-pipeline.php';
$p=['id'=>1,'occurred_date'=>'2026-10-22',
 'public_geo'=>['source'=>'event_public','version'=>'public-location/v1','mode'=>'approximate_area']];
$a=[1=>['publication_status'=>'published','source_quality'=>'verified_real','public_projection_approved'=>true,
'taxonomy_verified'=>true,'episode_identity_verified'=>true,'canonical_episode_id'=>'canonical-1',
'territory_path'=>['Lazio','Roma','Pomezia'],'category'=>'Veicoli','phenomenon'=>'Veicolo rubato']];
$out=BadAround_Intelligence_Pipeline::build([$p,$p],$a,new DateTimeImmutable('2026-10-28'),[4,3,4,3,4,3,4,3,18]);
if($out['aggregation']['total_episodes']!==1 || $out['aggregation']['excluded']['duplicate']!==1
 || $out['anomaly_review']['status']!=='candidate_for_review'
 || $out['anomaly_review']['public_alert']!==false
 || $out['release_status']!=='synthetic_only_not_for_publication')throw new RuntimeException('Pipeline contract');
$untrusted=BadAround_Intelligence_Pipeline::build([$p],[],new DateTimeImmutable('2026-10-28'));
if($untrusted['projection_rejected']!==1||$untrusted['aggregation']['total_episodes']!==0)throw new RuntimeException('Fail closed');
echo "INTELLIGENCE-01C offline pipeline PASS\n";
