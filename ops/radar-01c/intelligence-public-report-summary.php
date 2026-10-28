<?php
/**
 * INTELLIGENCE-01E — descriptive counters from an actual Discovery-shaped
 * published projection. No incident identity, severity, public API or risk scores.
 */
declare(strict_types=1);
final class BadAround_Intelligence_Public_Report_Summary {
 public static function build(array $feed,DateTimeImmutable $asOf): array {
  if(!isset($feed['items'],$feed['total'],$feed['page'],$feed['pages']) ||
     !is_array($feed['items']) || !array_is_list($feed['items']) ||
     !is_int($feed['total']) || !is_int($feed['page']) || !is_int($feed['pages']) ||
     $feed['total']<0 || $feed['pages']<1 || $feed['page']<1)throw new InvalidArgumentException('Invalid Discovery pagination');
  $seen=[];$byType=[];$byTerritory=[];$byMonth=[];$dated=0;$missing=0;$valid=0;$duplicate=0;
  foreach($feed['items'] as $row){
   if(!is_array($row)||!isset($row['id'])||!is_int($row['id'])||$row['id']<1)continue;
   if(isset($seen[$row['id']])){$duplicate++;continue;}
   $seen[$row['id']]=true;$valid++;
   $t=$row['event_type']['name']??null;
   if(is_string($t)&&trim($t)!=='')$byType[$t]=($byType[$t]??0)+1;
   $area=$row['territory']['name']??null;
   if(is_string($area)&&trim($area)!=='')$byTerritory[$area]=($byTerritory[$area]??0)+1;
   $date=$row['occurred_date']??null;
   $d=is_string($date)&&preg_match('/^\\d{4}-\\d{2}-\\d{2}$/',$date)
      ?DateTimeImmutable::createFromFormat('!Y-m-d',$date,$asOf->getTimezone()):false;
   if(!$d||$d->format('Y-m-d')!==$date||$d>$asOf->setTime(23,59,59)){$missing++;continue;}
   $dated++;$month=$d->format('Y-m');$byMonth[$month]=($byMonth[$month]??0)+1;
  }
  foreach([$byType,$byTerritory,$byMonth] as &$unused){} // no implicit mutation
  ksort($byType);ksort($byTerritory);ksort($byMonth);
  // A single page can never be certified as a complete population if its
  // reported total differs or pagination announces additional pages.
  $complete=$feed['page']===1 && $feed['pages']===1 &&
    $feed['total']===$valid && $duplicate===0;
  return ['contract'=>'badaround-public-report-summary/v0.1','unit'=>'published_reports_not_unique_incidents',
   'observed_reports'=>$valid,'source_reported_total'=>$feed['total'],
   'snapshot_complete'=>$complete,'duplicate_report_ids'=>$duplicate,
   'with_incident_date'=>$dated,'without_valid_incident_date'=>$missing,
   'by_event_type'=>$byType,'by_territory_label'=>$byTerritory,'by_incident_month'=>$byMonth,
   'certified_incident_counts'=>false,'public_risk_scores'=>false,'public_anomaly_alerts'=>false];
 }
}
