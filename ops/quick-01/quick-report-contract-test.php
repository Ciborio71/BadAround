<?php
declare(strict_types=1);
require __DIR__.'/quick-report-contract.php';
function checkQuick(bool $condition,string $reason):void {if(!$condition)throw new RuntimeException($reason);}
$r=BadAround_Quick_Report_Contract::transition('local','save_draft',['category'=>'Veicoli','user_initiated'=>true]);
checkQuick($r['allowed']&&$r['next']==='private_draft'&&!$r['publication']&&!$r['radar_inclusion'],'save private');
$r=BadAround_Quick_Report_Contract::transition('local','save_draft',['category'=>'Veicoli']);
checkQuick(!$r['allowed'],'no silent save');
$r=BadAround_Quick_Report_Contract::transition('private_draft','mark_ready',['category'=>'Veicoli']);
checkQuick(!$r['allowed'],'incomplete draft cannot proceed');
$r=BadAround_Quick_Report_Contract::transition('ready_for_intake','submit',['canonical_intake_validated'=>false]);
checkQuick(!$r['allowed'],'no intake bypass');
$r=BadAround_Quick_Report_Contract::transition('ready_for_intake','submit',['canonical_intake_validated'=>true]);
checkQuick($r['allowed']&&$r['next']==='submitted_for_moderation'&&!$r['publication'],'moderation required');
try{BadAround_Quick_Report_Contract::transition('published','save_draft',[]);throw new RuntimeException('invalid state admitted');}
catch(InvalidArgumentException $expected){}
echo "QUICK-01 private-draft state contract PASS\n";
