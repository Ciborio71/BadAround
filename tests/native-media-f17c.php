<?php
/** Independent F1.7C security/recovery probes. Synthetic fixtures and isolated SQL only. */
require __DIR__.'/native-media-harness.php';
$checks=0;$failures=0;
function review_check($ok,$message){global $checks,$failures;++$checks;if(!$ok)++$failures;echo ($ok?'PASS: ':'FAIL: ').$message."\n";}
function review_code($v){return is_wp_error($v)?$v->get_error_code():($v['error']['code']??'success');}
function review_image($colour=0){$im=imagecreatetruecolor(2,2);imagesetpixel($im,0,0,$colour);ob_start();imagepng($im);return ob_get_clean();}
function review_session(){global $service;$id=BadAround_Native_Media_Config::new_uuid();$s=$service->create($id,BadAround_Native_Media_Capability::encode(random_bytes(32)),hash('sha256',$id));if(is_wp_error($s))throw new Exception(review_code($s));$s['submission_id']=$id;return $s;}
function review_upload($s,$bytes,$name='synthetic.png',$key=null){global $service;$r=fopen('php://temp','w+b');fwrite($r,$bytes);rewind($r);try{return $service->upload($s['media_session_id'],$key?:BadAround_Native_Media_Config::new_uuid(),$s['capability'],$r,$name,hash('sha256',$s['submission_id']));}finally{fclose($r);}}
function review_payload($s,$items){$p=f13_fixture();$p['submission_id']=$s['submission_id'];$p['media']=['availability'=>'yes','items'=>$items];return $p;}
function review_adapter($svc=null){global $service;$svc=$svc?:$service;$repo=new BadAround_Report_Repository();$linker=new BadAround_Native_Media_Linker($svc->ledger);$frozen=new BadAround_Report_Persistence_Service($repo,new TestTax(),new TestTerritory(),$linker,new TestProjection());$p=new BadAround_Native_Media_Persistence($svc,$repo,$frozen,$linker);$gold=new BadAround_Native_Report_Golden_Path_Service(new BadAround_Native_Report_Intake_Service(null,null,$repo),$p,$repo);return new BadAround_Native_Media_Report_Adapter($svc,$repo,$gold);}
class ReviewLedger extends BadAround_Native_Media_Ledger {
 public $held=[];
 function locked($names,$callback){return parent::locked($names,function()use($names,$callback){$previous=$this->held;$this->held=array_merge($this->held,$names);try{return $callback();}finally{$this->held=$previous;}});}
}
class ReviewCodecProbe extends BadAround_Native_Media_Validator {
 public $ledger,$calls=0,$insideCritical=false;
 function codecs(){++$this->calls;foreach($this->ledger->held as $lock){if($lock==='admission'||preg_match('/^(session|submission):/',$lock))$this->insideCritical=true;}return ['png'];}
}
$guardLedger=new ReviewLedger($db);$probe=new ReviewCodecProbe($config);$probe->ledger=$guardLedger;
$guardService=new BadAround_Native_Media_Service($config,$guardLedger,$storage,$probe,fn()=>$GLOBALS['now']);
$s=review_session();$u=review_upload($s,review_image(1101));$p=review_payload($s,[$u['descriptor']]);$canonical=(new BadAround_Report_Normalizer())->normalize($p);
$pin=$guardService->pin($canonical,$s['capability']);
review_check(review_code($pin)==='success','first pin succeeds with fresh validator instance');
review_check($probe->calls>0&&!$probe->insideCritical,'decoder readiness runs outside admission/submission/session locks');
review_check(!$guardLedger->held,'pin releases all media locks before frozen Golden Path');

