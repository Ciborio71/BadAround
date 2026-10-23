<?php
require __DIR__.'/native-media-harness.php';
$payloads=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);$checks=0;
function gac($ok,$message){global $checks;$checks++;if(!$ok)throw new RuntimeException($message);}
$repo=new BadAround_Report_Repository();$persist=new BadAround_Report_Persistence_Service($repo,new TestTax(),new TestTerritory(),new BadAround_Media_Repository(),new TestProjection());
$gold=new BadAround_Native_Report_Golden_Path_Service(new BadAround_Native_Report_Intake_Service(null,null,$repo),$persist,$repo);
$controller=new BadAround_Native_Report_REST_Controller($gold,static function(){throw new RuntimeException('No-media must not initialize media service');});
foreach($payloads as $p){
 $GLOBALS['transients']=[];$before=count($GLOBALS['posts']);$response=$controller->rest_create(new WP_REST_Request(json_encode($p),['content-type'=>'application/json']));$body=$response->data;
 gac(($body['status']??null)==='success','REST radius payload '.json_encode($body));$id=$repo->find_by_native_submission($p['submission_id']);
 $row=$db->get_row($db->prepare('SELECT * FROM test_ba_reports WHERE id=%d',$id),ARRAY_A);$stored=$row['content_original'];$e=json_decode($stored,true);
 gac($e['canonical']['location']['public_location_request']===$p['location']['public_location_request'],'exact request persisted without coercion');
 gac($e['canonical']['schema_version']==='badaround-report/v2'&&$e['public_geo_governance']==='user-public-location/v1','stable report schema/governance');
 gac(count($GLOBALS['posts'])===$before+1&&get_post_status($row['event_id'])==='pending','one pending event');
 $GLOBALS['transients']=[];$retry=$controller->rest_create(new WP_REST_Request(json_encode($p),['content-type'=>'application/json']));
 gac(($retry->data['event_id']??null)===$body['event_id']&&count($GLOBALS['posts'])===$before+1,'identical retry same event');
 $changed=$e['canonical'];$changed['location']['public_location_request']=$p['location']['public_location_request']['mode']==='exact_point'?['mode'=>'approximate_area','radius_m'=>500]:['mode'=>'approximate_area','radius_m'=>$p['location']['public_location_request']['radius_m']===100?500:100];
 gac(!$repo->persist_canonical_private_data($id,$changed),'C.1 rejects changed radius/mode');
 $GLOBALS['transients']=[];$conflict=$p;$conflict['location']['public_location_request']=$changed['location']['public_location_request'];$response=$controller->rest_create(new WP_REST_Request(json_encode($conflict),['content-type'=>'application/json']));
 gac(($response->data['status']??null)!=='success'&&count($GLOBALS['posts'])===$before+1,'conflicting retry creates no event');
 gac($db->get_var($db->prepare('SELECT content_original FROM test_ba_reports WHERE id=%d',$id))===$stored,'canonical bytes immutable');
 $identity=$repo->native_source_identity($p['submission_id']);
 gac((int)$db->get_var($db->prepare("SELECT COUNT(*) FROM test_ba_reports WHERE source_type='native' AND source_form_id=%d AND source_entry_id=%d",$identity['form_id'],$identity['entry_id']))===1,'exactly one report for submission identity');
}
echo json_encode(['pass'=>true,'cases'=>count($payloads),'assertions'=>$checks]);
