<?php
/* Temporary staging-only, administrator + token protected, no fixture writes. */
if(!defined('ABSPATH')||($_SERVER['HTTP_HOST']??'')!=='staging.badaround.it'||time()>1792737331)return;
function gpr_assert($ok,$why){if(!$ok)throw new RuntimeException($why);}
function gpr_digest($sql){global $wpdb;$r=$wpdb->get_results($sql,ARRAY_A);gpr_assert(!$wpdb->last_error,'READ_FAILED');return hash('sha256',wp_json_encode($r));}
function gpr_state(){global $wpdb;
 $events="SELECT ID FROM {$wpdb->posts} WHERE post_type='ba_evento'";
 $out=['reports'=>gpr_digest("SELECT * FROM {$wpdb->prefix}ba_reports ORDER BY id"),
 'events'=>gpr_digest("SELECT * FROM {$wpdb->posts} WHERE ID IN ($events) ORDER BY ID"),
 'eventmeta'=>gpr_digest("SELECT * FROM {$wpdb->postmeta} WHERE post_id IN ($events) ORDER BY meta_id"),
 'relationships'=>gpr_digest("SELECT * FROM {$wpdb->term_relationships} WHERE object_id IN ($events) ORDER BY object_id,term_taxonomy_id"),
 'audit'=>gpr_digest("SELECT * FROM {$wpdb->prefix}ba_audit_log WHERE object_type IN ('event','report') ORDER BY id"),
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
 $r=['event'=>$id,'report'=>(int)($row['id']??0),'primary_report'=>$rid,'status'=>get_post_status($id),'moderation'=>get_post_meta($id,'_ba_moderation_status',true),'marker'=>$envelope['public_geo_governance']??null,'request'=>$canonical['location']['public_location_request']??null,'schema_version'=>$canonical['schema_version']??null,'decision'=>get_post_meta($id,'_ba_public_location_decision',true),'public_geo'=>BadAround_Public_Geo_Projection::public_view($id),'projection'=>get_post_meta($id,'_ba_public_geo',true),'render'=>gpr_render($id),'url'=>get_permalink($id),'source_sha256'=>hash('sha256',wp_json_encode([$row['exact_lat']??null,$row['exact_lng']??null,$row['exact_address']??null]))];
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
add_action('rest_api_init',static function(){register_rest_route('geoprec01r-qa/v1','/inspect',[
 'methods'=>'POST','permission_callback'=>static function($r){return current_user_can('manage_options')&&hash_equals('ac625f44b5c6a0fe2e4e8c51c3b1dc1cf099f08572ba9ca4544dc454e39fd4da',hash('sha256',(string)$r->get_param('token')))?true:new WP_Error('geoprec01r_denied','QA authorization required.',['status'=>403]);},
 'callback'=>static function($r){nocache_headers();header('Cache-Control: private, no-store, max-age=0');header('X-Robots-Tag: noindex, nofollow');try{
  $action=$r->get_param('action');gpr_assert(in_array($action,['snapshot','negative'],true),'ACTION_SCOPE');$before=gpr_state();$out=['state'=>$before,'native'=>badaround_native_report_config()['schemaVersion'],'contract_sha'=>hash_file('sha256',WP_PLUGIN_DIR.'/badaround-core/contracts/nf-conditional-v1.0.json'),'media_enabled'=>(new BadAround_Native_Media_Config())->enabled(),'fixtures'=>[]];
  foreach([303,304,269,352,354,356,358] as $id)$out['fixtures'][]=gpr_inspect($id);
  if($action==='negative')$out['negatives']=gpr_negatives();gpr_assert($before===gpr_state(),'READ_ONLY_INVARIANT');return $out;
 }catch(Throwable $e){return new WP_Error('geoprec01r_failure',$e->getMessage(),['status'=>500]);}
 }]);});