// Publication must use the durable report-to-event relation if other provenance disappears.
$a=review_adapter();$bound=$a->submit($p,$s['capability']);review_check($bound['status']==='success','review fixture binds through frozen intake and persistence');
$event=$bound['event_id'];$report=$bound['report_id'];$saved=$GLOBALS['posts'][$event]['meta_input'];
$db->query('DELETE FROM test_ba_native_media_items WHERE session_id='.$db->pdo->quote($s['media_session_id']));
$db->query('DELETE FROM test_ba_report_media WHERE report_id='.(int)$report);
$GLOBALS['posts'][$event]['meta_input']=[];
review_check(review_code(BadAround_Native_Media_Fence::check($event))==='ba_native_media_publication_blocked','durable native report fences event with missing ledger final rows and post metadata');
$GLOBALS['posts'][$event]['meta_input']=['_ba_source_type'=>'wpforms'];
review_check(review_code(BadAround_Native_Media_Fence::check($event))==='ba_native_media_publication_blocked','mislabeled WPForms post cannot override durable native media report');
$GLOBALS['posts'][$event]['post_status']='publish';$GLOBALS['posts'][$event]['meta_input']['_ba_moderation_status']='published';
class ReviewPublication extends BadAround_Publication_Service {function prepare_public_projection($event){return true;}}
review_check(review_code((new ReviewPublication())->publish($event))==='ba_native_media_publication_blocked','already-published recovery cannot bypass missing-provenance native fence');
$db->fail=fn($q)=>strpos($q,'SELECT id, source_type, content_original FROM test_ba_reports')===0;
review_check(review_code(BadAround_Native_Media_Fence::check($event))==='ba_native_media_publication_blocked','failed durable report provenance query fails closed');$db->fail=null;
$originalContent=$db->get_var('SELECT content_original FROM test_ba_reports WHERE id='.(int)$report);$envelope=json_decode($originalContent,true);
$db->update('test_ba_reports',['source_type'=>'wpforms'],['id'=>$report]);review_check(review_code(BadAround_Native_Media_Fence::check($event))==='ba_native_media_publication_blocked','canonical native envelope rejects mislabeled durable source');$db->update('test_ba_reports',['source_type'=>'native'],['id'=>$report]);
foreach([null,false,0,''] as $invalidManifest){$corrupt=$envelope;$corrupt['canonical']['media']['items']=$invalidManifest;$db->update('test_ba_reports',['content_original'=>json_encode($corrupt)],['id'=>$report]);review_check(review_code(BadAround_Native_Media_Fence::check($event))==='ba_native_media_publication_blocked','malformed empty-looking native manifest fails closed: '.json_encode($invalidManifest));}
$db->update('test_ba_reports',['content_original'=>$originalContent],['id'=>$report]);
$GLOBALS['posts'][$event]['post_status']='pending';$GLOBALS['posts'][$event]['meta_input']=$saved;

// Completed retry cannot load a decoder, read originals, call persistence, renew TTL or reset moderation.
class ReviewNoFiles extends BadAround_Native_Media_Storage {function verify($k,$b,$c){throw new Exception('completed retry read original');}function promote($i,$r){throw new Exception('completed retry promoted original');}}
class ReviewNoDecoder extends BadAround_Native_Media_Validator {function codecs(){throw new Exception('completed retry invoked decoder');}}
$complete=review_session();$cu=review_upload($complete,review_image(1102));$cp=review_payload($complete,[$cu['descriptor']]);$ca=review_adapter();$cr=$ca->submit($cp,$complete['capability']);review_check($cr['status']==='success','completed replay fixture has durable receipt');
$completedRow=$ledger->session($complete['media_session_id']);$receipt=json_decode($completedRow['receipt_json'],true);$badReceipt=$receipt;$badReceipt['submission_id']=BadAround_Native_Media_Config::new_uuid();
$db->update('test_ba_native_media_sessions',['receipt_json'=>json_encode($badReceipt)],['session_id'=>$complete['media_session_id']]);
review_check(review_code($ca->submit($cp,$complete['capability']))==='media_binding_invariant_failed','receipt cannot name another submission');
$db->update('test_ba_native_media_sessions',['receipt_json'=>$completedRow['receipt_json']],['session_id'=>$complete['media_session_id']]);
$readonlyService=new BadAround_Native_Media_Service(new BadAround_Native_Media_Config(),$ledger,new ReviewNoFiles($config),new ReviewNoDecoder($config),fn()=>$GLOBALS['now']);
$readonly=new BadAround_Native_Media_Report_Adapter($readonlyService,new BadAround_Report_Repository(),new stdClass());
$before=$db->writes;$GLOBALS['now']=$fixture_epoch+90000;$replay=$readonly->submit($cp,$complete['capability']);$GLOBALS['now']=$fixture_epoch;
review_check($replay['status']==='success'&&$replay['duplicate']&&$db->writes===$before,'completed retry uses verifier and metadata only even when intake configuration is disabled');
review_check($ledger->session($complete['media_session_id'])===$completedRow,'completed retry preserves receipt state version and deadlines');
review_check(review_code($readonly->submit($cp,BadAround_Native_Media_Capability::encode(random_bytes(32))))==='media_capability_invalid','completed retry still requires exact capability');

