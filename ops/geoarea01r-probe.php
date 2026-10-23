<?php
/* Temporary staging-only, administrator + token protected, no fixture writes. */
if(!defined('ABSPATH')||($_SERVER['HTTP_HOST']??'')!=='staging.badaround.it'||time()>1792726846)return;
function gpr_assert($ok,$why){if(!$ok)throw new RuntimeException($why);}
function gpr_digest($sql){global $wpdb;$r=$wpdb->get_results($sql,ARRAY_A);gpr_assert(!$wpdb->last_error,'READ_FAILED');return hash('sha256',wp_json_encode($r));}
function gpr_state(){global $wpdb;
 $events="SELECT ID FROM {$wpdb->posts} WHERE post_type='ba_evento' AND ID<=358";
 $out=['reports'=>gpr_digest("SELECT * FROM {$wpdb->prefix}ba_reports WHERE id<=76 ORDER BY id"),
 'events'=>gpr_digest("SELECT * FROM {$wpdb->posts} WHERE ID IN ($events) ORDER BY ID"),
 'eventmeta'=>gpr_digest("SELECT * FROM {$wpdb->postmeta} WHERE post_id IN ($events) ORDER BY meta_id"),
 'relationships'=>gpr_digest("SELECT * FROM {$wpdb->term_relationships} WHERE object_id IN ($events) ORDER BY object_id,term_taxonomy_id"),
 'audit'=>gpr_digest("SELECT * FROM {$wpdb->prefix}ba_audit_log WHERE (object_type='event' AND object_id BETWEEN 1 AND 358) OR (object_type='report' AND object_id BETWEEN 1 AND 76) ORDER BY id"),
 'terms'=>gpr_digest("SELECT * FROM {$wpdb->terms} ORDER BY term_id"),
 'taxonomy'=>gpr_digest("SELECT * FROM {$wpdb->term_taxonomy} ORDER BY term_taxonomy_id"),
 'termmeta'=>gpr_digest("SELECT * FROM {$wpdb->termmeta} ORDER BY meta_id")];
 $out['schema']=[];foreach($wpdb->get_col('SHOW TABLES') as $t){if(!str_starts_with($t,$wpdb->prefix))continue;$r=$wpdb->get_row("SHOW CREATE TABLE `$t`",ARRAY_N);$out['schema'][$t]=hash('sha256',preg_replace('/ AUTO_INCREMENT=\d+/','',$r[1]));}return $out;
}
function gpr_render($id){
 $old_query=$GLOBALS['wp_query'];$old_post=$GLOBALS['post']??null;
 try{$GLOBALS['wp_query']=new WP_Query(['post_type'=>'ba_evento','post_status'=>['publish','pending'],'p'=>$id]);ob_start();include get_stylesheet_directory().'/template-parts/single-event-layout.php';$html=ob_get_clean();}
 finally{$GLOBALS['wp_query']=$old_query;$GLOBALS['post']=$old_post;}
 preg_match('/<div[^>]*data-ba-map-context="detail"[^>]*>/',$html,$m);
 return ['html_sha256'=>hash('sha256',$html),'map_tag'=>$m[0]??null,'exact_label'=>str_contains($html,'<small>Posizione esatta</small>'),'approx_label'=>str_contains($html,'<small>Posizione approssimativa</small>'),'territory_label'=>str_contains($html,'<small>Zona territoriale</small>'),'private_tokens_present'=>(bool)preg_match('/exact_address|exact_lat|exact_lng|_ba_public_geo_proof|user_authority/',$html)];
}
function gpr_inspect($id){global $wpdb;
 $rid=(int)get_post_meta($id,'_ba_primary_report_id',true);
 $row=$wpdb->get_row($wpdb->prepare("SELECT id,content_original,exact_lat,exact_lng,exact_address FROM {$wpdb->prefix}ba_reports WHERE event_id=%d AND deleted_at IS NULL ORDER BY id LIMIT 1",$id),ARRAY_A);
 $envelope=json_decode($row['content_original']??'',true);$canonical=$envelope['canonical']??[];
 $r=['event'=>$id,'report'=>(int)($row['id']??0),'primary_report'=>$rid,'status'=>get_post_status($id),'moderation'=>get_post_meta($id,'_ba_moderation_status',true),'marker'=>$envelope['public_geo_governance']??null,'request'=>$canonical['location']['public_location_request']??null,'schema_version'=>$canonical['schema_version']??null,'request_version'=>$canonical['location']['public_location_request_version']??null,'decision'=>get_post_meta($id,'_ba_public_location_decision',true),'public_geo'=>BadAround_Public_Geo_Projection::public_view($id),'projection'=>get_post_meta($id,'_ba_public_geo',true),'render'=>gpr_render($id),'url'=>get_permalink($id),'source_sha256'=>hash('sha256',wp_json_encode([$row['exact_lat']??null,$row['exact_lng']??null,$row['exact_address']??null]))];
 if($r['public_geo']&&$r['public_geo']['mode']==='approximate_area')$r['private_point_differs']=abs($r['public_geo']['lat']-(float)$row['exact_lat'])>0.0000001||abs($r['public_geo']['lng']-(float)$row['exact_lng'])>0.0000001;
 if(class_exists('BadAround_Geo_Moderation_Service')){
  $ctx=(new BadAround_Geo_Moderation_Service())->request_context($id);$r['context']=is_wp_error($ctx)?['error'=>$ctx->get_error_code()]:$ctx;
  $a=new BadAround_Moderation_Admin();ob_start();$a->render_geo_decision_box(get_post($id));$box=ob_get_clean();ob_start();$a->render_public_box(get_post($id));$public=ob_get_clean();
  $r['moderation_ui']=['requested_readonly'=>str_contains($box,'Posizione pubblica richiesta'),'has_mode_control'=>(bool)preg_match('/<(select|input)[^>]*name="mode"/',$box),'has_coordinate_controls'=>(bool)preg_match('/name="ba_public_meta\[(_ba_public_lat|_ba_public_lng|_ba_public_radius_m)\]"/',$public),'geo_ui_text'=>wp_strip_all_tags($box)];
 }
 $r['audit_sha256']=gpr_digest($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ba_audit_log WHERE (object_type='event' AND object_id=%d) OR (object_type='report' AND object_id=%d) ORDER BY id",$id,$r['report']));
 return $r;
}
function gpr_negatives(){
 $reader=new ReflectionMethod('BadAround_Discovery_Query','public_geo');$discovery=new BadAround_Discovery_Query();$id=352;$out=[];
 foreach(['missing_projection','missing_all_metadata','unknown_authority','source_proof_mismatch','mirror_mismatch'] as $case){
  $p=get_post_meta($id,'_ba_public_geo_proof',true);$override=[];
  switch($case){
   case 'missing_projection':$override['_ba_public_geo']='';break;
   case 'missing_all_metadata':$override=['_ba_public_geo'=>'','_ba_public_geo_proof'=>'','_ba_public_location_decision'=>'','_ba_public_radius_m'=>1000];break;
   case 'unknown_authority':$p['user_authority']['authority']='unknown';$override['_ba_public_geo_proof']=$p;break;
   case 'source_proof_mismatch':$p['source']='in-memory-mismatch';$override['_ba_public_geo_proof']=$p;break;
   case 'mirror_mismatch':$override['_ba_public_lat']=42;break;
  }
  $filter=static function($check,$object,$key,$single)use($id,$override){if((int)$object===$id&&array_key_exists($key,$override))return [$override[$key]];return $check;};
  add_filter('get_post_metadata',$filter,PHP_INT_MAX,4);
  try{$r=gpr_render($id);$geo=BadAround_Public_Geo_Projection::public_view($id);$d=$reader->invoke($discovery,$id);gpr_assert($geo===null&&$d===null&&$r['map_tag']===null&&!$r['approx_label']&&!$r['exact_label'],'NEGATIVE_CONSUMER_'.$case);$out[$case]='FAIL_CLOSED';}
  finally{remove_filter('get_post_metadata',$filter,PHP_INT_MAX);}
 }
 $unknown=BadAround_Public_Geo_Contract::user_authority(['public_geo_governance'=>'unknown-governance']);gpr_assert(is_wp_error($unknown),'UNKNOWN_GOVERNANCE');$out['unknown_governance_marker']=$unknown->get_error_code();
 foreach([356,358] as $event){gpr_assert(BadAround_Public_Geo_Projection::public_view($event)===null,'UNPUBLISHED_PUBLIC_GEO');$out['unpublished_'.$event]='NO_PUBLIC_GEOMETRY';}
 // Actual moderator request handler; the governed context rejects before any metadata/audit write.
 foreach([356,358] as $event)foreach(['exact_point','approximate_area','unsupported'] as $mode){$result=(new BadAround_Moderation_Admin())->process_geo_decision_request($event,['mode'=>$mode,'revision'=>BadAround_Geo_Moderation_Service::revision(get_post_meta($event,'_ba_public_location_decision',true)),'reason_code'=>'privacy','exact_confirmed'=>true,'proof'=>['mode'=>'unsupported'],'public_location_request'=>'unsupported'],wp_create_nonce('ba_save_geo_decision_'.$event),'POST');gpr_assert(is_wp_error($result)&&$result->get_error_code()==='ba_geo_decision_user_authoritative','CRAFTED_OVERRIDE');$out['crafted_'.$event.'_'.$mode]=$result->get_error_code();}
 return $out;
}
add_action('rest_api_init',static function(){register_rest_route('geoarea01r-qa/v1','/inspect',[
 'methods'=>'POST','permission_callback'=>static function($r){return current_user_can('manage_options')&&hash_equals('cb226d4797ed6e7b0a9a8bd5c9b69cba9678c3cb00ffea668d24cf25f0d78073',hash('sha256',(string)$r->get_param('token')))?true:new WP_Error('geoarea01r_denied','QA authorization required.',['status'=>403]);},
 'callback'=>static function($r){nocache_headers();header('Cache-Control: private, no-store, max-age=0');header('X-Robots-Tag: noindex, nofollow');try{
  $action=$r->get_param('action');gpr_assert(in_array($action,['snapshot','negative','inspect','validate','submit','publish'],true),'ACTION_SCOPE');$before=gpr_state();
  $allowed=array('c6485fe9-aea6-4a12-a06e-ad9f6f43853f','5d21b702-d68c-4c60-be6f-bfcf7d25a3d7','a1528a9d-b937-4ba2-a49b-1a93309ee21b','81ead244-6243-480c-b825-eb6e9a352c15');
  if($action==='validate'){
   $results=[];foreach((array)$r->get_param('cases') as $p){$v=BadAround_Report_Version_Registry::prepare($p);$results[]=is_wp_error($v)?['error'=>$v->get_error_code()]:['valid'=>true];}
   gpr_assert($before===gpr_state(),'VALIDATION_MUTATED_PROTECTED_STATE');return ['results'=>$results];
  }
  if($action==='submit'){
   $p=$r->get_param('payload');gpr_assert(in_array($p['submission_id']??null,$allowed,true),'UUID_SCOPE');
   gpr_assert(($p['schema_version']??null)==='badaround-report/v2'&&($p['reporter']['social_visibility']??null)==='declined'&&empty($p['media']['items'])&&str_contains($p['content']['description']??'','QA sintetica GEO-AREA-01R'),'SYNTHETIC_SCOPE');
   $req=new WP_REST_Request('POST','/badaround/v1/reports');$req->set_header('content-type','application/json');$req->set_header('x-badaround-intake','badaround-report/v1');$req->set_body(wp_json_encode($p));
   $res=rest_do_request($req);gpr_assert($before===gpr_state(),'SUBMISSION_MUTATED_PROTECTED_STATE');return ['http'=>$res->get_status(),'body'=>$res->get_data()];
  }
  if($action==='publish'){
   $id=(int)$r->get_param('event');gpr_assert($id>358,'NEW_EVENT_ONLY');
   $row=$wpdb->get_row($wpdb->prepare("SELECT content_original FROM {$wpdb->prefix}ba_reports WHERE event_id=%d AND deleted_at IS NULL ORDER BY id LIMIT 1",$id),ARRAY_A);$e=json_decode($row['content_original']??'',true);$p=$e['canonical']??[];
   gpr_assert(in_array($p['submission_id']??null,$allowed,true)&&($p['reporter']['social_visibility']??null)==='declined','FIXTURE_SCOPE');
   $mod=(new BadAround_Moderation_Service())->transition($id,'approved');gpr_assert(!is_wp_error($mod),'APPROVAL_FAILED');$pub=(new BadAround_Publication_Service())->publish($id);gpr_assert(!is_wp_error($pub),'PUBLICATION_FAILED:'.(is_wp_error($pub)?$pub->get_error_code():''));
   return ['fixture'=>gpr_inspect($id)];
  }
  $out=['state'=>$before,'native'=>badaround_native_report_config()['schemaVersion'],'contract_sha'=>hash_file('sha256',WP_PLUGIN_DIR.'/badaround-core/contracts/nf-conditional-v1.0.json'),'media_enabled'=>(new BadAround_Native_Media_Config())->enabled(),'fixtures'=>[]];
  foreach([303,304,269,352,354,356,358] as $id)$out['fixtures'][]=gpr_inspect($id);
  if($action==='inspect'){foreach((array)$r->get_param('events') as $id){gpr_assert((int)$id>358,'NEW_FIXTURE_ONLY');$out['new_fixtures'][]=gpr_inspect((int)$id);}}if($action==='negative')$out['negatives']=gpr_negatives();gpr_assert($before===gpr_state(),'READ_ONLY_INVARIANT');return $out;
 }catch(Throwable $e){return new WP_Error('geoarea01r_failure',$e->getMessage(),['status'=>500]);}
 }]);});

add_action('template_redirect',static function(){
 if(!isset($_GET['geoarea01r_qa'])||!hash_equals('cb226d4797ed6e7b0a9a8bd5c9b69cba9678c3cb00ffea668d24cf25f0d78073',hash('sha256',(string)$_GET['geoarea01r_qa'])))return;
 nocache_headers();header('Cache-Control: private, no-store, max-age=0');header('X-Robots-Tag: noindex, nofollow');
 add_action('wp_head',static function(){?>
 <script>
 (()=>{'use strict';const circles=[];let imported;
 function wrap(){const g=window.google?.maps;if(!g?.Circle||g.Circle.__geoareaQA)return;const Original=g.Circle;const Observed=new Proxy(Original,{construct(C,args){const c=Reflect.construct(C,args);circles.push(c);return c;}});Observed.__geoareaQA=true;g.Circle=Observed;}
 const timer=setInterval(()=>{const g=window.google?.maps;if(!g)return;wrap();if(g.importLibrary&&g.importLibrary!==imported){const original=g.importLibrary;imported=function(...args){return original.apply(this,args).then(result=>{wrap();return result;});};g.importLibrary=imported;}},100);
 addEventListener('DOMContentLoaded',()=>{const box=document.createElement('aside');box.id='geoarea01r-live-qa';box.style.cssText='padding:8px;background:#fff;border:2px solid #087f8c;color:#111;font:12px monospace;overflow-wrap:anywhere';box.innerHTML='<strong>QA temporanea — Google Maps reale</strong><output id="geoarea01r-google-proof"></output><button type="button" id="geoarea01r-zoom-in">QA zoom +</button><button type="button" id="geoarea01r-zoom-out">QA zoom −</button>';document.body.append(box);
 function active(){return circles.filter(c=>c.getMap());}
 for(const [id,delta] of [['geoarea01r-zoom-in',1],['geoarea01r-zoom-out',-1]])document.getElementById(id).onclick=()=>{for(const map of new Set(active().map(c=>c.getMap())))map.setZoom(map.getZoom()+delta);};
 setInterval(()=>{document.getElementById('geoarea01r-google-proof').textContent=JSON.stringify(active().map(c=>({radius_m:c.getRadius(),center:{lat:c.getCenter().lat(),lng:c.getCenter().lng()},zoom:c.getMap().getZoom(),context:c.getMap().getDiv().dataset.baMapContext||'native',event:c.getMap().getDiv().dataset.baEventId||null,actual_google_maps:true})));},100);
 });})();
 </script>
 <?php },0);
});
