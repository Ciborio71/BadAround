<?php
/** INTELLIGENCE-01C — offline orchestration, no WordPress hooks / public endpoints. */
declare(strict_types=1);
require_once __DIR__.'/intelligence-projection-adapter.php';
require_once __DIR__.'/intelligence-aggregator.php';
require_once __DIR__.'/intelligence-spike-screener.php';
final class BadAround_Intelligence_Pipeline {
 public static function build(array $publishedSnapshot,array $serverAuthority,DateTimeImmutable $asOf,?array $verifiedNineWeeklyBuckets=null): array {
  $ingest=BadAround_Intelligence_Projection_Adapter::batch($publishedSnapshot,$serverAuthority);
  $summary=BadAround_Intelligence_Aggregator::summarize($ingest['normalized'],$asOf);
  $spike=$verifiedNineWeeklyBuckets===null
   ? ['status'=>'insufficient_history','public_alert'=>false]
   : BadAround_Intelligence_Spike_Screener::inspect($verifiedNineWeeklyBuckets);
  return [
   'pipeline'=>'badaround-intelligence-pipeline/v0.1',
   'projection_rejected'=>$ingest['rejected'],
   'aggregation'=>$summary,
   'anomaly_review'=>$spike,
   'release_status'=>'synthetic_only_not_for_publication'
  ];
 }
}
