<?php
require __DIR__.'/geo-public-04-moderation.php';
class GPContextDB extends Geo04_DB {
 public $prefix='gprec_sql_';public $pdo;
 function __construct(){
  $this->pdo=new PDO(getenv('BA_TEST_DSN')?:'sqlite::memory:',getenv('BA_TEST_DB_USER')?:'root',getenv('BA_TEST_DB_PASSWORD')?:'');$this->pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
  if($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'){
   $extract=static function($json,$path){$v=json_decode($json,true);foreach(explode('.',substr($path,2)) as $key){if(!is_array($v)||!array_key_exists($key,$v))return null;$v=$v[$key];}return json_encode($v);};
   $this->pdo->sqliteCreateFunction('JSON_EXTRACT',$extract,2);
   $this->pdo->sqliteCreateFunction('JSON_VALID',static function($s){json_decode($s);return json_last_error()===JSON_ERROR_NONE;},1);
   $this->pdo->sqliteCreateFunction('JSON_UNQUOTE',static fn($s)=>$s===null?null:json_decode($s,true),1);
   $this->pdo->sqliteCreateFunction('JSON_CONTAINS_PATH',static function($s,$mode,...$paths)use($extract){foreach($paths as $p)if($extract($s,$p)!==null)return 1;return 0;},-1);
  }
  $this->pdo->exec('DROP TABLE IF EXISTS gprec_sql_ba_reports');
  $this->pdo->exec('CREATE TABLE gprec_sql_ba_reports (id INTEGER PRIMARY KEY, event_id INTEGER, source_type VARCHAR(20), content_original TEXT, deleted_at TEXT)');
 }
 function get_row($sql){$this->last_error='';try{return $this->pdo->query($sql)->fetch(PDO::FETCH_OBJ);}catch(Throwable $e){$this->last_error=$e->getMessage();return null;}}
 function get_var($sql){$r=$this->get_row($sql);return $r?array_values((array)$r)[0]:null;}
 function put($e,$mode,$governance=true){$envelope=['canonical'=>['location'=>['public_location_request'=>$mode,'public_location_request_version'=>'public-location-request-v1']]];if($governance)$envelope['public_geo_governance']='user-public-location/v1';$this->pdo->prepare('INSERT INTO gprec_sql_ba_reports VALUES (?, ?, ?, ?, NULL)')->execute([$e+1000,$e,'native',json_encode($envelope)]);}
}
$wpdb=new GPContextDB();
try{
 foreach(['exact_point','approximate_area'] as $i=>$mode){
  $id=980+$i;gseed($id,$mode);$wpdb->put($id,$mode);$c=$svc->request_context($id);
  gassert(!is_wp_error($c)&&$c['user_authoritative']&&$c['request']===$mode&&$c['allowed']===[],'real SQL request and cutover context '.$mode);
  gassert(BadAround_Publication_Service::uses_versioned_geo($id),'real SQL versioned route '.$mode);
 }
 gseed(982,'exact_point');$wpdb->put(982,'exact_point',false);$c=$svc->request_context(982);
 gassert(!$c['user_authoritative']&&in_array('approximate_area',$c['allowed'],true),'real SQL legacy row retains historical policy');
 // Primary selection is authoritative even with an older secondary report on the event.
 $wpdb->pdo->exec("UPDATE gprec_sql_ba_reports SET event_id=982 WHERE id=1981");$GLOBALS['meta'][982]['_ba_primary_report_id']=1981;
 $c=$svc->request_context(982);gassert($c['user_authoritative']&&$c['request']==='approximate_area','primary report selected instead of oldest secondary');
 echo 'GEO_PREC_01_SQL_CONTEXT=PASS driver='.$wpdb->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)."\n";
}finally{$wpdb->pdo->exec('DROP TABLE IF EXISTS gprec_sql_ba_reports');}
