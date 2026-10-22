<?php
/** Differential oracle: actual frozen backend evaluation; no application writes. */
ob_start(); require __DIR__.'/native-report-f13.php'; ob_end_clean();
require_once dirname(__DIR__).'/wordpress/plugins/badaround-core/includes/'.'class-badaround-report-v2-validator.php';
$cases=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);$out=[];
foreach($cases as $report){
 $normal=(new BadAround_Report_V2_Normalizer())->normalize($report);$row=BadAround_Report_Conditional_Contract::row($normal);$active=[];$required=[];
 foreach(BadAround_Report_Conditional_Contract::fields() as $path=>$field){if(BadAround_Report_Conditional_Contract::active($path,$normal,$row)){$active[]=$path;if($row['fields'][$path]['required_when_active']??false)$required[]=$path;}}
 $result=BadAround_Report_Version_Registry::prepare($report);
 $out[]=['active'=>$active,'required'=>$required,'normalized'=>$normal,'error'=>is_wp_error($result)?['code'=>$result->get_error_code(),'data'=>$result->get_error_data()]:null];
}
echo json_encode($out,JSON_THROW_ON_ERROR);
