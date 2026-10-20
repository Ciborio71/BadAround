<?php
/** Real geographic B3 audit, isolated database. */
define('ABSPATH',__DIR__.'/');
function get_current_user_id(){return 42;}
function absint($x){return abs((int)$x);}
function wp_json_encode($x){return json_encode($x);}
function wp_generate_uuid4(){return 'synthetic-publication-request';}
function current_time($format,$gmt=false){return '2026-10-20 18:00:00';}
class Audit05DB{public $prefix='wp_',$rows=[],$fail=false;function insert($table,$row,$format){if($this->fail)return false;$this->rows[]=$row;return 1;}}
$wpdb=new Audit05DB();
require dirname(__DIR__).'/wordpress/plugins/badaround-core/includes/class-badaround-audit-log.php';
function assert05($v,$label){if(!$v){fwrite(STDERR,'FAIL '.$label."\n");exit(1);}echo 'PASS '.$label."\n";}
foreach(['exact_point','approximate_area'] as $mode){
 $c=['location'=>['public_location_request'=>$mode,'exact_lat'=>41.8991622,'exact_lng'=>12.4730719,'exact_address'=>'Private test street 99'],'decision'=>['mode'=>$mode]];
 $p=['version'=>'public-location/v1','mode'=>$mode,'lat'=>41.9,'lng'=>12.47,'radius_meters'=>$mode==='exact_point'?0:1000];
 assert05(BadAround_Audit_Log::geographic_publication(303,$c,$p)===1,'audit persisted '.$mode);
 $row=end($wpdb->rows);$v=json_decode($row['new_value'],true);
 assert05($row['actor_user_id']===42&&$row['object_id']===303&&$row['created_at']==='2026-10-20 18:00:00','audit actor object UTC');
 assert05($v['user_request']===$mode&&$v['moderator_decision']===$mode&&$v['public_projection_mode']===$mode&&$v['publication_event']==='event_published'&&$v['policy_version']==='public-location/v1','required publication descriptor');
 assert05(!preg_match('/exact_lat|exact_lng|exact_address|41\.899|12\.473|Private test/',json_encode($row)),'no private coordinates or address in audit');
}
$wpdb->fail=true;assert05(false===BadAround_Audit_Log::geographic_publication(303,$c,$p),'audit failure reported to transactional caller');
echo "GEO_PUBLIC_05_AUDIT=PASS\n";
