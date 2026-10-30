<?php
declare(strict_types=1);
define('ABSPATH',__DIR__);
require_once dirname(__DIR__).'/wordpress/plugins/badaround-core/includes/class-badaround-radar-public-counts.php';
$rows=[];
for($i=1;$i<=22;$i++)$rows[]=['id'=>$i,'territory'=>['slug'=>'roma-roma'],'event_type'=>['slug'=>'veicolo-rubato']];
$q=['contract'=>'badaround-intelligence-quality-gate/v0.1','unit'=>'published_reports_not_unique_incidents',
'date_coverage_count'=>5,'undated_or_invalid_count'=>17,'public_alerts'=>[],'groups'=>[]];
$r=BadAround_Radar_Public_Counts::present($rows,$q);
if($r['published_report_count']!==22||$r['groups'][0]['published_reports']!==22)throw new RuntimeException('Counts wrong');
if($r['date_coverage_sufficient']!==false||$r['temporal_comparison_visible']!==false)throw new RuntimeException('Misleading trend');
if($r['public_alerts']!==[]||$r['risk_score_available']!==false)throw new RuntimeException('Risk claim');
$rows[21]['id']=1;
try{BadAround_Radar_Public_Counts::present($rows,$q);throw new RuntimeException('Duplicate accepted');}
catch(InvalidArgumentException $e){}
echo "RADAR-PUBLIC-01: PASS 4 tests\n";
