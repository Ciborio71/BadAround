<?php
declare(strict_types=1);
require __DIR__.'/intelligence-projection-adapter.php';
require __DIR__.'/intelligence-aggregator.php';
function must(bool $v,string $m): void {if(!$v)throw new RuntimeException($m);}
$e=['id'=>12,'occurred_date'=>'2026-10-22','public_geo'=>['source'=>'event_public','version'=>'public-location/v1','mode'=>'approximate_area']];
$authority=[12=>['publication_status'=>'published','source_quality'=>'verified_real','public_projection_approved'=>true,'taxonomy_verified'=>true,'episode_identity_verified'=>true,
 'canonical_episode_id'=>'episode-trusted-12','territory_path'=>['Lazio','Roma','Pomezia'],'category'=>'Veicoli','phenomenon'=>'Veicolo rubato']];
$r=BadAround_Intelligence_Projection_Adapter::batch([$e,$e],$authority);
must($r['rejected']===0,'admitted authorized');
$summary=BadAround_Intelligence_Aggregator::summarize($r['normalized'],new DateTimeImmutable('2026-10-28'));
must($summary['total_episodes']===1,'verified canonical episode dedup');
$unverified=$authority;$unverified[12]['episode_identity_verified']=false;
must(BadAround_Intelligence_Projection_Adapter::normalize($e,$unverified)===null,'no unverified IDs');
$spoof=$e;$spoof['id']=13;$spoof['source_quality']='verified_real';
must(BadAround_Intelligence_Projection_Adapter::normalize($spoof,$authority)===null,'no payload spoof');
$secret=$e;$secret['private_geo']=['lat'=>41.0,'lng'=>12.0];
must(!array_key_exists('private_geo',BadAround_Intelligence_Projection_Adapter::normalize($secret,$authority)),'no private pass through');
$bad=$e;$bad['public_geo']['source']='private_location';
must(BadAround_Intelligence_Projection_Adapter::normalize($bad,$authority)===null,'reject private source');
$bad=$e;$bad['public_geo']['mode']='unknown';
must(BadAround_Intelligence_Projection_Adapter::normalize($bad,$authority)===null,'reject unknown mode');
echo "INTELLIGENCE-01B projection adapter PASS\n";
