<?php
if(!defined('ABSPATH') || ($_SERVER['HTTP_HOST']??'')!=='staging.badaround.it')exit;
add_action('rest_api_init',static function(){register_rest_route('nf05-qa/v1','/__ROUTE__',array('methods'=>array('GET','POST'),'permission_callback'=>static function($request){return hash_equals('__TOKEN__',(string)$request->get_header('x-nf04-token')) ? true : new WP_Error('nf05r_unauthorized','QA authorization required.',array('status'=>403));},'callback'=>static function($request){
if (!hash_equals('__TOKEN__',$_SERVER['HTTP_X_NF04_TOKEN']??'')){http_response_code(404);exit;}
header('Content-Type: application/json');header('Cache-Control: private, no-store, max-age=0');global $wpdb;
function nf04digest($sql){global $wpdb;$rows=$wpdb->get_results($sql,ARRAY_A);if($wpdb->last_error)throw new RuntimeException($wpdb->last_error);return hash('sha256',json_encode($rows));}
try{
$input=json_decode(file_get_contents('php://input'),true)?:[];
if(isset($input['cases'])){
 $out=[];
 foreach($input['cases'] as $p){$parts=BadAround_Report_Version_Registry::components($p);$normal=$parts[0]->normalize($p);$row=BadAround_Report_Conditional_Contract::row($normal);$active=[];$required=[];
  foreach(BadAround_Report_Conditional_Contract::fields() as $path=>$field)if(BadAround_Report_Conditional_Contract::active($path,$normal,$row)){$active[]=$path;if($row['fields'][$path]['required_when_active']??false)$required[]=$path;}
  $valid=BadAround_Report_Version_Registry::prepare($p);$out[]=['active'=>$active,'required'=>$required,'normalized'=>$normal,'valid'=>!is_wp_error($valid)];
 }
 echo json_encode($out,JSON_THROW_ON_ERROR);exit;
}
$core=WP_PLUGIN_DIR.'/badaround-core/';$exists=class_exists('BadAround_Report_Conditional_Contract',false);$c=BadAround_Report_Conditional_Contract::definition();
$maxReport=(int)$wpdb->get_var("SELECT COALESCE(MAX(id),0) FROM {$wpdb->prefix}ba_reports");$maxPost=(int)$wpdb->get_var("SELECT COALESCE(MAX(ID),0) FROM {$wpdb->posts}");
$oldReport=isset($input['max_report'])?(int)$input['max_report']:$maxReport;$oldPost=isset($input['max_post'])?(int)$input['max_post']:$maxPost;
$out=['core_loaded'=>in_array(realpath($core.'badaround-core.php'),get_included_files(),true),'class_exists'=>$exists,'json_hash'=>hash_file('sha256',$core.'contracts/nf-conditional-v1.0.json'),'constant'=>BadAround_Report_Conditional_Contract::SHA256,'matrix_count'=>count($c['matrix']),'native_version'=>badaround_native_report_config()['schemaVersion'],'max_report'=>$maxReport,'max_post'=>$maxPost,'media_enabled'=>(new BadAround_Native_Media_Config())->enabled()];
$out['state']=['reports'=>nf04digest("SELECT * FROM {$wpdb->prefix}ba_reports WHERE id<=$oldReport ORDER BY id"),'events'=>nf04digest("SELECT * FROM {$wpdb->posts} WHERE post_type='ba_evento' AND ID<=$oldPost ORDER BY ID"),'eventmeta'=>nf04digest("SELECT m.* FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID=m.post_id WHERE p.post_type='ba_evento' AND p.ID<=$oldPost ORDER BY m.meta_id"),'protected'=>nf04digest("SELECT * FROM {$wpdb->prefix}ba_reports WHERE id BETWEEN 35 AND 68 ORDER BY id"),'terms'=>nf04digest("SELECT * FROM {$wpdb->terms} ORDER BY term_id"),'taxonomy'=>nf04digest("SELECT * FROM {$wpdb->term_taxonomy} ORDER BY term_taxonomy_id"),'termmeta'=>nf04digest("SELECT * FROM {$wpdb->termmeta} ORDER BY meta_id"),'relationships'=>nf04digest("SELECT * FROM {$wpdb->term_relationships} WHERE object_id<=$oldPost ORDER BY object_id,term_taxonomy_id")];
$out['schema']=[];foreach([$wpdb->prefix.'ba_reports',$wpdb->posts,$wpdb->postmeta,$wpdb->terms,$wpdb->term_taxonomy,$wpdb->termmeta,$wpdb->term_relationships] as $t){$r=$wpdb->get_row("SHOW CREATE TABLE `$t`",ARRAY_N);$out['schema'][$t]=hash('sha256',preg_replace('/ AUTO_INCREMENT=\d+/','',$r[1]));}
$v1=$wpdb->get_results("SELECT content_original FROM {$wpdb->prefix}ba_reports WHERE id BETWEEN 35 AND 55 ORDER BY id",ARRAY_A);$out['v1_valid']=false;foreach($v1 as $stored){$v=json_decode($stored['content_original'],true)['canonical']??[];if(($v['schema_version']??'')==='badaround-report/v1'){$out['v1_valid']=!is_wp_error(BadAround_Report_Version_Registry::prepare($v));break;}}
$out['historical_versions']=$wpdb->get_results("SELECT id,content_original FROM {$wpdb->prefix}ba_reports WHERE id BETWEEN 35 AND 68 ORDER BY id",ARRAY_A);
foreach($out['historical_versions'] as &$r){$p=json_decode($r['content_original'],true);$r=['id'=>(int)$r['id'],'version'=>$p['schema_version']??null,'canonical_version'=>$p['canonical']['schema_version']??null];}unset($r);
$out['new_reports']=[];
foreach($wpdb->get_results("SELECT id,event_id,content_original FROM {$wpdb->prefix}ba_reports WHERE id>$oldReport ORDER BY id",ARRAY_A) as $r){
 $p=json_decode($r['content_original'],true);$canon=$p['canonical']??[];$event=(int)$r['event_id'];$map=new BadAround_Event_Taxonomy_Map();$cat=$map->resolve_category($canon['event']['category']??'');$sub=$map->resolve_subtype($canon['event']['subtype']??'',$cat);$territory=(new BadAround_Territory_Resolver())->resolve_path($canon['location']??[]);
 $out['new_reports'][]=['report'=>(int)$r['id'],'event'=>$event,'envelope_version'=>$p['schema_version']??null,'canonical'=>$canon,'pending'=>get_post_status($event)==='pending','event_types'=>wp_get_object_terms($event,'ba_tipo_evento',['fields'=>'ids']),'expected_subtype'=>$sub?(int)$sub->term_id:null,'territories'=>wp_get_object_terms($event,'ba_territorio',['fields'=>'ids']),'expected_territory'=>is_wp_error($territory)?null:(int)$territory];
}
$out['new_event_count']=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='ba_evento' AND ID>$oldPost");
echo json_encode($out,JSON_THROW_ON_ERROR);
}catch(Throwable $e){http_response_code(500);echo json_encode(['error'=>$e->getMessage()]);}

exit;
}));});
