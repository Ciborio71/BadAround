<?php
/** INTELLIGENCE-01B — trusted publication projection adapter, no WP/database hooks. */
declare(strict_types=1);
final class BadAround_Intelligence_Projection_Adapter {
 public static function normalize(array $publication, array $authority): ?array {
  // Every sensitive trust assertion is supplied by server-side authority registry,
  // keyed by the published event ID, never by the incoming event payload.
  $id=$publication['id']??null;
  if(!is_int($id)||$id<1||!isset($authority[$id])||!is_array($authority[$id]))return null;
  $trusted=$authority[$id];
  if(($publication['public_geo']['source']??null)!=='event_public'
      ||($publication['public_geo']['version']??null)!=='public-location/v1'
      ||!in_array(($publication['public_geo']['mode']??null),['exact_point','approximate_area'],true))return null;
  if(($trusted['publication_status']??null)!=='published'
      ||($trusted['source_quality']??null)!=='verified_real'
      ||($trusted['public_projection_approved']??null)!==true
      ||($trusted['taxonomy_verified']??null)!==true
      ||($trusted['episode_identity_verified']??null)!==true)return null;
  if(!isset($trusted['canonical_episode_id'])||!is_string($trusted['canonical_episode_id'])
     ||trim($trusted['canonical_episode_id'])==='')return null;
  $path=$trusted['territory_path']??null;
  if(!is_array($path)||count($path)<3||count($path)>4)return null;
  foreach($path as $name)if(!is_string($name)||trim($name)==='')return null;
  $category=$trusted['category']??null;$phenomenon=$trusted['phenomenon']??null;
  if(!is_string($category)||$category===''||!is_string($phenomenon)||$phenomenon==='')return null;
  $date=$publication['occurred_date']??null;
  if(!is_string($date))$date=null;
  return [
   'publication_status'=>'published','source_quality'=>'verified_real',
   'canonical_episode_id'=>$trusted['canonical_episode_id'],
   'region'=>$path[0],'province'=>$path[1],'municipality'=>$path[2],
   'category'=>$category,'phenomenon'=>$phenomenon,'occurred_date'=>$date
  ];
 }
 public static function batch(array $events,array $authority): array {
  $normalized=[];$rejected=0;
  foreach($events as $publication){
   if(!is_array($publication)){$rejected++;continue;}
   $event=self::normalize($publication,$authority);
   if($event===null)$rejected++;else $normalized[]=$event;
  }
  return ['normalized'=>$normalized,'rejected'=>$rejected];
 }
}
