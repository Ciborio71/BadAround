<?php
/**
 * INTELLIGENCE-01I — trusted WordPress server-side integration seam.
 * Deliberately no hooks, REST routes, cron or persistence.
 * Requires the canonical Discovery class to be loaded by the host application.
 */
declare(strict_types=1);
require_once __DIR__.'/intelligence-discovery-bridge.php';
final class BadAround_Intelligence_WordPress_Adapter {
 public static function describePublishedReports($discovery=null):array {
  if(!class_exists('BadAround_Discovery_Query')||!function_exists('wp_timezone'))
   throw new RuntimeException('Canonical WordPress Discovery runtime unavailable');
  if($discovery===null)$discovery=new BadAround_Discovery_Query();
  if(!($discovery instanceof BadAround_Discovery_Query))
   throw new InvalidArgumentException('Expected canonical Discovery service');
  $today=new DateTimeImmutable('now',wp_timezone());
  $result=BadAround_Intelligence_Discovery_Bridge::build(
   [$discovery,'discover'],$today);
  // Explicitly descriptive, never promote sample reports to certified episodes.
  if(($result['totals']['certified_incident_counts']??null)!==false||
     ($result['totals']['snapshot_complete']??null)!==true)
    throw new RuntimeException('Cannot certify descriptive report counts');
  $result['runtime']='canonical_wordpress_discovery';
  return $result;
 }
}
