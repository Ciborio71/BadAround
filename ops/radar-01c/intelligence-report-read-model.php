<?php
/** INTELLIGENCE-01G: offline read-model composition for published REPORTS. */
declare(strict_types=1);
require_once __DIR__.'/intelligence-discovery-snapshot.php';
require_once __DIR__.'/intelligence-public-report-summary.php';
require_once __DIR__.'/intelligence-discovery-preflight.php';
final class BadAround_Intelligence_Report_Read_Model {
 public static function fromPages(array $pages,DateTimeImmutable $asOf):array {
  $snapshot=BadAround_Intelligence_Discovery_Snapshot::assemble($pages);
  $summary=BadAround_Intelligence_Public_Report_Summary::build($snapshot,$asOf);
  $quality=BadAround_Intelligence_Discovery_Preflight::inspect($snapshot,$asOf);
  if(!$summary['snapshot_complete']||$summary['observed_reports']!==$quality['unique_report_ids'])
   throw new LogicException('Read model consistency failure');
  return ['contract'=>'badaround-intelligence-report-read-model/v0.1',
   'scope'=>'public_discovery_reports_not_unique_incidents',
   'totals'=>$summary,
   'quality'=>$quality,
   'certification'=>'offline_validation_only',
   'public_alerts'=>[],
   'heatmap_cells'=>[],
   'source_is_transactionally_consistent'=>false];
 }
}
