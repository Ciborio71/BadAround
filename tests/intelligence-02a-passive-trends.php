<?php
declare(strict_types=1);
define('ABSPATH', __DIR__);
require_once dirname(__DIR__).'/wordpress/plugins/badaround-core/includes/class-badaround-intelligence-trends.php';
function check($ok,$message) {if(!$ok)throw new RuntimeException($message);}
function report($id,$date,$territory='roma',$type='furto') {
 return array('id'=>$id,'occurred_date'=>$date,'territory'=>array('slug'=>$territory),
 'event_type'=>array('slug'=>$type));
}
$today=new DateTimeImmutable('2026-10-29',new DateTimeZone('Europe/Rome'));
$rows=array(
 report(1,'2026-10-29'),report(2,'23/10/2026'),
 report(3,'2026-10-22'),report(4,'16/10/2026'),
 report(5,'2026-10-15'),report(6,'2026-11-01'),
 report(7,'2026-02-30'),report(8,''),
 report(9,'2026-10-29','pomezia','avvistamento')
);
$r=BadAround_Intelligence_Trends::summarize($rows,$today,7);
check($r['current_start']==='2026-10-23' && $r['previous_start']==='2026-10-16','Calendar boundaries');
check($r['input_report_count']===9 && $r['undated_or_invalid_report_count']===2,'Missing dates');
check(count($r['groups'])===2,'Groups');
check($r['groups'][1]['territory_slug']==='roma','Ordering');
check($r['groups'][1]['current_reports']===2 && $r['groups'][1]['previous_reports']===2,'Window counts');
check($r['groups'][1]['delta_reports']===0 && $r['groups'][1]['percentage_change']===0.0,'Baseline change');
check($r['groups'][0]['comparison_available']===false && $r['groups'][0]['percentage_change']===null,'Zero base');
check($r['public_alerts']===array() && $r['anomaly_detection_enabled']===false && $r['risk_inference_enabled']===false,'No claims');
$other=BadAround_Intelligence_Trends::summarize($rows,$today,30);
check($other['period_days']===30 && $other['current_start']==='2026-09-30','30 day option');
try {BadAround_Intelligence_Trends::summarize(array(report(1,'2026-10-29'),report(1,'2026-10-28')),$today);throw new RuntimeException('Duplicate accepted');}
catch(InvalidArgumentException $e) {}
try {BadAround_Intelligence_Trends::summarize($rows,$today,8);throw new RuntimeException('Invalid window accepted');}
catch(InvalidArgumentException $e) {}
echo "INTELLIGENCE-02A: PASS 11 assertions\n";
