from pathlib import Path
src=Path('tests/f1-14r-publication-contract.php').read_text()
needle='$svc=new BadAround_Publication_Service();'
assert src.count(needle)==1
setup=src.split(needle)[0]
old="function wp_update_post($x,$error=false){foreach($x as $k=>$v){if($k!=='ID')$GLOBALS['posts'][$x['ID']]->$k=$v;}return $x['ID'];}"
new="""function wp_update_post($x,$error=false){
  $id=$x['ID'];
  if(!empty($GLOBALS['inject_exception'])){ throw new RuntimeException('simulated update failure'); }
  if(!empty($GLOBALS['inject_wp_error'])){ return new WP_Error('synthetic_update_failure'); }
  foreach($x as $k=>$v){if($k!=='ID')$GLOBALS['posts'][$id]->$k=$v;}
  if($GLOBALS['posts'][$id]->post_status==='publish'){
    $allowed=false;
    foreach($GLOBALS['publish_guards']??[] as $callback){$allowed=$callback($allowed,$id);}
    if(!$allowed){$GLOBALS['posts'][$id]->post_status='pending';}
  }
  return $id;
}"""
assert setup.count(old)==1
setup=setup.replace(old,new)
old_hooks="function add_filter(){}function remove_filter(){}function do_action(){}"
new_hooks="""function add_filter($name,$cb,$priority=10,$args=1){if($name==='badaround_allow_event_publish'){$GLOBALS['publish_guards'][]=$cb;} }
function remove_filter($name,$cb,$priority=10){if($name==='badaround_allow_event_publish'){$GLOBALS['publish_guards']=array_values(array_filter($GLOBALS['publish_guards']??[],static fn($x)=>$x!==$cb));} }
function do_action(){}"""
assert setup.count(old_hooks)==1
setup=setup.replace(old_hooks,new_hooks)\nsetup=setup.replace('function get_var($q){return 0;}','function get_var($q){return str_contains($q,\"ba_audit_log\") ? ($GLOBALS[\"audit_proof\"]??0) : 0;}')
checks=r"""
$svc=new BadAround_Publication_Service();
function gp_check($ok,$name) { if(!$ok){fwrite(STDERR,"FAIL $name\\n");exit(1);} echo "PASS $name\\n"; }
function scenario($id,$moderation,$wpstatus){
  seed($id,4,'f16-c8','',[
    '_ba_public_location_precision'=>'f32-c6',
    '_ba_public_lat'=>41.67467,
    '_ba_public_lng'=>12.502868,
    '_ba_public_radius_m'=>1000,
    '_ba_moderation_status'=>$moderation
  ]);
  $GLOBALS['posts'][$id]->post_status=$wpstatus;
  $GLOBALS['posts'][$id]->post_title='Public safe synthetic title';
  $GLOBALS['posts'][$id]->post_content='Safe synthetic text, no exact address';
}
$GLOBALS['publish_guards']=[];
scenario(300,'published','publish');
$baseline=[get_post_meta(300,'_ba_public_lat'),get_post_meta(300,'_ba_public_lng'),get_post_meta(300,'_ba_public_radius_m')];
gp_check(true===$svc->prepare_public_projection(300),'A published projection prepare');
gp_check(get_post_status(300)==='publish','A stays published');
gp_check($baseline===[get_post_meta(300,'_ba_public_lat'),get_post_meta(300,'_ba_public_lng'),get_post_meta(300,'_ba_public_radius_m')],'A public coordinates unchanged');
gp_check(count($GLOBALS['publish_guards'])===0,'A no leftover filter');
scenario(301,'approved','pending');
gp_check(true===$svc->prepare_public_projection(301),'B approved preparation');
gp_check(get_post_status(301)==='pending','B approved not auto published');
gp_check(count($GLOBALS['publish_guards'])===0,'B no temporary authorization');
scenario(302,'new','pending');
gp_check(true===$svc->prepare_public_projection(302),'C pending projection');
gp_check(get_post_status(302)==='pending','C pending not public');
scenario(303,'rejected','pending');
gp_check(true===$svc->prepare_public_projection(303),'D rejected projection');
gp_check(get_post_status(303)==='pending','D rejected not public');
for($i=0;$i<3;$i++) gp_check(true===$svc->prepare_public_projection(300) && get_post_status(300)==='publish' && count($GLOBALS['publish_guards'])===0,'E repeat '.($i+1).' idempotent status and hook');
scenario(304,'published','publish');
$GLOBALS['inject_exception']=true;
$thrown=false;
try{$svc->prepare_public_projection(304);}catch(RuntimeException $e){$thrown=true;}
unset($GLOBALS['inject_exception']);
gp_check($thrown && count($GLOBALS['publish_guards'])===0,'F exception cleans authorization');
$GLOBALS['inject_wp_error']=true;
$err=$svc->prepare_public_projection(304);
unset($GLOBALS['inject_wp_error']);
gp_check(is_wp_error($err) && count($GLOBALS['publish_guards'])===0,'F WP_Error cleans authorization');
gp_check(get_post_status(304)==='publish','F original status retained on error');
scenario(305,'published','publish');
$GLOBALS['private_fixture']=['exact_lat'=>41.6,'exact_lng'=>12.5,'exact_address'=>'Private Address 99'];
gp_check(true===$svc->prepare_public_projection(305),'G privacy scenario prepare');
gp_check(true===$svc->prepare_public_projection(305),'G privacy repeat');
gp_check(strpos(get_post(305)->post_content,'Private Address 99')===false,'G private address absent');
gp_check(true===$svc->prepare_public_projection(305) && get_post_status(305)==='publish','G no unintended pending state');
unset($GLOBALS['private_fixture']);
scenario(269,'published','publish');
unset($GLOBALS['meta'][269]['_ba_public_lat'],$GLOBALS['meta'][269]['_ba_public_lng'],$GLOBALS['meta'][269]['_ba_public_radius_m']);
gp_check(true===$svc->prepare_public_projection(269),'H territory-only 269 prepared');
gp_check(get_post_status(269)==='publish' && get_post_meta(269,'_ba_public_lat')==='','H territory-only and published');
scenario(260,'published','publish');
$fixture=[get_post_meta(260,'_ba_public_lat'),get_post_meta(260,'_ba_public_lng'),get_post_meta(260,'_ba_public_radius_m')];
gp_check(true===$svc->prepare_public_projection(260),'I existing fixture 260 prepared');
gp_check(get_post_status(260)==='publish' && $fixture===[get_post_meta(260,'_ba_public_lat'),get_post_meta(260,'_ba_public_lng'),get_post_meta(260,'_ba_public_radius_m')],'I fixture 260 unchanged');

scenario(290,'published','pending');
$GLOBALS['meta'][290]['_ba_primary_report_id']=25;
$GLOBALS['audit_proof']=1;
gp_check(true===$svc->prepare_public_projection(290),'J event 290 projection prepared');
gp_check(true===$svc->publish(290),'J authorized event 290 recovered through service');
gp_check(get_post_status(290)==='publish','J event 290 WordPress published');
gp_check(get_post_meta(290,'_ba_moderation_status')==='published','J moderation unchanged');
gp_check(count($GLOBALS['publish_guards'])===0,'J no dangling temporary filter');
gp_check(true===$svc->publish(290),'J second service call idempotent');
scenario(291,'published','pending');
$GLOBALS['meta'][291]['_ba_primary_report_id']=25;
gp_check(is_wp_error($svc->publish(291)) && get_post_status(291)==='pending','K no recovery for non-290');
scenario(290,'published','pending');
$GLOBALS['meta'][290]['_ba_primary_report_id']=24;
gp_check(is_wp_error($svc->publish(290)) && get_post_status(290)==='pending','K mismatched report refused');
$GLOBALS['meta'][290]['_ba_primary_report_id']=25;
$GLOBALS['audit_proof']=0;
gp_check(is_wp_error($svc->publish(290)) && get_post_status(290)==='pending','K absent historical audit refused');
unset($GLOBALS['audit_proof']);
echo "GEO_PRIV_02D1_REGRESSION=PASS\\n";

"""
Path('tests/.geo-priv-02d1-generated.php').write_text(setup+checks)
