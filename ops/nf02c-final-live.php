<?php
if(!defined('ABSPATH'))exit;
add_action('plugins_loaded', function(){
function nff_fixture() {
	return array(
		'schema_version'=>'badaround-report/v1',
		'submission_id'=>'123e4567-e89b-42d3-a456-426614174000',
		'event'=>array('category'=>'hazard','subtype'=>'hazard_road_obstruction'),
		'reporter'=>array(
			'relationship'=>'territory_observer','first_name'=>'QA','last_name'=>'NFFINAL',
			'email'=>'MARIO@example.invalid','phone'=>'',
			'public_identity_mode'=>'anonymous','contact_preference'=>'none'
		),
		'location'=>array(
			'exact_address'=>'Via Roma 10, Pomezia','exact_lat'=>41.669,'exact_lng'=>12.501,
			'place_id'=>'place-test','area_label'=>'Pomezia',
			'region'=>'Lazio','province'=>'Roma','municipality'=>'Pomezia','locality'=>'Torvaianica',
			'public_precision'=>'area',
			'place_type'=>'road_sidewalk','immediate_danger'=>'no'
		),
		'time'=>array('mode'=>'exact','date'=>'2026-10-01','knowledge'=>'unknown'),
		'content'=>array('description'=>'Ostacolo presente sulla carreggiata.'),
		'damage'=>array('status'=>'no'),
		'authority'=>array('status'=>'no'),
		'media'=>array('availability'=>'no','items'=>array()),
		'reward'=>array('status'=>'none'),
		'consents'=>array(
			'truthfulness'=>true,'media_rights'=>true,'publication_rules'=>true,
			'terms'=>true,'privacy'=>true,'version'=>'native-v1'
		),
	);
}

function nff_code($r){return is_wp_error($r)?$r->get_error_code():($r['error']['code']??'success');}
function nff_row($id){global $wpdb;return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ba_reports WHERE id=%d",$id),ARRAY_A);}
function nff_wait($fn,$why,$seconds=8){$until=microtime(true)+$seconds;while(!$fn()){if(microtime(true)>$until)throw new RuntimeException('Harness timeout: '.$why);usleep(10000);}}
function nff_save($path,$data){$tmp=$path.'.tmp.'.getmypid();file_put_contents($tmp,wp_json_encode($data));rename($tmp,$path);}
class NFFRaceRepo extends BadAround_Report_Repository {
 public $job,$held=false,$contention=false,$observed=null;
 function __construct($j){$this->job=$j;}
 private function hold($id){if($this->held||empty($this->job['race']))return;$this->held=true;$base=$this->job['wave'];nff_save($base.'.owner',['report_id'=>$id,'pid'=>getmypid()]);if(!empty($this->job['independent'])){usleep(200000);return;}nff_wait(fn()=>is_file($base.'.probe'),'contending request');}
 function acquire_native_lock($uuid,$timeout=0){$ok=parent::acquire_native_lock($uuid,$timeout);if($ok&&($this->job['hold_at']??'')==='lock')$this->hold($this->find_by_native_submission($uuid));if(!$ok&&!empty($this->job['race'])){$this->contention=true;$base=$this->job['wave'];nff_wait(fn()=>is_file($base.'.owner'),'reserved owner');$id=(int)$this->find_by_native_submission($uuid);$row=$id?nff_row($id):null;$stored=$row?json_decode($row['content_original']??'',true):null;$this->observed=['report_id'=>$id,'version'=>$stored['schema_version']??'bare-v1','status'=>$row['status']??null];file_put_contents($base.'.probe',getmypid());}return $ok;}
 function create_native_intake_record($uuid){$id=parent::create_native_intake_record($uuid);if(!is_wp_error($id))$this->hold($id);return $id;}
 function create_versioned_native_intake_record($canonical){$id=parent::create_versioned_native_intake_record($canonical);if(!is_wp_error($id))$this->hold($id);return $id;}
}
class NFFPauseTax extends BadAround_Event_Taxonomy_Map {function assign($id,$report){throw new RuntimeException('NFF_CONTROLLED_EVENT_PAUSE');}}
class NFFBeforeReserve extends BadAround_Report_Repository {function acquire_native_lock($s,$t=0){throw new RuntimeException('NFF_BEFORE_RESERVATION');}}
function nff_gold($repo,$tax=null){return new BadAround_Native_Report_Golden_Path_Service(new BadAround_Native_Report_Intake_Service(null,null,$repo),new BadAround_Report_Persistence_Service($repo,$tax),$repo);}

function nf02c2_row($id){return nff_row($id); }
function nf02c2_hash($v){return hash('sha256',wp_json_encode($v));}

function nff_payload($v){$p=nff_fixture();$p['schema_version']=$v;$p['submission_id']=wp_generate_uuid4();$p['reporter']['first_name']='QA';$p['reporter']['last_name']='NFFINAL';$p['reporter']['email']='nf-final@example.invalid';$p['reporter']['phone']='';$p['content']['description']='NF-UX-02C final QA synthetic event. DO NOT PUBLISH.';$p['location']['public_location_request']='approximate_area';$p['location']['public_location_request_version']='public-location-request-v1';$p['reporter']['social_visibility']='declined';$p['consents']['social_visibility_version']='social-preference-v1';return $p;}

function nff_invariants($p){global $wpdb;$repo=new BadAround_Report_Repository();$id=$repo->find_by_native_submission($p['submission_id']);$identity=$repo->native_source_identity($p['submission_id']);$n=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}ba_reports WHERE source_type='native' AND source_form_id=%d AND source_entry_id=%d",$identity['form_id'],$identity['entry_id']));nff_ck($n===1,'max one report per UUID');$events=get_posts(['post_type'=>'ba_evento','post_status'=>'any','fields'=>'ids','posts_per_page'=>3,'meta_key'=>'_ba_primary_report_id','meta_value'=>$id]);nff_ck(count($events)===1,'max one event per UUID');$event=(int)$events[0];nff_ck((int)$repo->event_id_for_report($id)===$event,'stable two-way linkage');nff_ck(get_post_status($event)==='pending'&&get_post_meta($event,'_ba_moderation_status',true)==='new','pending only');$c=BadAround_Report_Version_Registry::prepare($p);nff_ck($repo->native_payload_matches_report($id,$c),'canonical winner unmodified');$row=nff_row($id);$stored=json_decode($row['content_original'],true);nff_ck($stored['schema_version']===$p['schema_version']&&$stored['canonical']['schema_version']===$p['schema_version'],'coherent immutable version');$map=new BadAround_Event_Taxonomy_Map();$expected=[(int)$map->resolve_category('hazard')->term_id,(int)$map->resolve_subtype('hazard_road_obstruction')->term_id];sort($expected);$actual=array_map('intval',wp_get_object_terms($event,'ba_tipo_evento',['fields'=>'ids']));sort($actual);nff_ck($actual===$expected,'category and subtype complete without semantic duplicates');$territory=(new BadAround_Territory_Resolver())->resolve_path($c['location']);nff_ck(array_map('intval',wp_get_object_terms($event,'ba_territorio',['fields'=>'ids']))===[(int)$territory],'territory complete');nff_ck(!get_post_meta($event,'_ba_public_geo',true)&&!get_post_meta($event,'_ba_public_lat',true),'no public projection');nff_ck($stored['canonical']['reporter']['social_visibility']==='declined','Social preference private');nff_ck($stored['canonical']['location']['public_location_request']==='approximate_area'&&$stored['canonical']['location']['exact_lat']===41.669,'private GEO preserved');return ['report_id'=>$id,'event_id'=>$event,'canonical_sha256'=>hash('sha256',$row['content_original'])];}

function nf02c2_snapshot(){
 global $wpdb;$protected=[];
 foreach(range(35,56) as $id){$r=nf02c2_row($id);$eid=$id+277;$protected[$id]=['row_hash'=>nf02c2_hash($r),'content_hash'=>hash('sha256',$r['content_original']),'event_id'=>$eid,'event_status'=>get_post_status($eid),'event_hash'=>nf02c2_hash($wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->posts} WHERE ID=%d",$eid),ARRAY_A)),'meta_hash'=>nf02c2_hash($wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->postmeta} WHERE post_id=%d ORDER BY meta_id",$eid),ARRAY_A)),'relations_hash'=>nf02c2_hash($wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->term_relationships} WHERE object_id=%d ORDER BY term_taxonomy_id",$eid),ARRAY_A))];}
 $schema=[];$tables=$wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($wpdb->prefix.'ba_').'%'));sort($tables);foreach($tables as $t){$schema[$t]=preg_replace('/AUTO_INCREMENT=[0-9]+/','AUTO_INCREMENT=COUNTER',wp_json_encode($wpdb->get_results('SHOW CREATE TABLE `'.str_replace('`','``',$t).'`',ARRAY_N)));}
 return ['protected'=>$protected,'schema'=>nf02c2_hash($schema),'terms'=>nf02c2_hash($wpdb->get_results("SELECT t.term_id,t.name,t.slug,tt.taxonomy,tt.parent,tt.description FROM {$wpdb->terms} t JOIN {$wpdb->term_taxonomy} tt ON tt.term_id=t.term_id ORDER BY t.term_id,tt.taxonomy",ARRAY_A)),'media_sessions'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ba_native_media_sessions"),'media_items'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ba_native_media_items")];
}

