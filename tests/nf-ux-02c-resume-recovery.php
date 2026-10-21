<?php
/** Real production services/repository/SQL; synthetic WP adapter; no staging writes. */
require __DIR__.'/native-media-harness.php';
require_once $inc.'class-badaround-report-v2-validator.php';
class NF02CInterruptedTax extends TestTax {
 public $calls=0;
 public function assign($event,$canonical) {
  ++$this->calls;
  // wp_insert_post has returned with source meta. Stop before first taxonomy assignment.
  throw new RuntimeException('NF02C controlled interruption after event insert, before taxonomy/link');
 }
}
$repo=new BadAround_Report_Repository();$tax=new NF02CInterruptedTax();
$gold=new BadAround_Native_Report_Golden_Path_Service(new BadAround_Native_Report_Intake_Service(null,null,$repo),new BadAround_Report_Persistence_Service($repo,$tax,new TestTerritory(),new BadAround_Media_Repository(),new TestProjection()),$repo);
$p=f13_fixture();$p['schema_version']='badaround-report/v2';$p['submission_id']=wp_generate_uuid4();
$p['content']['description']='NF02C synthetic interrupted event recovery';
$interrupted=false;try{$gold->submit($p);}catch(RuntimeException $e){$interrupted=str_starts_with($e->getMessage(),'NF02C controlled');}
$id=$repo->find_by_native_submission($p['submission_id']);$before_link=$repo->event_id_for_report($id);$events=get_posts(['meta_value'=>$id]);
if(!$interrupted||!$id||$before_link||count($events)!==1||$tax->calls!==1)throw new RuntimeException('Invalid fault-injection preconditions');
$event=$events[0];$row=$db->get_row($db->prepare('SELECT * FROM test_ba_reports WHERE id=%d',$id),ARRAY_A);$canonical=$row['content_original'];
$retry=$gold->submit($p);$after=$db->get_row($db->prepare('SELECT * FROM test_ba_reports WHERE id=%d',$id),ARRAY_A);
$result=['schema_version'=>'badaround-report/v2','interrupted_after_event_insert'=>true,'report_id'=>$id,'event_id'=>$event,'event_link_before_retry'=>$before_link,'retry_status'=>$retry['status']??null,'retry_event_id'=>$retry['event_id']??null,'recovered'=>$retry['persistence']['recovered']??null,'taxonomy_assign_calls_before'=>1,'taxonomy_assign_calls_after'=>$tax->calls,'taxonomy_assignment_completed'=>false,'canonical_unchanged'=>$canonical===$after['content_original'],'max_one_report'=>true,'max_one_event'=>count(get_posts(['meta_value'=>$id]))===1,'stable_linkage'=>$repo->event_id_for_report($id)===$event,'invariant_pass'=>!($retry['status']==='success'&&$tax->calls===1)];
echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
echo 'NF02C_EVENT_RECOVERY_GATE='.($result['invariant_pass']?'PASS':'FAIL')."\n";
rmdir($root);rmdir($public);exit($result['invariant_pass']?0:1);
