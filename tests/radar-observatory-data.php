<?php
// Aggregation, calendar, public geometry containment and suppression regressions.
define('ABSPATH',__DIR__);
function add_action(...$a){}function wp_timezone(){return new DateTimeZone('Europe/Rome');}function wp_strip_all_tags($s){return strip_tags($s);}function remove_accents($s){return strtr($s,array('à'=>'a','è'=>'e','é'=>'e','ì'=>'i','ò'=>'o','ù'=>'u'));}function get_stylesheet_directory(){return dirname(__DIR__).'/wordpress/themes/badaround-child';}
require get_stylesheet_directory().'/inc/radar-observatory.php';
function check($ok,$label){if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
check(BadAround_Radar_Observatory::date('31/02/2026')===null,'invalid calendar date rejected');
check(BadAround_Radar_Observatory::date('18/11/2026')==='2026-11-18','Italian date accepted');
$path=array(array('id'=>1,'name'=>'Lazio','slug'=>'lazio'),array('id'=>2,'name'=>'Roma','slug'=>'roma-provincia'),array('id'=>3,'name'=>'Roma','slug'=>'roma'));
$rows=array();foreach(array('2026-11-01','2026-11-03',null,'2026-12-01') as $date)$rows[]=array('path'=>$path,'category'=>'veicoli','type'=>'Veicolo rubato','date'=>$date,'published'=>'2026-11-18','geo'=>array('lat'=>41.9,'lng'=>12.5,'radius'=>100));
$today=new DateTimeImmutable('2026-11-18',wp_timezone());
$result=BadAround_Radar_Observatory::aggregate($rows,array(),array(),'30','date',$today);
check($result['metrics']['total']===4&&$result['metrics']['dated']===2,'totals distinguish invalid missing and future episode dates');
check(count($result['series']['bins'])===30&&array_sum(array_column($result['series']['bins'],'count'))===2,'zero filling and future exclusion');
check(count($result['geography']['cells'])===1&&$result['geography']['type']==='heatmap','national heatmap uses suppressed aggregated cells');
check(!str_contains(json_encode($result),'public_geo')&&!str_contains(json_encode($result),'permalink'),'response contains no event projections');
$local=BadAround_Radar_Observatory::aggregate(array_slice($rows,0,2),$path,array(),'all','date',$today);
check(count($local['geography']['cells'])===0,'sparse cells suppressed');
$local=BadAround_Radar_Observatory::aggregate($rows,$path,array(),'all','date',$today);
check(count($local['geography']['cells'])===1&&$local['geography']['cells'][0]['count']===4,'same cell aggregates equal weights');
foreach(array(0,100,500,2000) as $radius){
 $cell=BadAround_Radar_Observatory::cell(array('lat'=>41.9,'lng'=>12.5,'radius'=>$radius));check($cell!==null,'cell generated radius '.$radius);
 $p=$cell['polygon'];$lat=41.9;$lng=12.5;$x=6378137*deg2rad($lng);$y=6378137*log(tan(M_PI/4+deg2rad($lat)/2));$uncertainty=$radius/cos(deg2rad($lat));
 for($i=0;$i<6;$i++){$a=array(6378137*deg2rad($p[$i][0]),6378137*log(tan(M_PI/4+deg2rad($p[$i][1])/2)));$b=array(6378137*deg2rad($p[$i+1][0]),6378137*log(tan(M_PI/4+deg2rad($p[$i+1][1])/2)));$dist=(($b[0]-$a[0])*($y-$a[1])-($b[1]-$a[1])*($x-$a[0]))/hypot($b[0]-$a[0],$b[1]-$a[1]);check($dist>=$uncertainty-2,'public uncertainty fully contained '.$radius.' edge '.$i);}
}
check(count((require get_stylesheet_directory().'/inc/radar-geography.php')['regions']['features'])===20,'20 sourced regions');
echo "RADAR_AGGREGATE_PASS\n";

