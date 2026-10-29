<?php
declare(strict_types=1);
define('ABSPATH', __DIR__);
require_once dirname(__DIR__).'/wordpress/plugins/badaround-core/includes/class-badaround-intelligence-peak-candidates.php';
function require_test($condition,$message) {if(!$condition)throw new RuntimeException($message);}
function fixture($c,$p) {
 return array('contract'=>'badaround-intelligence-descriptive-trends/v0.1',
 'unit'=>'published_reports_not_unique_incidents','period_days'=>7,
 'anomaly_detection_enabled'=>false,'risk_inference_enabled'=>false,'public_alerts'=>array(),
 'groups'=>array(array('territory_slug'=>'roma','event_type_slug'=>'furto','current_reports'=>$c,'previous_reports'=>$p)));
}
$cases=array(
 array(10,5,true,null), array(9,5,false,'insufficient_current_reports'),
 array(20,0,false,'insufficient_previous_reports'),
 array(12,7,false,'increase_below_provisional_gate'),
 array(20,10,true,null),
 array(0,0,false,'insufficient_previous_reports')
);
foreach($cases as $case) {
 $result=BadAround_Intelligence_Peak_Candidates::screen(fixture($case[0],$case[1]));
 require_test($result['groups'][0]['candidate_for_manual_review']===$case[2],'Eligibility mismatch');
 require_test($result['groups'][0]['suppression_reason']===$case[3],'Suppression mismatch');
 require_test($result['public_alerts']===array() && $result['thresholds_approved']===false,'No public alert');
}
try {BadAround_Intelligence_Peak_Candidates::screen(array());throw new RuntimeException('Malformed accepted');}
catch(InvalidArgumentException $e){}
$invalid=fixture(10,5);$invalid['groups'][0]['territory_slug']='secret/coordinate';
try {BadAround_Intelligence_Peak_Candidates::screen($invalid);throw new RuntimeException('Invalid territory accepted');}
catch(InvalidArgumentException $e){}
echo 'INTELLIGENCE-02B: PASS 14 checks'.PHP_EOL;
