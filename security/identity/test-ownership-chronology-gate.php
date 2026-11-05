<?php
declare(strict_types=1);
require_once __DIR__ . '/ownership-chronology-gate.php';
$base = [
    'submission_id'=>'123e4567-e89b-42d3-a456-426614174000',
    'operation_id'=>str_repeat('a',32),
    'identity_id'=>'identity_123', 'created_at'=>1000,
    'state'=>'reserved', 'report_id'=>null, 'event_id'=>null,
];
$canonical = ['submission_id'=>$base['submission_id'],'report_id'=>101,'event_id'=>202,'duplicate'=>false];
$receipt = static fn(int $r,int $e):bool => $r===101 && $e===202;
$order = static fn(int $r,int $e,int $at):bool => $at<=1100;
$checks=[];
$checks['valid reservation']=BadAround_Identity_Ownership_Chronology_Gate::eligible($base,$canonical,$receipt,$order);
$checks['old report rejected']=!BadAround_Identity_Ownership_Chronology_Gate::eligible($base,$canonical,$receipt,static fn():bool=>false);
$c=$canonical;$c['duplicate']=true;
$checks['duplicate rejected']=!BadAround_Identity_Ownership_Chronology_Gate::eligible($base,$c,$receipt,$order);
$c=$canonical;$c['event_id']=203;
$checks['event mismatch rejected']=!BadAround_Identity_Ownership_Chronology_Gate::eligible($base,$c,$receipt,$order);
$r=$base;$r['state']='linked';$r['report_id']=900;$r['event_id']=202;
$checks['linked mismatch rejected']=!BadAround_Identity_Ownership_Chronology_Gate::eligible($r,$canonical,$receipt,$order);
$r=$base;$r['created_at']='1000';
$checks['timestamp type rejected']=!BadAround_Identity_Ownership_Chronology_Gate::eligible($r,$canonical,$receipt,$order);
$checks['proof failure rejected']=!BadAround_Identity_Ownership_Chronology_Gate::eligible($base,$canonical,$receipt,static function():bool {throw new RuntimeException('offline');});
$checks['missing row rejected']=!BadAround_Identity_Ownership_Chronology_Gate::eligible(null,$canonical,$receipt,$order);
foreach($checks as $name=>$ok) echo ($ok?'PASS ':'FAIL ').$name."\n";
if(in_array(false,$checks,true)) exit(1);
