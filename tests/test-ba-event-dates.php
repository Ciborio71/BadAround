<?php
require __DIR__.'/geo-public-05-publication.php';
function wp_timezone(){return new DateTimeZone('Europe/Rome');}
function wp_date($format,$timestamp,$zone=null){return (new DateTimeImmutable('@'.$timestamp))->setTimezone($zone?:wp_timezone())->format($format);}
class ReceiptDB extends Geo05DB {
 function get_row($sql){
  if(str_contains($sql,'FROM wp_posts'))return parent::get_row($sql);
  if(!empty($GLOBALS['receipt_missing']))return null;
  return (object)$GLOBALS['receipt_row'];
 }
}
$wpdb=new ReceiptDB();g5seed(9400);$GLOBALS['posts'][9400]->post_status='publish';$GLOBALS['meta'][9400]['_ba_moderation_status']='published';
$GLOBALS['receipt_row']=['id'=>10400,'created_at'=>'2026-11-06 23:46:58','source_type'=>'native'];
check(BadAround_Event_Dates::parse_receipt('2026-02-30 12:00:00')===null,'invalid receipt date rejected');
$r=BadAround_Event_Dates::capture(9400);check(!is_wp_error($r)&&$r['kind']==='submitted'&&!isset($r['report_id']),'native timestamp projected without private report identifier');
$label=BadAround_Event_Dates::receipt_label($r);check($label['text']==='07/11/2026 alle 00:46'&&$label['datetime']==='2026-11-06T23:46:58Z','UTC receipt uses Rome date across midnight');
$summer=BadAround_Event_Dates::receipt_label(['received_utc'=>'2026-07-01 12:00:00','kind'=>'imported']);check($summer['text']==='01/07/2026 alle 14:00'&&$summer['prefix']==='Segnalazione acquisita il','summer timezone and imported wording');
check(BadAround_Event_Dates::capture(9400)===$r,'repeat capture stable');
$stored=$GLOBALS['meta'][9400][BadAround_Event_Dates::META];$GLOBALS['meta'][9400][BadAround_Event_Dates::META]['data']['received_utc']='2025-01-01 00:00:00';check(BadAround_Event_Dates::view(9400)===[],'tampered receipt hidden');$GLOBALS['meta'][9400][BadAround_Event_Dates::META]=$stored;
$GLOBALS['meta'][9400]['_ba_primary_report_id']=999;check(BadAround_Event_Dates::view(9400)===[],'changed report binding hides stale receipt');$GLOBALS['meta'][9400]['_ba_primary_report_id']=10400;
$GLOBALS['denied']=['ba_view_private_reports'];check(is_wp_error(BadAround_Event_Dates::capture(9400)),'receipt capture permission enforced');$GLOBALS['denied']=[];
$GLOBALS['receipt_row']['created_at']='2026-11-07 12:00:00';$GLOBALS['commit_fail']=true;check(is_wp_error(BadAround_Event_Dates::capture(9400))&&$GLOBALS['meta'][9400][BadAround_Event_Dates::META]===$stored,'failed transaction restores receipt');$GLOBALS['commit_fail']=false;
$GLOBALS['receipt_missing']=true;check(is_wp_error(BadAround_Event_Dates::capture(9400)),'missing report fails closed');$GLOBALS['receipt_missing']=false;
check(BadAround_Event_Dates::occurrence_label(9400)==='Non specificata dall’autore','missing occurrence never substituted with receipt or publication');
$t=['time'=>['date'=>'2026-10-20','knowledge'=>'exact','exact_time'=>'18:30']];check(BadAround_Event_Dates::occurrence_label(9400,$t)==='20/10/2026 · ore 18:30','exact user occurrence distinct from receipt');
$t['time']=['date'=>'2026-10-20','knowledge'=>'range','range_start'=>'18:00','range_end'=>'20:00'];check(BadAround_Event_Dates::occurrence_label(9400,$t)==='20/10/2026 · tra le 18:00 e le 20:00','uncertain time range preserved');
$t['time']=['mode'=>'approximate','approximate_period'=>'last_7_days'];check(BadAround_Event_Dates::occurrence_label(9400,$t)==='Nei 7 giorni precedenti la segnalazione (riferimento: 07/11/2026)','relative occurrence anchored to original local receipt date');
