<?php
/** INTELLIGENCE-01D: public Discovery contract and readiness preflight, offline only. */
declare(strict_types=1);
final class BadAround_Intelligence_Discovery_Preflight {
 public static function inspect(array $payload,DateTimeImmutable $asOf): array {
  if(!isset($payload['items'])||!is_array($payload['items'])||!array_is_list($payload['items']))
    throw new InvalidArgumentException('Invalid Discovery collection');
  $ids=[];$types=[];$territories=[];$summary=['sample_events'=>0,'unique_report_ids'=>0,
   'duplicate_report_ids'=>0,'geolocated'=>0,'exact_point'=>0,'approximate_area'=>0,
   'dated'=>0,'dated_and_geolocated'=>0,'unknown_date'=>0,'invalid_date'=>0,
   'qa_title_suspects'=>0,'phenomenon_labels'=>0,'territory_labels'=>0,
   'is_complete_snapshot'=>false,'usable_for_incident_counts'=>false,
   'usable_for_temporal_geographic_trends'=>false,'reasons'=>[]];
  foreach($payload['items'] as $e) {
   if(!is_array($e)){continue;}
   $summary['sample_events']++;
   $id=$e['id']??null;
   if(!is_int($id)||$id<1)continue;
   if(isset($ids[$id])){$summary['duplicate_report_ids']++;continue;}
   $ids[$id]=true;
   if(preg_match('/^\\s*\\[?QA\\b|nessun evento reale|collaudo tecnico sintetico/i',(string)($e['title']??'')))$summary['qa_title_suspects']++;
   $type=$e['event_type']['name']??null;if(is_string($type)&&trim($type)!=='')$types[$type]=true;
   $territory=$e['territory']['name']??null;if(is_string($territory)&&trim($territory)!=='')$territories[$territory]=true;
   $geo=$e['public_geo']??null;
   $hasGeo=is_array($geo)&&($geo['source']??null)==='event_public'
    &&($geo['version']??null)==='public-location/v1'
    &&in_array(($geo['mode']??null),['exact_point','approximate_area'],true)
    &&isset($geo['lat'],$geo['lng'])&&is_numeric($geo['lat'])&&is_numeric($geo['lng'])
    &&abs((float)$geo['lat'])<=90&&abs((float)$geo['lng'])<=180;
   if($hasGeo){
    $summary['geolocated']++;
    $summary[$geo['mode']]++;
   }
   $date=$e['occurred_date']??null;
   if(!is_string($date)||$date===''){$summary['unknown_date']++;continue;}
   $d=preg_match('/^\\d{4}-\\d{2}-\\d{2}$/',$date)?DateTimeImmutable::createFromFormat('!Y-m-d',$date,$asOf->getTimezone()):false;
   if(!$d||$d->format('Y-m-d')!==$date||$d>$asOf->setTime(23,59,59)){$summary['invalid_date']++;continue;}
   $summary['dated']++;if($hasGeo)$summary['dated_and_geolocated']++;
  }
  $summary['unique_report_ids']=count($ids);
  $summary['phenomenon_labels']=count($types);
  $summary['territory_labels']=count($territories);
  $summary['reasons']=['Discovery IDs represent reports, not certified distinct episodes',
   'No server-trusted provenance or canonical episode identity in Discovery',
   'Full snapshot completeness and withdrawal history not established'];
  if($summary['dated_and_geolocated']===0)$summary['reasons'][]='No incident-dated public geometry available for spatial time trends';
  // Deliberately never promote a bounded public feed to certified statistics.
  return $summary;
 }
}
