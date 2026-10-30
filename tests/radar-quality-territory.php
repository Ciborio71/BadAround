<?php
declare(strict_types=1);
define('ABSPATH',__DIR__);
$dir=dirname(__DIR__).'/wordpress/plugins/badaround-core/includes/';
require $dir.'class-badaround-radar-quality-registry.php';
require $dir.'class-badaround-radar-territory-analysis.php';
$rows=array();
foreach(array(233,352,354,381,378,290) as $id)$rows[]=array('id'=>$id,'territory'=>array('slug'=>'roma-roma'),'event_type'=>array('slug'=>'veicolo-rubato'));
$q=BadAround_Radar_Quality_Registry::classify($rows);
if($q['synthetic_count']!==3||$q['unverified_count']!==3||$q['verified_real_count']!==0)
 throw new RuntimeException('Initial fail-closed classification');
$a=BadAround_Radar_Territory_Analysis::summarize($q);
if($a['verified_count']!==0||$a['groups']!==[]||$a['excluded']['confirmed_synthetic']!==3)
 throw new RuntimeException('Synthetic or unverified leaked');
$review=array(381=>array('status'=>'editor_verified_real','reviewer_id'=>7,'reviewed_at'=>'2026-10-30T20:00:00Z','evidence_ref'=>'internal-review-381'));
$q=BadAround_Radar_Quality_Registry::classify($rows,$review);
$a=BadAround_Radar_Territory_Analysis::summarize($q);
if($a['verified_count']!==1||$a['groups'][0]['published_reports']!==1||$a['excluded']['unverified']!==2)
 throw new RuntimeException('Verified selection');
try {BadAround_Radar_Quality_Registry::classify($rows,array(233=>$review[381]));throw new RuntimeException('Synthetic override');}
catch(InvalidArgumentException $e){}
echo "RADAR QUALITY + TERRITORY PASS: 4 scenarios\n";
