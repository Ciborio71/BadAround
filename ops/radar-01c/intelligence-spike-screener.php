<?php
/** INTELLIGENCE-02 — synthetic-only descriptive spike screening. No public alerts. */
declare(strict_types=1);
final class BadAround_Intelligence_Spike_Screener {
 public static function inspect(array $weeklyCounts): array {
  // Exactly 9 sequential calendar buckets, previous eight as baseline and final as current.
  // A sparse series cannot justify relative anomaly claims.
  if(count($weeklyCounts)!==9)return ['status'=>'insufficient_history','public_alert'=>false];
  foreach($weeklyCounts as $n)if(!is_int($n)||$n<0)return ['status'=>'invalid_input','public_alert'=>false];
  $baseline=array_slice($weeklyCounts,0,8);$current=$weeklyCounts[8];
  $sum=array_sum($baseline);
  if($sum<24)return ['status'=>'insufficient_baseline','public_alert'=>false];
  $mean=$sum/8.0;
  $variance=0.0;foreach($baseline as $n)$variance+=($n-$mean)**2;
  $std=sqrt($variance/8.0);
  $threshold=max($mean*1.8,$mean+3.0*max(1.0,$std));
  $candidate=$current>=6&&$current>$threshold;
  return ['status'=>$candidate?'candidate_for_review':'no_anomaly',
   'public_alert'=>false,'baseline_mean'=>round($mean,2),'current'=>$current,
   'threshold'=>round($threshold,2),'window'=>'weekly','review_required'=>$candidate];
 }
}
