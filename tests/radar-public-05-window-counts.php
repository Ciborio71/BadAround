<?php
declare(strict_types=1);
define('ABSPATH', __DIR__);
require dirname(__DIR__).'/wordpress/plugins/badaround-core/includes/class-badaround-radar-window-counts.php';
$archive=['contract'=>'badaround-radar-public-counts/v0.1','unit'=>'published_reports_not_unique_incidents',
 'published_report_count'=>22,'dated_reports'=>5,'undated_reports'=>17,'date_coverage_sufficient'=>false,'public_alerts'=>[]];
$q=['contract'=>'badaround-intelligence-quality-gate/v0.1','unit'=>$archive['unit'],
 'date_coverage_count'=>5,'undated_or_invalid_count'=>17,'public_alerts'=>[]];
$trends=['contract'=>'badaround-intelligence-descriptive-trends/v0.1','unit'=>$archive['unit'],
 'input_report_count'=>22,'undated_or_invalid_report_count'=>17,'period_days'=>30,'public_alerts'=>[],
 'groups'=>[
 ['territory_slug'=>'pomezia','event_type_slug'=>'ostacolo','current_reports'=>1],
 ['territory_slug'=>'torvaianica','event_type_slug'=>'veicolo-rubato','current_reports'=>0],
 ['territory_slug'=>'roma','event_type_slug'=>'veicolo-rubato','current_reports'=>3]]];
$r=BadAround_Radar_Window_Counts::present($archive,$trends,$q);
if($r['dated_reports_in_period']!==4||count($r['groups'])!==2||$r['archive_reports']!==22
 ||$r['undated_archive_reports']!==17)throw new RuntimeException('30-day counts incorrect');
echo "PASS 30-day counts and exclusion of zero group\n";
$trends['period_days']=7;$trends['groups']=[];
$r=BadAround_Radar_Window_Counts::present($archive,$trends,$q);
if($r['dated_reports_in_period']!==0||$r['groups']!==[]||$r['archive_reports']!==22
 ||$r['temporal_comparison_visible']!==false)throw new RuntimeException('7-day emptiness incorrect');
echo "PASS 7-day empty state and retained archive\n";
$trends['input_report_count']=23;
try {BadAround_Radar_Window_Counts::present($archive,$trends,$q);throw new RuntimeException('Mismatch accepted');}
catch(InvalidArgumentException $e) {}
echo "PASS incompatible snapshot fails closed\n";
