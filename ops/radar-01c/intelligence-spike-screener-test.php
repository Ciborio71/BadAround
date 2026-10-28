<?php
declare(strict_types=1);
require __DIR__.'/intelligence-spike-screener.php';
function check(bool $x,string $s): void {if(!$x)throw new RuntimeException($s);}
$r=BadAround_Intelligence_Spike_Screener::inspect([4,3,4,3,4,3,4,3,17]);
check($r['status']==='candidate_for_review'&&!$r['public_alert'],'spike candidate not public alert');
$r=BadAround_Intelligence_Spike_Screener::inspect([4,3,4,3,4,3,4,3,4]);
check($r['status']==='no_anomaly','ordinary');
$r=BadAround_Intelligence_Spike_Screener::inspect([0,0,1,0,0,0,0,0,8]);
check($r['status']==='insufficient_baseline','low denominator');
$r=BadAround_Intelligence_Spike_Screener::inspect([4,3,4]);
check($r['status']==='insufficient_history','missing history');
$r=BadAround_Intelligence_Spike_Screener::inspect([4,3,4,3,4,3,4,-1,17]);
check($r['status']==='invalid_input','negative data');
echo "INTELLIGENCE-02 spike screening PASS\n";