// Independent A-E fault matrix: logical identities and deterministic targets survive interruption.
class ReviewPromotionFault extends BadAround_Native_Media_Storage {
 public $failOnce=true;
 function promote($item,$report){if($this->failOnce){$this->failOnce=false;return BadAround_Native_Media_Config::error('media_binding_failed');}return parent::promote($item,$report);}
}
foreach(['B-second-mapping','C-promotion','D-renamed-final-inserted','E-existing-event-receipt'] as $boundary){
 $fs=review_session();$f1=review_upload($fs,review_image(random_int(2000,5000)));$f2=review_upload($fs,review_image(random_int(5001,9000)));$fp=review_payload($fs,[$f1['descriptor'],$f2['descriptor']]);
 $faultStorage=$boundary==='C-promotion'?new ReviewPromotionFault($config):$storage;
 $faultService=new BadAround_Native_Media_Service($config,$ledger,$faultStorage,$validator,fn()=>$GLOBALS['now']);$fa=review_adapter($faultService);$mapping=0;
 if($boundary==='B-second-mapping'||$boundary==='D-renamed-final-inserted'){
  $db->fail=function($q)use($boundary,&$mapping){if(strpos($q,"UPDATE test_ba_native_media_items SET storage_key='report-")!==0)return false;++$mapping;return $mapping===($boundary==='B-second-mapping'?2:1);};
 }elseif($boundary==='E-existing-event-receipt'){$db->fail=fn($q)=>strpos($q,"UPDATE test_ba_native_media_sessions SET state='committed'")===0;}
 $failed=$fa->submit($fp,$fs['capability']);$db->fail=null;
 review_check(review_code($failed)==='media_binding_failed',$boundary.' returns retryable binding failure');
 review_check($ledger->session($fs['media_session_id'])['state']!=='committed',$boundary.' never acknowledges an incomplete receipt');
 $eventsBefore=count($GLOBALS['posts']);
 $recovered=$fa->submit($fp,$fs['capability']);$fr=$recovered['report_id']??0;$fe=$recovered['event_id']??0;$repo=new BadAround_Report_Repository();
 review_check($recovered['status']==='success'&&$repo->find_by_native_submission($fs['submission_id'])===$fr,$boundary.' exact retry preserves report identity');
 $events=get_posts(['meta_value'=>$fr]);review_check(count($events)===1&&$events[0]===$fe,$boundary.' produces exactly one durable event');
 review_check(count($ledger->final_set($fr))===2,$boundary.' produces exactly the two pinned final rows');
 $allDurable=true;foreach($fp['media']['items'] as $descriptor){$i=$ledger->item($descriptor['media_id']);$allDurable=$allDurable&&$i['state']==='bound_pending_review'&&(int)$i['event_id']===$fe&&$storage->verify($i['storage_key'],$i['file_size'],$i['checksum']);}
 review_check($allDurable&&get_post_status($fe)==='pending'&&get_post_meta($fe,'_ba_moderation_status')==='new',$boundary.' originals match checksum ownership and Da moderare');
 if($boundary==='E-existing-event-receipt')review_check(count($GLOBALS['posts'])===$eventsBefore,$boundary.' existing-event early return adds no event');
 $before=$db->writes;$again=$fa->submit($fp,$fs['capability']);review_check($again['duplicate']&&$db->writes===$before,$boundary.' completed recovery replay is read-only');
}

