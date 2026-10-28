<?php
declare(strict_types=1);
require __DIR__.'/intelligence-aggregator.php';
$now=new DateTimeImmutable('2026-10-28',new DateTimeZone('Europe/Rome'));
$base=['publication_status'=>'published','source_quality'=>'verified_real','region'=>'Lazio',
 'province'=>'Roma','municipality'=>'Pomezia','category'=>'Veicoli',
 'phenomenon'=>'Veicolo rubato','occurred_date'=>'2026-10-20'];
$events=[
 $base+['canonical_episode_id'=>'episode-1'],
 $base+['canonical_episode_id'=>'episode-1'], // confirmed duplicate
 array_merge($base,['canonical_episode_id'=>'episode-2','occurred_date'=>null]),
 array_merge($base,['canonical_episode_id'=>'episode-3','region'=>'Piemonte','province'=>'Torino','municipality'=>'Orbassano','occurred_date'=>'2026-10-21']),
 array_merge($base,['canonical_episode_id'=>'episode-test','source_quality'=>'qa_fixture']),
 array_merge($base,['canonical_episode_id'=>'episode-draft','publication_status'=>'draft']),
 array_merge($base,['canonical_episode_id'=>'episode-future','occurred_date'=>'2027-01-01']),
];
$r=BadAround_Intelligence_Aggregator::summarize($events,$now);
function assertSame($a,$b,$name){if($a!==$b)throw new RuntimeException($name.': '.json_encode($a).' != '.json_encode($b));}
assertSame(4,$r['total_episodes'],'count');
assertSame(2,$r['dated_episodes'],'dated');
assertSame(2,$r['undated_episodes'],'undated/future');
assertSame(3,$r['by_region']['Lazio'],'region Lazio');
assertSame(1,$r['by_region']['Piemonte'],'region Piemonte');
assertSame(1,$r['excluded']['duplicate'],'duplicate');
assertSame(1,$r['excluded']['unverified'],'QA exclusion');
assertSame(1,$r['excluded']['unpublished'],'draft exclusion');
assertSame(null,$r['heatmap'],'heatmap not certified');
assertSame([],$r['alerts'],'no fabricated alerts');
$reverse=BadAround_Intelligence_Aggregator::summarize(array_reverse($events),$now);
assertSame($r['total_episodes'],$reverse['total_episodes'],'stable total');
echo "INTELLIGENCE-01 synthetic aggregation PASS\n";
