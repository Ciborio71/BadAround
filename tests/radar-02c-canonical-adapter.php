<?php
declare(strict_types=1);
define('ABSPATH',__DIR__);
class BadAround_Discovery_Query {
 private $callback; public $calls=0;
 public function __construct(callable $fn){$this->callback=$fn;}
 public function discover($args){$this->calls++;return ($this->callback)($args,$this->calls);}
}
require_once dirname(__DIR__).'/wordpress/plugins/badaround-core/includes/class-badaround-radar-canonical-adapter.php';
function verify($x,$message){if(!$x)throw new RuntimeException($message);}
function fake($id){return array('id'=>$id,'occurred_date'=>'2026-10-29',
 'territory'=>array('slug'=>'roma'),'event_type'=>array('slug'=>'furto'),
 'public_geo'=>array('lat'=>41.2,'lng'=>12.3),'excerpt'=>'sensitive text','thumbnail'=>'https://invalid.example/image');}
function page($items,$total=null,$pages=1,$number=1){return array('items'=>$items,'total'=>$total===null?count($items):$total,'pages'=>$pages,'page'=>$number);}
function rejection(callable $fn){try{$fn();}catch(RuntimeException $e){return;}throw new RuntimeException('Expected failure');}
$q=new BadAround_Discovery_Query(fn($args)=>page(array(fake(1))));
$rows=BadAround_Radar_Canonical_Adapter::rows($q);
verify(count($rows)===1 && $q->calls===2,'Stable two-pass read');
verify(array_keys($rows[0])===array('id','occurred_date','territory','event_type'),'Strict whitelist');
verify(!isset($rows[0]['public_geo'],$rows[0]['excerpt'],$rows[0]['thumbnail']),'No sensitive projection fields');
$q=new BadAround_Discovery_Query(fn($args)=>page(array(),0,0));
verify(BadAround_Radar_Canonical_Adapter::rows($q)===array(),'True empty Discovery');
$q=new BadAround_Discovery_Query(fn($args)=>page(array(fake(1),fake(1))));
rejection(fn()=>BadAround_Radar_Canonical_Adapter::rows($q));
$q=new BadAround_Discovery_Query(fn($args)=>page(array(fake(1)),2));
rejection(fn()=>BadAround_Radar_Canonical_Adapter::rows($q));
$q=new BadAround_Discovery_Query(fn($args,$call)=>page(array(fake($call))));
rejection(fn()=>BadAround_Radar_Canonical_Adapter::rows($q));
$q=new BadAround_Discovery_Query(fn($args)=>page(array(),0,21));
rejection(fn()=>BadAround_Radar_Canonical_Adapter::rows($q));
echo "RADAR-02C: PASS 8 checks".PHP_EOL;