// Cross-submission ownership and exact manifest validation, with accepted foreign originals.
$owner=review_session();$own=review_upload($owner,review_image(1103));$foreign=review_session();$fu=review_upload($foreign,review_image(1104));
$op=review_payload($owner,[$own['descriptor']]);$oc=(new BadAround_Report_Normalizer())->normalize($op);
$wrong=$oc;$wrong['media']['items']=[$fu['descriptor']];review_check(review_code($service->pin($wrong,$owner['capability']))==='media_reference_invalid','accepted foreign media cannot enter owned manifest');
review_check(review_code($service->pin($oc,$foreign['capability']))==='media_capability_invalid','foreign valid capability cannot pin owned submission');
$guess=$oc;$guess['media']['items'][0]['media_id']=BadAround_Native_Media_Config::new_uuid();review_check(review_code($service->pin($guess,$owner['capability']))==='media_reference_invalid','guessed media UUID is rejected');
foreach(['mime_type'=>'image/jpeg','file_size'=>$own['descriptor']['file_size']+1,'extension'=>'jpg'] as $field=>$value){$wrong=$oc;$wrong['media']['items'][0][$field]=$value;review_check(review_code($service->pin($wrong,$owner['capability']))==='media_descriptor_mismatch','verified descriptor immutable: '.$field);}
$GLOBALS['now']=$fixture_epoch+86400;review_check(review_code($service->pin($oc,$owner['capability']))==='media_session_expired','first commit expiry boundary rejects owned capability');$GLOBALS['now']=$fixture_epoch;

// Binary edge cases independently reach full decode or static checks.
$png=review_image(1105);$crcBad=$png;$crcBad[29]=chr(ord($crcBad[29])^1);
foreach(['CRC'=>$crcBad,'trailing bytes'=>$png.'MZ','malformed chunk'=>substr_replace($png,pack('N',0xffffffff),8,4)] as $label=>$binary){$t=review_session();review_check(is_wp_error(review_upload($t,$binary)),'reject PNG '.$label);}
foreach(['windows\\path.png',"control\x01.png",'image.php.png','https://host.invalid/image.png'] as $name){$t=review_session();review_check(is_wp_error(review_upload($t,$png,$name)),'reject unsafe filename '.json_encode($name));}
// Root/file symlinks and unsafe modes cannot be used as staging evidence.
$badRootConfig=new BadAround_Native_Media_Config(array_merge($settings,['private_root'=>$public]));review_check(!(new BadAround_Native_Media_Storage($badRootConfig))->root(),'public document root cannot serve as private root');
chmod($root,0755);clearstatcache();review_check(!$storage->root(),'private root with group/other access fails closed');chmod($root,0700);clearstatcache();
$li=$ledger->item($own['descriptor']['media_id']);$safe=$storage->path($li['storage_key']);rename($safe,$safe.'.saved');symlink($safe.'.saved',$safe);clearstatcache();review_check(!$storage->verify($li['storage_key'],$li['file_size'],$li['checksum']),'original symlink cannot pass checksum verification');unlink($safe);rename($safe.'.saved',$safe);

