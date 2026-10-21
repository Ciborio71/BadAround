<?php
require __DIR__ . '/native-media-harness.php';
require_once $inc . 'class-badaround-report-v2-validator.php';
$checks=0;
function ic($ok,$why){global $checks;++$checks;if(!$ok)throw new RuntimeException($why);}
function ip($v='badaround-report/v2'){$p=f13_fixture();$p['schema_version']=$v;$p['submission_id']=BadAround_Native_Media_Config::new_uuid();return BadAround_Report_Version_Registry::prepare($p);}
function ir($id){global $db;return $db->get_row($db->prepare('SELECT * FROM test_ba_reports WHERE id=%d',$id),ARRAY_A);}
function ig($repo,$tax=null){return new BadAround_Native_Report_Golden_Path_Service(new BadAround_Native_Report_Intake_Service(null,null,$repo),new BadAround_Report_Persistence_Service($repo,$tax?:new TestTax(),new TestTerritory(),new BadAround_Media_Repository(),new TestProjection()),$repo);}
$repo=new BadAround_Report_Repository();
foreach(['badaround-report/v1','badaround-report/v2'] as $version){
 $p=ip($version);$idem=new BadAround_Report_Idempotency_Service($repo);$slot=$idem->reserve($p['submission_id'],$p);ic(!is_wp_error($slot),'reserve');$id=$slot['report_id'];
 try {
  $reserved=ir($id);ic($repo->persist_canonical_private_data($id,$p)===true,'first persist');$before=ir($id);
  if($version==='badaround-report/v2')ic($reserved['content_original']===$before['content_original']&&!empty($before['author_name']),'reserved bytes preserved while private columns materialize');
  ic($repo->persist_normalized_private_data($id,[])===false&&ir($id)===$before,'legacy writer cannot replace native snapshot');
  ic($repo->persist_canonical_private_data($id,$p)===true,'identical retry');ic(ir($id)['content_original']===$before['content_original'],'identical canonical bytes');
  foreach(['description','geo','social','uuid','version','envelope'] as $case){
   $bad=$p;$override=null;
   switch($case){case 'description':$bad['content']['description']='Changed active detail';break;
    case 'geo':$bad['location']['public_location_request']='exact_point';$bad['location']['public_location_request_version']='public-location-request-v1';break;
    case 'social':$bad['reporter']['social_visibility']='declined';$bad['consents']['social_visibility_version']='social-preference-v1';break;
    case 'uuid':$bad['submission_id']=BadAround_Native_Media_Config::new_uuid();break;
    case 'version':$bad['schema_version']=$version==='badaround-report/v2'?'badaround-report/v1':'badaround-report/v2';break;
    case 'envelope':$override=json_decode($before['content_original'],true);$override['canonical']['content']['description']='Forged via override';break;}
   ic($repo->persist_canonical_private_data($id,$bad,$override)===false,$version.' rejects '.$case);
   ic(ir($id)===$before,'entire stored row unchanged after '.$case);
  }
 }finally{$idem->release($p['submission_id']);}
 $gold=ig($repo);$done=$gold->submit($p);$again=$gold->submit($p);ic(($done['status']??null)==='success'&&$done['report_id']===$id&&$again['event_id']===$done['event_id'],'original recovery after conflicts');
 ic(get_post_status($done['event_id'])==='pending','pending only');
 $http=new BadAround_Native_Report_REST_Controller($gold,static function(){throw new Exception('No media coordinator');});
 foreach(['same','active','version'] as $phase){$q=$p;if($phase==='active')$q['content']['description']='HTTP active conflict';if($phase==='version'){$q=f13_fixture();$q['submission_id']=$p['submission_id'];$q['schema_version']=$version==='badaround-report/v2'?'badaround-report/v1':'badaround-report/v2';}$GLOBALS['transients']=[];
  $r=$http->rest_create(new WP_REST_Request(json_encode($q),['content-type'=>'application/json']));
  ic($r->status===($phase==='same'?200:409),'HTTP status '.$phase);if($phase!=='same')ic($r->data['error']['code']==='duplicate_submission','HTTP conflict code');
 }
}
// Empty native slots are historical v1; they are not an implicit upgrade path.
$p=ip('badaround-report/v1');$id=$repo->create_native_intake_record($p['submission_id']);$before=ir($id);$bad=$p;$bad['schema_version']='badaround-report/v2';ic($repo->persist_canonical_private_data($id,$bad)===false&&ir($id)===$before,'bare v1 slot cannot become v2');ic($repo->persist_canonical_private_data($id,$p),'bare v1 first write');
// V2 normalizes raw object order and strips only known inactive data before equality.
$p=ip();$id=$repo->create_versioned_native_intake_record($p);ic($repo->persist_canonical_private_data($id,$p),'first v2');$before=ir($id);$same=array_reverse($p,true);$same['vehicle']=['type'=>['stale']];ic($repo->persist_canonical_private_data($id,$same)&&ir($id)['content_original']===$before['content_original'],'normalized reordered stale-only persistence');$same['vehicle']['unknown']='x';ic(!$repo->persist_canonical_private_data($id,$same)&&ir($id)===$before,'unknown hidden key rejected');
// Active Altro / conditional details cannot replace a committed snapshot.
foreach(['altro','authority'] as $case){$p=ip();if($case==='altro'){$p['location']['place_type']='other';$p['location']['place_type_other_detail']='Private initial detail';}else{$p['authority']=['status'=>'yes','type'=>'other','type_other_detail'=>'Initial authority'];}$p=BadAround_Report_Version_Registry::prepare($p);ic(!is_wp_error($p),'conditional fixture');$id=$repo->create_versioned_native_intake_record($p);ic($repo->persist_canonical_private_data($id,$p),'conditional first');$before=ir($id);$bad=$p;if($case==='altro')$bad['location']['place_type_other_detail']='Changed';else $bad['authority']['type_other_detail']='Changed';ic(!$repo->persist_canonical_private_data($id,$bad)&&ir($id)===$before,'active conditional immutable '.$case);}
// Simulate write uncertainty AFTER SQL committed, not merely a pre-write failure.
class ICUncertainDB extends MediaTestDB {public $uncertain=false;public function update($table,$data,$where,$a=null,$b=null){$out=parent::update($table,$data,$where,$a,$b);if($this->uncertain&&$table==='test_ba_reports'&&isset($data['content_original'])){$this->uncertain=false;return false;}return $out;}}
$GLOBALS['wpdb']=$db=new ICUncertainDB($db->pdo);
foreach(['badaround-report/v1','badaround-report/v2'] as $v){$p=ip($v);$id=$v==='badaround-report/v2'?$repo->create_versioned_native_intake_record($p):$repo->create_native_intake_record($p['submission_id']);$db->uncertain=true;ic(!$repo->persist_canonical_private_data($id,$p),'uncertain outcome surfaced');$committed=ir($id);ic($repo->persist_canonical_private_data($id,$p)&&ir($id)['content_original']===$committed['content_original'],'uncertain retry same bytes');$done=ig($repo)->submit($p);ic(($done['status']??'')==='success'&&$done['report_id']===$id,'uncertain recovery original report');}
// Lost first-write race: the observed empty slot is changed before UPDATE.
class ICRaceDB extends MediaTestDB {public $winner=null;public function update($table,$data,$where,$a=null,$b=null){if($this->winner&&isset($data['content_original'])){$winner=$this->winner;$this->winner=null;parent::update($table,$winner,['id'=>$where['id']]);}return parent::update($table,$data,$where,$a,$b);}}
$GLOBALS['wpdb']=$db=new ICRaceDB($db->pdo);
foreach([false,true] as $identical){$p=ip('badaround-report/v1');$id=$repo->create_native_intake_record($p['submission_id']);$winner=$p;if(!$identical)$winner['content']['description']='Winning different snapshot';$envelope=['schema_version'=>$winner['schema_version'],'submission_id'=>$winner['submission_id'],'canonical'=>$winner,'private_fields'=>[55=>$winner['content']['description']]];$bytes=wp_json_encode($envelope,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);$db->winner=['content_original'=>$bytes,'payload_hash'=>hash('sha256',$bytes)];ic($repo->persist_canonical_private_data($id,$p)===$identical,'CAS race outcome');ic(ir($id)['content_original']===$bytes,'CAS winner unchanged');}
class ICFailTax extends TestTax {public $fail=true;public function assign($event,$canonical){if($this->fail){$this->fail=false;return new WP_Error('synthetic_event_failure');}return parent::assign($event,$canonical);}}
foreach(['badaround-report/v1','badaround-report/v2'] as $v){$p=ip($v);$gold=ig($repo,new ICFailTax());$n=count($GLOBALS['posts']);$failed=$gold->submit($p);ic(($failed['status']??'')==='error'&&count($GLOBALS['posts'])===$n,'partial event compensated');$ok=$gold->submit($p);$again=$gold->submit($p);ic(($ok['status']??'')==='success'&&$ok['event_id']===$again['event_id']&&count($GLOBALS['posts'])===$n+1,'event recovery exactly once');}
echo "NF_UX_02C1_PERSISTENCE=PASS assertions={$checks}\n";
rmdir($root);rmdir($public);
