<?php
/** INTELLIGENCE-01H: read-only bridge to the EXISTING Discovery service.
 * No REST route registration, no DB writes, no public exposure.
 * The callable is injected for deterministic tests; production callers may pass
 * [$discoveryQuery, 'discover'] only inside a trusted server context.
 */
declare(strict_types=1);
require_once __DIR__.'/intelligence-report-read-model.php';
final class BadAround_Intelligence_Discovery_Bridge {
 public const MAX_PAGES=20;
 public static function build(callable $discover,DateTimeImmutable $asOf):array {
  $pages=[];$expected=null;
  for($n=1;$n<=self::MAX_PAGES;$n++){
   $chunk=$discover(['page'=>$n,'per_page'=>100,'period'=>0,
     'territory'=>'','category'=>'','event_type'=>'','search'=>'']);
   if(!is_array($chunk)||!isset($chunk['pages'],$chunk['total'])||
      !is_int($chunk['pages'])||!is_int($chunk['total']))
      throw new UnexpectedValueException('Untrusted Discovery page');
   if($n===1){
     $expected=$chunk['pages'];
     if($expected<1||$expected>self::MAX_PAGES)
       throw new RuntimeException('Snapshot exceeds bounded page limit');
   }
   if($chunk['pages']!==$expected)throw new RuntimeException('Discovery page count drift');
   $pages[]=$chunk;
   if($n===$expected)break;
  }
  $result=BadAround_Intelligence_Report_Read_Model::fromPages($pages,$asOf);
  $result['bridge']='existing_discovery_read_only';
  return $result;
 }
}
