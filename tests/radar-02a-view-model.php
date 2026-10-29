<?php
declare(strict_types=1);
define('ABSPATH', __DIR__);
require_once dirname(__DIR__).'/wordpress/plugins/badaround-core/includes/class-badaround-radar-view-model.php';
function verify($ok,$message) {if(!$ok)throw new RuntimeException($message);}
$t=array(
 'contract'=>'badaround-intelligence-descriptive-trends/v0.1',
 'unit'=>'published_reports_not_unique_incidents','period_days'=>7,
 'current_start'=>'2026-10-23','current_end'=>'2026-10-29','public_alerts'=>array(),
 'groups'=>array(array('territory_slug'=>'roma','event_type_slug'=>'furto',
   'current_reports'=>10,'previous_reports'=>5)));
$q=array(
 'contract'=>'badaround-intelligence-quality-gate/v0.1',
 'unit'=>'published_reports_not_unique_incidents',
 'statistical_significance_assessed'=>false,'representativeness_assessed'=>false,
 'thresholds_approved'=>false,'public_alerts'=>array(),
 'groups'=>array(array('territory_slug'=>'roma','event_type_slug'=>'furto',
 'quality_review_required'=>false)));
$r=BadAround_Radar_View_Model::build($t,$q);
verify($r['internal_preview_only']===true,'internal only');
verify($r['cards'][0]['current_reports']===10,'current');
verify($r['cards'][0]['previous_reports']===5,'previous');
verify($r['cards'][0]['percentage_change']===100.0,'percent');
verify($r['public_alerts']===array(),'no alerts');
$q['groups'][0]['quality_review_required']=true;
$r=BadAround_Radar_View_Model::build($t,$q);
verify($r['cards'][0]['comparison_displayable']===false,'hide untrustworthy comparison');
verify($r['cards'][0]['delta_reports']===null && $r['cards'][0]['percentage_change']===null,'no delta');
$q['groups'][0]['territory_slug']='pomezia';
try {BadAround_Radar_View_Model::build($t,$q);throw new RuntimeException('mismatch accepted');}
catch(InvalidArgumentException $e){}
$q['groups'][0]['territory_slug']='roma';$q['thresholds_approved']=true;
try {BadAround_Radar_View_Model::build($t,$q);throw new RuntimeException('improper approval accepted');}
catch(InvalidArgumentException $e){}
echo 'RADAR-02A: PASS 9 assertions'.PHP_EOL;
