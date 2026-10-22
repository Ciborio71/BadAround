<?php
if (!hash_equals('__TOKEN__', $_SERVER['HTTP_X_NF03A_TOKEN'] ?? '')) { http_response_code(404); exit; }
require __DIR__.'/wp-load.php';
header('Content-Type: application/json');header('Cache-Control: no-store');
global $wpdb;
function nf03a_digest($sql){global $wpdb;$r=$wpdb->get_results($sql,ARRAY_A);if($wpdb->last_error)throw new RuntimeException($wpdb->last_error);return hash('sha256',json_encode($r));}
try{
$core=WP_PLUGIN_DIR.'/badaround-core/';$exists=class_exists('BadAround_Report_Conditional_Contract',false);
$out=['core_loaded'=>in_array(realpath($core.'badaround-core.php'),get_included_files(),true),'class_exists'=>$exists,'json_hash'=>hash_file('sha256',$core.'contracts/nf-conditional-v1.0.json'),'native_version'=>badaround_native_report_config()['schemaVersion'],'v2_implicitly_loaded'=>class_exists('BadAround_Report_V2_Validator',false)];
if($exists){$c=BadAround_Report_Conditional_Contract::definition();$out['contract_constant']=BadAround_Report_Conditional_Contract::SHA256;$out['matrix_count']=count($c['matrix']);$out['automatic_count']=count(array_filter($c['matrix'],static function($r){return $r['emergency']['automatic_subtype_notice'];}));}
$out['state']=['reports'=>nf03a_digest("SELECT * FROM {$wpdb->prefix}ba_reports ORDER BY id"),'events'=>nf03a_digest("SELECT * FROM {$wpdb->posts} WHERE post_type='ba_evento' ORDER BY ID"),'eventmeta'=>nf03a_digest("SELECT m.* FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID=m.post_id WHERE p.post_type='ba_evento' ORDER BY m.meta_id"),'protected'=>nf03a_digest("SELECT * FROM {$wpdb->prefix}ba_reports WHERE id BETWEEN 35 AND 67 ORDER BY id"),'terms'=>nf03a_digest("SELECT * FROM {$wpdb->terms} ORDER BY term_id"),'taxonomy'=>nf03a_digest("SELECT * FROM {$wpdb->term_taxonomy} ORDER BY term_taxonomy_id"),'termmeta'=>nf03a_digest("SELECT * FROM {$wpdb->termmeta} ORDER BY meta_id"),'relationships'=>nf03a_digest("SELECT * FROM {$wpdb->term_relationships} ORDER BY object_id,term_taxonomy_id")];
$out['schema']=[];foreach([$wpdb->prefix.'ba_reports',$wpdb->posts,$wpdb->postmeta,$wpdb->terms,$wpdb->term_taxonomy,$wpdb->termmeta,$wpdb->term_relationships] as $t){$row=$wpdb->get_row("SHOW CREATE TABLE `$t`",ARRAY_N);$out['schema'][$t]=hash('sha256',$row[1]);}
$components=BadAround_Report_Version_Registry::components(['schema_version'=>'badaround-report/v1']);$out['v1_components']=array_map('get_class',$components);
echo json_encode($out,JSON_THROW_ON_ERROR);
}catch(Throwable $e){http_response_code(500);echo json_encode(['error'=>$e->getMessage()]);}
