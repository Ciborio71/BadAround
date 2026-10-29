<?php
declare(strict_types=1);
define('ABSPATH',__DIR__);
require_once dirname(__DIR__).'/wordpress/plugins/badaround-core/includes/class-badaround-intelligence-data-quality.php';
function ok($yes,$label){if(!$yes)throw new RuntimeException($label);}
function sample($total,$missing,$previous=5){
 $s=array('contract'=>'badaround-intelligence-descriptive-trends/v0.1','unit'=>'published_reports_not_unique_incidents','period_days'=>7,
 'input_report_count'=>$total,'undated_or_invalid_report_count'=>$missing,'public_alerts'=>array(),
 'groups'=>array(array('territory_slug'=>'roma','event_type_slug'=>'furto','current_reports'=>10,'previous_reports'=>$previous)));
 $c=array('contract'=>'badaround-intelligence-peak-candidates/v0.1','unit'=>'published_reports_not_unique_incidents','period_days'=>7,
 'thresholds_approved'=>false,'public_alerts'=>array(),'groups'=>array($s['groups'][0]+array('candidate_for_manual_review'=>true)));
 return array($s,$c);
}
[$s,$c]=sample(100,10);
$r=BadAround_Intelligence_Data_Quality::assess($s,$c);
ok($r['date_coverage_ratio']===0.9,'coverage');
ok(!$r['groups'][0]['quality_review_required'],'sufficient coverage');
ok($r['statistical_significance_assessed']===false && $r['public_alerts']===array(),'no risk claims');
[$s,$c]=sample(100,21);
$r=BadAround_Intelligence_Data_Quality::assess($s,$c);
ok($r['groups'][0]['quality_reasons']===array('unreliable_date_coverage'),'poor coverage');
[$s,$c]=sample(100,20,4);
$r=BadAround_Intelligence_Data_Quality::assess($s,$c);
ok($r['groups'][0]['quality_reasons']===array('small_comparison_base'),'boundary');
[$s,$c]=sample(0,0,0);
$r=BadAround_Intelligence_Data_Quality::assess($s,$c);
ok($r['date_coverage_ratio']===null,'empty denominator');
[$s,$c]=sample(10,11);
try{BadAround_Intelligence_Data_Quality::assess($s,$c);throw new RuntimeException('invalid totals accepted');}
catch(InvalidArgumentException $e){}
[$s,$c]=sample(100,0);$c['groups'][0]['current_reports']=11;
try{BadAround_Intelligence_Data_Quality::assess($s,$c);throw new RuntimeException('mismatch accepted');}
catch(InvalidArgumentException $e){}
[$s,$c]=sample(100,0);$c['thresholds_approved']=true;
try{BadAround_Intelligence_Data_Quality::assess($s,$c);throw new RuntimeException('approved gates accepted');}
catch(InvalidArgumentException $e){}
echo "INTELLIGENCE-02C: PASS 9 assertions\n";
