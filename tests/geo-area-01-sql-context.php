<?php
require __DIR__.'/geo-prec-01-sql-context.php';
$wpdb->pdo->exec('CREATE TABLE gprec_sql_ba_reports (id INTEGER PRIMARY KEY,event_id INTEGER,source_type VARCHAR(20),content_original TEXT,deleted_at TEXT)');
try{
 foreach([50,100,250,500,1000] as $radius){$id=3000+$radius;gseed($id,'approximate_area');$e=['public_geo_governance'=>'user-public-location/v1','canonical'=>['location'=>['public_location_request'=>['mode'=>'approximate_area','radius_m'=>$radius],'public_location_request_version'=>'public-location-request-v2']]];
 $wpdb->pdo->prepare('INSERT INTO gprec_sql_ba_reports VALUES (?,?,?, ?,NULL)')->execute([$id+1000,$id,'native',json_encode($e)]);$c=$svc->request_context($id);
 gassert(!is_wp_error($c)&&$c['user_authoritative']&&$c['request']==='approximate_area'&&$c['location']['public_location_request']['radius_m']===$radius&&$c['allowed']===[],'actual SQL object request decoded; no moderator control '.$radius);}
}finally{$wpdb->pdo->exec('DROP TABLE gprec_sql_ba_reports');}
echo "GEO_AREA_01_SQL_CONTEXT=PASS\n";
