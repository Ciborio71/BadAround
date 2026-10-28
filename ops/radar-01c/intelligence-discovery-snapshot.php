<?php
/**
 * INTELLIGENCE-01F — assemble only a complete, internally consistent set of
 * public Discovery pages. Offline adapter: no REST route and no WP hooks.
 */
declare(strict_types=1);
final class BadAround_Intelligence_Discovery_Snapshot {
 public static function assemble(array $pages): array {
  if(!$pages)throw new InvalidArgumentException('Empty Discovery collection');
  $expectedPages=null;$expectedTotal=null;$byPage=[];$ids=[];
  foreach($pages as $chunk){
   if(!is_array($chunk)||!isset($chunk['items'],$chunk['total'],$chunk['page'],$chunk['pages'])
    ||!is_array($chunk['items'])||!array_is_list($chunk['items'])
    ||!is_int($chunk['total'])||!is_int($chunk['page'])||!is_int($chunk['pages'])
    ||$chunk['total']<0||$chunk['page']<1||$chunk['pages']<1)
      throw new InvalidArgumentException('Malformed Discovery response');
   $n=$chunk['page'];$max=$chunk['pages'];$total=$chunk['total'];
   if($n>$max||isset($byPage[$n]))throw new InvalidArgumentException('Repeated or invalid page');
   if($expectedPages!==null&&($max!==$expectedPages||$total!==$expectedTotal))
      throw new InvalidArgumentException('Inconsistent snapshot metadata');
   $expectedPages=$max;$expectedTotal=$total;$byPage[$n]=$chunk['items'];
  }
  if(count($byPage)!==$expectedPages)throw new InvalidArgumentException('Missing Discovery page');
  ksort($byPage,SORT_NUMERIC);$rows=[];
  foreach($byPage as $n=>$items){
   if($n!==count($rows)+1){} // no index-based assumptions about item counts
   foreach($items as $row){
    if(!is_array($row)||!isset($row['id'])||!is_int($row['id'])||$row['id']<1)
      throw new InvalidArgumentException('Invalid report identifier');
    if(isset($ids[$row['id']]))throw new InvalidArgumentException('Report repeated across pages');
    $ids[$row['id']]=true;$rows[]=$row;
   }
  }
  if(count($rows)!==$expectedTotal)throw new InvalidArgumentException('Reported total differs from rows');
  return ['items'=>$rows,'total'=>$expectedTotal,'page'=>1,'pages'=>1,
   'assembly'=>'all_pages_validated','snapshot_consistency'=>'metadata_only_not_transactional'];
 }
}
