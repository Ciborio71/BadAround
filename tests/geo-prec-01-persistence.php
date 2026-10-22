<?php
require __DIR__.'/native-media-harness.php';
$repo=new BadAround_Report_Repository();$checks=0;
function pc($ok,$label){global $checks;$checks++;if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
function prow($id){global $db;return $db->get_row($db->prepare('SELECT * FROM test_ba_reports WHERE id=%d',$id),ARRAY_A);}
foreach(['badaround-report/v1','badaround-report/v2'] as $v)foreach(['exact_point','approximate_area'] as $mode){
 $p=f13_fixture();$p['schema_version']=$v;$p['submission_id']=BadAround_Native_Media_Config::new_uuid();$p['location']['public_location_request']=$mode;$p['location']['public_location_request_version']='public-location-request-v1';
 $p=BadAround_Report_Version_Registry::prepare($p);pc(!is_wp_error($p),'canonical accepted '.$v.' '.$mode);
 $idem=new BadAround_Report_Idempotency_Service($repo);$r=$idem->reserve($p['submission_id'],$p);pc(!is_wp_error($r),'reserve');$id=$r['report_id'];
 try{
  $reserved=prow($id);pc($repo->persist_canonical_private_data($id,$p),'persist');$stored=prow($id);$e=json_decode($stored['content_original'],true);
  pc($e['public_geo_governance']==='user-public-location/v1'&&$e['canonical']===$p,'server-owned marker outside unchanged canonical');
  pc($stored['payload_hash']===hash('sha256',$stored['content_original']),'payload hash includes governance bytes');
  if($v==='badaround-report/v2')pc($reserved['content_original']===$stored['content_original'],'atomic v2 reservation already marked');
  $forged=$e;$forged['public_geo_governance']='legacy';pc($repo->persist_canonical_private_data($id,$p,$forged)&&prow($id)['content_original']===$stored['content_original'],'caller cannot replace governance on retry');
  $changed=$p;$changed['location']['public_location_request']=$mode==='exact_point'?'approximate_area':'exact_point';
  pc(!$repo->persist_canonical_private_data($id,$changed)&&prow($id)===$stored,'persisted reporter request immutable');
  pc(BadAround_Public_Geo_Contract::user_authority($e)['mode']===$mode,'authority derived from persisted request only');
 }finally{$idem->release($p['submission_id']);}
 // Emulate a complete pre-cutover snapshot without rewriting it through a new reservation.
 $historical=$e;unset($historical['public_geo_governance']);$bytes=wp_json_encode($historical,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
 $db->update('test_ba_reports',['content_original'=>$bytes,'payload_hash'=>hash('sha256',$bytes)],['id'=>$id]);
 pc($repo->persist_canonical_private_data($id,$p)&&prow($id)['content_original']===$bytes,'historical '.$v.' retry never stamps or migrates governance');
 pc(BadAround_Public_Geo_Contract::user_authority($historical)===null,'historical schema version alone never activates new rule');
}
// Bare v1 slots are legacy until their first canonical snapshot; caller cannot opt out at first write.
$p=f13_fixture();$p['submission_id']=BadAround_Native_Media_Config::new_uuid();$id=$repo->create_native_intake_record($p['submission_id']);
$override=['schema_version'=>$p['schema_version'],'submission_id'=>$p['submission_id'],'canonical'=>$p,'public_geo_governance'=>'legacy'];
pc($repo->persist_canonical_private_data($id,$p,$override),'v1 first canonical persistence succeeds');
pc(json_decode(prow($id)['content_original'],true)['public_geo_governance']==='user-public-location/v1','first-write governance cannot be client supplied');
echo "GEO_PREC_01_PERSISTENCE=PASS assertions={$checks}\n";
rmdir($root);rmdir($public);
