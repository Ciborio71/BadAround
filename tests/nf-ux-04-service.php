<?php
/** Native-produced payloads through unchanged REST, intake, SQL persistence and pending event. */
require __DIR__.'/native-media-harness.php';
$payloads=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);$checks=0;
function nf04check($ok,$message){global $checks;++$checks;if(!$ok)throw new RuntimeException($message);}
$repo=new BadAround_Report_Repository();$persist=new BadAround_Report_Persistence_Service($repo,new TestTax(),new TestTerritory(),new BadAround_Media_Repository(),new TestProjection());
$gold=new BadAround_Native_Report_Golden_Path_Service(new BadAround_Native_Report_Intake_Service(null,null,$repo),$persist,$repo);
$controller=new BadAround_Native_Report_REST_Controller($gold,static function(){throw new RuntimeException('No-media must not construct media service');});
foreach($payloads as $p){
 $GLOBALS['transients']=[];$before=count($GLOBALS['posts']);
 $result=$controller->rest_create(new WP_REST_Request(json_encode($p),['content-type'=>'application/json']));$body=$result->data;
 nf04check(($body['status']??null)==='success','REST v2 '.json_encode($body));
 nf04check($body['schema_version']==='badaround-report/v2','Explicit v2');
 nf04check($body['next_state']==='moderation_pending','Pending result');
 $rows=$db->get_results("SELECT * FROM test_ba_reports ORDER BY id DESC LIMIT 1",ARRAY_A);$row=$rows[0];
 $canonical=json_decode($row['content_original'],true)['canonical'];nf04check($canonical===(new BadAround_Report_V2_Normalizer())->normalize($p),'Canonical equals frontend normalization');
 nf04check(get_post_status((int)$row['event_id'])==='pending','Event never published');
 nf04check(count($GLOBALS['posts'])===$before+1,'Exactly one event');
 $GLOBALS['transients']=[];$retry=$controller->rest_create(new WP_REST_Request(json_encode($p),['content-type'=>'application/json']));nf04check(($retry->data['status']??null)==='success'&&count($GLOBALS['posts'])===$before+1,'Exact retry no duplicate');
}
echo json_encode(['pass'=>true,'payloads'=>count($payloads),'assertions'=>$checks]);