function nff_ck($ok,$why){global $nff_checks;$nff_checks[$why]=(bool)$ok;if(!$ok)throw new RuntimeException($why);}
function nff_live_dir(){return __DIR__.'/__WORKDIR__';}
function nff_live_load($name){return json_decode(file_get_contents(nff_live_dir().'/'.$name.'.json'),true);}
function nff_live_save($name,$data){nff_save(nff_live_dir().'/'.$name.'.json',$data);}
function nff_live_worker($job,$path){
 $out=['pid'=>getmypid(),'started'=>microtime(true)];
 file_put_contents($path.'.ready','ready');
 nff_wait(fn()=>is_file($job['peer'].'.ready'),'live concurrent peer',15);
 $repo=new NFFRaceRepo($job);
 $r=nff_gold($repo)->submit($job['payload']);
 $out+=['code'=>nff_code($r),'report_id'=>$r['report_id']??0,'event_id'=>$r['event_id']??0,'contention'=>$repo->contention,'observed_reserved'=>$repo->observed,'finished'=>microtime(true)];
 nff_save($path.'.result',$out);return $out;
}
add_action('rest_api_init',function(){
 $permission=static function(){return ($_SERVER['HTTP_HOST']??'')==='staging.badaround.it'&&current_user_can('manage_options');};
 foreach(['start','init','worker','finish','final'] as $action)register_rest_route('nf-final-qa/v1','/'.$action,['methods'=>'POST','permission_callback'=>$permission,'callback'=>function($req)use($action){
 global $nff_checks,$wpdb;$nff_checks=[];$dir=nff_live_dir();
 try{
  if($action==='start'){
   if(is_file($dir.'/baseline.json'))return ['ready'=>true,'existing'=>true];
   wp_mkdir_p($dir);chmod($dir,0700);
   nff_ck(hash_file('sha256',WP_PLUGIN_DIR.'/badaround-core/includes/class-badaround-report-repository.php')==='75064b889a61b35b507ec326b343aa5c48ba22d90c3c051b72eb828042892c3b','C1_runtime');
   nff_ck(hash_file('sha256',WP_PLUGIN_DIR.'/badaround-core/includes/class-badaround-report-persistence-service.php')==='fa2a5129ab3e852c1eca6052cda3bdbce7a47f8cd209f939f2421de22205c820','C2_runtime');
   nff_live_save('baseline',nf02c2_snapshot());nff_live_save('waves',[]);return ['ready'=>true,'protected'=>22];
  }
  $name=$req->get_param('case');$names=['v1_same','v1_different','v2_same','v2_different','v2_stale','cross_version','different_uuid','event_race_v1','event_race_v2'];
  if(in_array($action,['init','worker','finish'])&&!in_array($name,$names,true))throw new RuntimeException('Unknown QA case');
  if($action==='init'){
   if(is_file($dir.'/'.$name.'.json'))return ['ready'=>true,'existing'=>true];
   $v=str_contains($name,'v1')?'badaround-report/v1':'badaround-report/v2';$a=nff_payload($v);$a['content']['description'].=' staging '.$name;$b=$a;
   if(str_ends_with($name,'different'))$b['content']['description'].=' different active value';
   if($name==='v2_stale')$b['vehicle']=['type'=>'car'];
   if($name==='cross_version')$b['schema_version']='badaround-report/v1';
   if($name==='different_uuid')$b['submission_id']=wp_generate_uuid4();
   $event_before=0;
   if(str_starts_with($name,'event_race')){
    $repo=new BadAround_Report_Repository();try{nff_gold($repo,new NFFPauseTax())->submit($a);}catch(RuntimeException $e){if($e->getMessage()!=='NFF_CONTROLLED_EVENT_PAUSE')throw $e;}
    $rid=$repo->find_by_native_submission($a['submission_id']);nff_ck($rid>56,'new_synthetic_only');
    $events=get_posts(['post_type'=>'ba_evento','post_status'=>'any','fields'=>'ids','meta_key'=>'_ba_primary_report_id','meta_value'=>$rid]);nff_ck(count($events)===1,'C2_original_one_insert');$event_before=(int)$events[0];
   }
   nff_live_save($name,['payloads'=>[$a,$b],'event_before'=>$event_before]);
   return ['ready'=>true,'case'=>$name];
  }
  if($action==='worker'){
   $i=(int)$req->get_param('worker');if(!in_array($i,[0,1],true))throw new RuntimeException('Invalid worker');$path=$dir.'/'.$name.'-'.$i;
   if(is_file($path.'.result'))return json_decode(file_get_contents($path.'.result'),true);
   if(!@mkdir($path.'.running',0700))return new WP_REST_Response(['error'=>'QA worker already running'],409);
   $wave=nff_live_load($name);$job=['payload'=>$wave['payloads'][$i],'race'=>true,'wave'=>$dir.'/'.$name,'hold_at'=>str_starts_with($name,'event_race')?'lock':'create','peer'=>$dir.'/'.$name.'-'.(1-$i),'independent'=>$name==='different_uuid'];
   return nff_live_worker($job,$path);
  }
  if($action==='finish'){
   $wave=nff_live_load($name);$p=$wave['payloads'];$out=[];foreach([0,1] as $i){$path=$dir.'/'.$name.'-'.$i.'.result';if(!is_file($path))throw new RuntimeException('Worker result missing');$out[]=json_decode(file_get_contents($path),true);}
   nff_ck(max($out[0]['started'],$out[1]['started'])<min($out[0]['finished'],$out[1]['finished']),'requests_overlap');
   if($name!=='different_uuid')nff_ck((int)$out[0]['contention']+(int)$out[1]['contention']===1,'real_named_lock_contention');
   $winner=$out[0]['code']==='success'?0:1;nff_ck($out[$winner]['code']==='success','one_winner');
   $conflict=in_array($name,['v1_different','v2_different','cross_version']);$retry=[];
   foreach($p as $i=>$payload){$r=nff_gold(new BadAround_Report_Repository())->submit($payload);nff_ck(nff_code($r)===($conflict&&$i!==$winner?'duplicate_submission':'success'),'retry_expected_'.$i);$retry[]=['code'=>nff_code($r),'report_id'=>$r['report_id']??0,'event_id'=>$r['event_id']??0];}
   $state=nff_invariants($p[$winner]);nff_ck($state['report_id']>56&&$state['event_id']>333,'new_synthetic_identity');
   if($wave['event_before'])nff_ck($state['event_id']===$wave['event_before'],'C2_same_event_recovered');
   $fixtures=[$state];if($name==='different_uuid'){$other=nff_invariants($p[1-$winner]);nff_ck($other['report_id']!==$state['report_id']&&$other['event_id']!==$state['event_id'],'independent_submissions');$fixtures[]=$other;}
   foreach($p as $i=>$payload)if($retry[$i]['code']==='success'){$r=nff_gold(new BadAround_Report_Repository())->submit($payload);nff_ck(nff_code($r)==='success'&&$r['report_id']===$retry[$i]['report_id']&&$r['event_id']===$retry[$i]['event_id'],'response_loss_stable_'.$i);}
   if($name==='v2_same'){$repo=new BadAround_Report_Repository();$id=$state['report_id'];$bytes=nff_row($id)['content_original'];$c=BadAround_Report_Version_Registry::prepare($p[$winner]);nff_ck($repo->persist_canonical_private_data($id,$c),'C1_identical');$c['content']['description'].=' replacement';nff_ck(!$repo->persist_canonical_private_data($id,$c)&&nff_row($id)['content_original']===$bytes,'C1_active_rejected_unchanged');}
   $result=['pass'=>true,'case'=>$name,'initial'=>$out,'retry'=>$retry,'fixtures'=>$fixtures,'checks'=>$nff_checks];$waves=nff_live_load('waves');$waves[$name]=$result;nff_live_save('waves',$waves);return $result;
  }
  if($action==='final'){
   $waves=nff_live_load('waves');nff_ck(count($waves)===9,'all_live_waves_complete');$before=nff_live_load('baseline');$after=nf02c2_snapshot();nff_ck($before===$after,'protected_schema_registry_media_unchanged');
   foreach(range(35,56) as $id){$row=nff_row($id);$payload=json_decode($row['content_original'],true);$view=(new BadAround_Moderation_Admin())->canonical_report_view($payload);nff_ck(is_array($view),'historical_read_'.$id);}
   $negative=nff_row(56);nff_ck(empty($negative['event_id'])&&get_post_status(333)==='pending','negative_fixture_preserved');
   nff_ck(BadAround_Report_Schema::VERSION==='badaround-report/v1','Native_stays_v1');nff_ck((new BadAround_Native_Media_Service())->ready()===false,'media_fail_closed');
   foreach(['badaround-report/v1','badaround-report/v2'] as $v){$p=nff_payload($v);$p['media']=['availability'=>'yes','items'=>[['media_id'=>wp_generate_uuid4(),'mime_type'=>'image/png','file_size'=>100,'extension'=>'png']]];$r=(new BadAround_Native_Media_Report_Adapter())->submit($p,'invalid');nff_ck(nff_code($r)!=='success'&&!(new BadAround_Report_Repository())->find_by_native_submission($p['submission_id']),$v.'_media_rejected_no_report');}
   $result=['pass'=>true,'waves'=>$waves,'before'=>$before,'after'=>$after,'checks'=>$nff_checks];nff_save(__DIR__.'/__RESULT_FILE__',$result);return ['pass'=>true,'wave_count'=>count($waves),'protected'=>22];
  }
 }catch(Throwable $e){$error=['pass'=>false,'action'=>$action,'case'=>$name??null,'error'=>$e->getMessage(),'checks'=>$nff_checks];nff_save(__DIR__.'/__RESULT_FILE__',$error);return new WP_REST_Response($error,500);}
 }]);
});
add_action('admin_menu',function(){add_management_page('NF final QA','NF final QA','manage_options','nf-final-qa',function(){
 echo '<div class="wrap"><h1>NF-UX-02C final QA</h1><button id="nf-run" class="button button-primary">Run controlled final QA</button><pre id="nf-output">Ready</pre></div>';
 $nonce=wp_create_nonce('wp_rest');
 echo '<script>const nfNonce='.wp_json_encode($nonce).';document.getElementById("nf-run").onclick=async function(){this.disabled=true;const out=document.getElementById("nf-output");async function call(action,data={}){const r=await fetch("/wp-json/nf-final-qa/v1/"+action,{method:"POST",headers:{"Content-Type":"application/json","X-WP-Nonce":nfNonce},body:JSON.stringify(data)});const v=await r.json();if(!r.ok||v.pass===false)throw Error(JSON.stringify(v));return v;}try{out.textContent="Starting";await call("start");for(const name of ["v1_same","v1_different","v2_same","v2_different","v2_stale","cross_version","different_uuid","event_race_v1","event_race_v2"]){out.textContent+="\\n"+name+" running";await call("init",{case:name});await Promise.all([0,1].map(worker=>call("worker",{case:name,worker})));const r=await call("finish",{case:name});out.textContent+=" PASS "+JSON.stringify(r.fixtures);}out.textContent+="\\nFINAL "+JSON.stringify(await call("final"));}catch(e){out.textContent+="\\nSTOP "+e.message;}};</script>';
});});

}, 99);
