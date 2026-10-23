<?php
/** Production SQL context + actual admin rendering; no staging access/writes. */
require __DIR__.'/geo-prec-01-sql-context.php';
$wpdb->pdo->exec('CREATE TABLE gprec_sql_ba_reports (id INTEGER PRIMARY KEY,event_id INTEGER,source_type VARCHAR(20),content_original TEXT,deleted_at TEXT)');
$path='wordpress/plugins/badaround-core/includes/class-badaround-moderation-admin.php';
$source=shell_exec('git show 7398815f3a946f194a75c28fcaf48683d1441832:'.$path);
gassert(is_string($source)&&str_contains($source,'class BadAround_Moderation_Admin'),'certified baseline loaded');
// Renamed baseline class executes the original renderer against the same actual SQL context.
$source=str_replace('__DIR__',var_export(dirname(__DIR__).'/wordpress/plugins/badaround-core/includes',true),$source);
eval(substr(str_replace('class BadAround_Moderation_Admin {','class GeoArea01A_Baseline_Admin {',$source),5));
$old=new GeoArea01A_Baseline_Admin();
function ga1a_seed($id,$request,$version,$governed=true){global $wpdb;
 gseed($id);$e=['canonical'=>['location'=>['public_location_request'=>$request,'public_location_request_version'=>$version]]];
 if($governed)$e['public_geo_governance']='user-public-location/v1';
 $wpdb->pdo->prepare('INSERT INTO gprec_sql_ba_reports VALUES (?,?,?,?,NULL)')->execute([$id+1000,$id,'native',json_encode($e)]);
}
function ga1a_render($id,$admin=null){global $wpdb;$before=serialize([$GLOBALS['posts'],$GLOBALS['meta'],$GLOBALS['audit'],$wpdb->pdo->query('SELECT * FROM gprec_sql_ba_reports ORDER BY id')->fetchAll(PDO::FETCH_ASSOC)]);
 ob_start();($admin??new BadAround_Moderation_Admin())->render_geo_decision_box((object)['ID'=>$id]);$h=ob_get_clean();
 gassert($before===serialize([$GLOBALS['posts'],$GLOBALS['meta'],$GLOBALS['audit'],$wpdb->pdo->query('SELECT * FROM gprec_sql_ba_reports ORDER BY id')->fetchAll(PDO::FETCH_ASSOC)]),'render does not mutate report/post/meta/audit');return $h;
}
try{
 $id=10000;
 foreach(['exact_point'=>[null],'approximate_area'=>[50,100,250,500,1000]] as $mode=>$radii)foreach($radii as $r){
  $id++;$request=['mode'=>$mode];if($r!==null)$request['radius_m']=$r;ga1a_seed($id,$request,'public-location-request-v2');$h=ga1a_render($id);
  $label=$mode==='exact_point'?'Punto esatto':'Area approssimativa — circa '.($r===1000?'1 km':$r.' m');
  gassert(str_contains($h,'</strong> '.$label.'</p>'),'exact human label '.$label);
  gassert(!preg_match('/<(input|select|textarea|button|form)\b/i',$h),'no editable geo controls '.$label);
  if($mode==='exact_point')gassert(!str_contains($h,'circa'),'exact has no radius text');
 }
 foreach([['exact_point',true],['approximate_area',true],['exact_point',false],['approximate_area',false]] as [$mode,$gov]){
  $id++;ga1a_seed($id,$mode,'public-location-request-v1',$gov);$GLOBALS['meta'][$id]['_ba_public_radius_m']=1000;
  $h=ga1a_render($id);gassert($h===ga1a_render($id,$old),'legacy complete HTML byte identical '.$mode.' governed='.(int)$gov);
  gassert(!str_contains($h,'circa')&&!str_contains($h,'1000')&&!str_contains($h,'1 km'),'system radius not represented as reporter choice');
 }
 foreach([['mode'=>'approximate_area'],['mode'=>'approximate_area','radius_m'=>75],['mode'=>'approximate_area','radius_m'=>'500']] as $bad){
  $id++;ga1a_seed($id,$bad,'public-location-request-v2');$h=ga1a_render($id);
  gassert(str_contains($h,'Richiesta geografica non verificabile')&&!str_contains($h,'circa')&&!preg_match('/<(input|select|textarea|button)\b/i',$h),'invalid radius fails safe '.json_encode($bad));
 }
 $id++;ga1a_seed($id,['mode'=>'approximate_area','radius_m'=>500],'public-location-request-v99');$h=ga1a_render($id);
 gassert(str_contains($h,'Richiesta geografica non verificabile')&&!str_contains($h,'circa'),'unknown version fails closed');
 echo "GEO_AREA_01A_MODERATION_RENDER=PASS\n";
}finally{$wpdb->pdo->exec('DROP TABLE gprec_sql_ba_reports');}
