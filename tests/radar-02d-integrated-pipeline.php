<?php
/** RADAR-02D isolated full-pipeline integration. No WordPress bootstrap or real data. */
declare(strict_types=1);
define('ABSPATH',__DIR__);
class BadAround_Discovery_Query {
 private $callback; public function __construct(callable $cb){$this->callback=$cb;}
 public function discover($args){return ($this->callback)($args);}
}
$root=dirname(__DIR__).'/wordpress/plugins/badaround-core/includes/';
foreach(array('class-badaround-radar-canonical-adapter.php','class-badaround-intelligence-trends.php','class-badaround-intelligence-peak-candidates.php','class-badaround-intelligence-data-quality.php','class-badaround-radar-view-model.php') as $f)require_once $root.$f;
function check($x,$m){if(!$x)throw new RuntimeException($m);}
function entry($id,$date,$territory='roma',$type='furto'){return array('id'=>$id,'occurred_date'=>$date,'territory'=>array('slug'=>$territory),'event_type'=>array('slug'=>$type),'public_geo'=>array('lat'=>41.9,'lng'=>12.5),'excerpt'=>'private-not-for-view');}
function pipeline($entries){
 $query=new BadAround_Discovery_Query(static function($args)use($entries){return array('items'=>$entries,'total'=>count($entries),'pages'=>count($entries)?1:0,'page'=>1);});
 $rows=BadAround_Radar_Canonical_Adapter::rows($query);
 $trends=BadAround_Intelligence_Trends::summarize($rows,new DateTimeImmutable('2026-10-29',new DateTimeZone('Europe/Rome')),7);
 $candidates=BadAround_Intelligence_Peak_Candidates::screen($trends);
 $quality=BadAround_Intelligence_Data_Quality::assess($trends,$candidates);
 return array($rows,$trends,$candidates,$quality,BadAround_Radar_View_Model::build($trends,$quality));
}
$entries=array();
for($i=1;$i<=10;$i++)$entries[]=entry($i,'2026-10-29');
for($i=11;$i<=15;$i++)$entries[]=entry($i,'2026-10-20');
[$rows,$trends,$candidates,$quality,$view]=pipeline($entries);
check(count($rows)===15,'all canonical rows');
check(!isset($rows[0]['public_geo'],$rows[0]['excerpt']),'strict projection');
check($trends['groups'][0]['current_reports']===10 && $trends['groups'][0]['previous_reports']===5,'temporal totals');
check($candidates['candidate_count']===1,'candidate counted');
check($quality['date_coverage_ratio']===1.0,'coverage');
check($view['cards'][0]['percentage_change']===100.0,'view comparison');
check($view['internal_preview_only']===true && $view['public_alerts']===array(),'no public claims');
[$rows,$trends,$candidates,$quality,$view]=pipeline(array());
check($view['cards']===array() && $view['public_alerts']===array(),'empty pipeline');
$dated=array(entry(1,'2026-10-29'),entry(2,'2026-10-20'),entry(3,''));
[$rows,$trends,$candidates,$quality,$view]=pipeline($dated);
check($quality['groups'][0]['quality_review_required']===true,'insufficient coverage suppressed');
check($view['cards'][0]['percentage_change']===null,'suppressed comparison');
echo 'RADAR-02D: PASS 10 isolated pipeline assertions'.PHP_EOL;