// Cleanup preserves quota until deletion and cannot remove an active stale writer.
class ReviewFailedDelete extends BadAround_Native_Media_Storage {function remove($k){return false;}}
$cleanupSession=review_session();$cleanupUpload=review_upload($cleanupSession,review_image(1106));$cleanupItem=$ledger->item($cleanupUpload['descriptor']['media_id']);
$deleteService=new BadAround_Native_Media_Service($config,$ledger,new ReviewFailedDelete($config),$validator,fn()=>$GLOBALS['now']);
$reserved=$ledger->usage()['temporary_bytes'];review_check(review_code($deleteService->remove($cleanupSession['media_session_id'],$cleanupItem['media_id'],$cleanupSession['capability']))==='media_service_unavailable','failed physical deletion returns retryable error');
review_check($ledger->usage()['temporary_bytes']===$reserved&&!$ledger->item($cleanupItem['media_id'])['storage_deleted'],'failed remove never releases physical storage quota');
$GLOBALS['now']=$fixture_epoch+90000;(new BadAround_Native_Media_Cleanup($deleteService))->run();
review_check(!$ledger->item($cleanupItem['media_id'])['storage_deleted'],'failed orphan deletion retains storage charge');
(new BadAround_Native_Media_Cleanup($service))->run();review_check($ledger->item($cleanupItem['media_id'])['storage_deleted']==1,'bounded later cleanup reconciles physical deletion');$GLOBALS['now']=$fixture_epoch;

$writer=review_session();$writerId=BadAround_Native_Media_Config::new_uuid();$wr=fopen('php://temp','w+b');fwrite($wr,$png);rewind($wr);$staged=$storage->stage($writer['media_session_id'],$writerId,$wr);fclose($wr);
$ledger->insert_item(['media_id'=>$writerId,'session_id'=>$writer['media_session_id'],'client_upload_id'=>BadAround_Native_Media_Config::new_uuid(),'storage_key'=>$staged['storage_key'],'state'=>'receiving','file_size'=>5242880,'ip_hash'=>hash('sha256','review-writer'),'lease_expires_at'=>$fixture_epoch-1,'created_at'=>$fixture_epoch,'updated_at'=>$fixture_epoch]);
$otherDb=new MediaTestDB($db->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?$db->pdo:null);$otherLedger=new BadAround_Native_Media_Ledger($otherDb);$otherService=new BadAround_Native_Media_Service($config,$otherLedger,$storage,$validator,fn()=>$GLOBALS['now']);
$ledger->locked(['writer:'.$writerId],function()use($otherService){(new BadAround_Native_Media_Cleanup($otherService))->run();});
review_check($ledger->item($writerId)['state']==='receiving'&&$storage->verify($staged['storage_key'],$staged['file_size'],$staged['checksum']),'stale lease cannot collect a writer held by another connection');
(new BadAround_Native_Media_Cleanup($service))->run();review_check($ledger->item($writerId)['state']==='invalid'&&$ledger->item($writerId)['storage_deleted']==1,'stale writer cleanup rechecks then deletes only after lock release');

// Same-content retries do not create a global dedup oracle or another final binding.
$same=review_session();$key=BadAround_Native_Media_Config::new_uuid();$one=review_upload($same,$png,'synthetic.png',$key);$two=review_upload($same,$png,'synthetic.png',$key);
review_check($one['descriptor']===$two['descriptor']&&!$one['duplicate']&&$two['duplicate'],'identical per-file retry is logically stable');
$different=review_upload($same,review_image(1107),'synthetic.png',$key);review_check(review_code($different)==='media_manifest_conflict','same key changed content conflicts independently');
$another=review_session();$cross=review_upload($another,$png);review_check($cross['descriptor']['media_id']!==$one['descriptor']['media_id'],'same bytes in another submission receive independent UUID');
$audit=json_encode(BadAround_Audit_Log::$records);review_check(strpos($audit,$complete['capability'])===false&&strpos($audit,$root)===false,'new recovery and fence audit events expose no bearer or path');
foreach([$root,$public] as $dir){$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($it as $f){$f->isDir()?rmdir($f->getPathname()):unlink($f->getPathname());}rmdir($dir);}
echo "F1.7C independent review: $checks assertions, $failures failures (".$db->pdo->getAttribute(PDO::ATTR_DRIVER_NAME).")\n";
exit($failures?1:0);
