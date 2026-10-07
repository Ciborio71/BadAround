<?php
require __DIR__.'/native-media-harness.php';
// Exact frozen transport is loaded from the authorized Git object, never reconstructed.
$pipes=[];$proc=proc_open(['git','show','bcc2c74ca3391f1d16f9b1eb9719650ea4d27952:wordpress/plugins/badaround-core/includes/class-badaround-native-report-rest-controller.php'],[1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__));
$frozen=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($proc);
if($exit!==0 || hash('sha256',$frozen)!=='112d15d2db24896e88ed9c5de011734cf70aa1f74ad9267823744483d58dc085')throw new Exception('Frozen baseline unavailable');
eval(substr(str_replace('BadAround_Native_Report_REST_Controller','Frozen_Report_Controller',$frozen),5));
class DifferentialRepo extends BadAround_Report_Repository {public $busy=false;function acquire_native_lock($id,$timeout=5){return !$this->busy && parent::acquire_native_lock($id,$timeout);}}
class DifferentialTax extends TestTax {public $fail=false;function assign($event,$canonical){return $this->fail ? new WP_Error('synthetic_tax_failure') : true;}}
function run_flow($class,$media,$availability){
	global $db;$db->query('DELETE FROM test_ba_reports');$db->query('DELETE FROM test_ba_report_media');$GLOBALS['posts']=[];$GLOBALS['transients']=[];
	$repo=new DifferentialRepo();$tax=new DifferentialTax();$persist=new BadAround_Report_Persistence_Service($repo,$tax,new TestTerritory(),new BadAround_Media_Repository(),new TestProjection());
	$gold=new BadAround_Native_Report_Golden_Path_Service(new BadAround_Native_Report_Intake_Service(null,null,$repo),$persist,$repo);
	$factory=static function(){throw new Exception('No-media must never construct media coordinator');};
	$controller=new $class($gold,$factory);$payload=f13_fixture();$payload['media']=['availability'=>$availability];if($media!=='absent')$payload['media']['items']=$media;
	$db->queries=[];$out=[];
	$call=function($p)use($controller,$db){
		$GLOBALS['transients']=[];$response=$controller->rest_create(new WP_REST_Request(json_encode($p),['content-type'=>'application/json','x-badaround-intake'=>'badaround-report/v1','x-badaround-media-capability'=>'deliberately-invalid-ignored']));
		$data=$response->data;if(isset($data['event_id']))$data['event_id']=$data['event_id'] ? 'generated-event' : null;
		$reports=$db->get_results('SELECT status,content_original,event_id FROM test_ba_reports',ARRAY_A);foreach($reports as &$r){if($r['event_id'])$r['event_id']='generated-event';}
		return [$response->status,$data,$reports,count($GLOBALS['posts']),array_values(array_map(fn($p)=>[$p['post_status'],$p['meta_input']['_ba_moderation_status']],$GLOBALS['posts']))];
	};
	$out['new']=$call($payload);$out['retry']=$call($payload);$out['completed']=$call($payload);
	$changed=$payload;$changed['content']['description'].=' changed';$out['changed']=$call($changed);
	$invalid=$payload;unset($invalid['reporter']['email']);$out['validation']=$call($invalid);
	$invalid=$payload;$invalid['schema_version']='bad-version';$out['schema']=$call($invalid);
	$repo->busy=true;$out['concurrency']=$call($payload);$repo->busy=false;
	// New identity: failure compensates event, and exact retry recovers.
	$payload['submission_id']='123e4567-e89b-42d3-a456-426614174001';$tax->fail=true;$out['persistence_failure']=$call($payload);$tax->fail=false;$out['recovery']=$call($payload);
	$queries=$db->queries;foreach($queries as $q){if(strpos($q,'ba_native_media_')!==false)throw new Exception('No-media ledger access');}
	return $out;
}
$cases=0;$fields=BadAround_Report_Schema::fields();
foreach($fields['media.availability']['constraints']['enum'] as $availability){
	foreach(['absent',[],null,'invalid'] as $media){
		$a=run_flow('Frozen_Report_Controller',$media,$availability);$b=run_flow('BadAround_Native_Report_REST_Controller',$media,$availability);
		if($a!==$b){fwrite(STDERR,'Differential mismatch '.json_encode([$availability,$media,$a,$b])."\n");exit(1);} $cases+=count($a);
	}
}
echo "No-media differential: $cases scenarios PASS; frozen transport + production F1.3/F1.4; zero media coordinator/ledger access\n";
rmdir($root);rmdir($public);
