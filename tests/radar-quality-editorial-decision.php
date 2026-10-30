<?php
declare(strict_types=1);
define('ABSPATH',__DIR__);
require dirname(__DIR__).'/wordpress/plugins/badaround-core/includes/class-badaround-radar-editorial-decision.php';
$x=['event_id'=>381,'status'=>'editor_verified_real','reviewer_id'=>7,'reviewed_at'=>'2026-10-30T20:00:00Z','evidence_ref'=>'internal-review-381','reason'=>'Fonte originale verificata','expected_revision'=>str_repeat('a',64)];
$r=BadAround_Radar_Editorial_Decision::validate($x);
if($r['may_write']!==false||$r['public_exposure']!==false)throw new RuntimeException('Unexpected side effects');
foreach([['event_id',233],['evidence_ref','<script>'],['reviewer_id',0]] as $change){
 $invalid=$x;$invalid[$change[0]]=$change[1];
 try{BadAround_Radar_Editorial_Decision::validate($invalid);throw new RuntimeException('Unsafe decision accepted');}
 catch(InvalidArgumentException $e){}
}
echo "RADAR-QUALITY-02: PASS 4 validation scenarios\n";
