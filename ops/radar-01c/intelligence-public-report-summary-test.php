<?php
declare(strict_types=1);
require __DIR__.'/intelligence-public-report-summary.php';
function verify(bool $x,string $m):void{if(!$x)throw new RuntimeException($m);}
$one=['id'=>1,'event_type'=>['name'=>'Veicolo rubato'],'territory'=>['name'=>'Roma'],'occurred_date'=>'2026-10-22'];
$two=['id'=>2,'event_type'=>['name'=>'Veicolo rubato'],'territory'=>['name'=>'Pomezia'],'occurred_date'=>''];
$r=BadAround_Intelligence_Public_Report_Summary::build(['items'=>[$one,$two],'total'=>2,'page'=>1,'pages'=>1],new DateTimeImmutable('2026-10-28'));
verify($r['snapshot_complete']&&$r['observed_reports']===2&&$r['with_incident_date']===1,'complete descriptive');
verify($r['by_event_type']['Veicolo rubato']===2,'category');
verify(!$r['certified_incident_counts']&&!$r['public_anomaly_alerts'],'not overclaimed');
$r=BadAround_Intelligence_Public_Report_Summary::build(['items'=>[$one],'total'=>22,'page'=>1,'pages'=>2],new DateTimeImmutable('2026-10-28'));
verify(!$r['snapshot_complete']&&$r['observed_reports']===1,'incomplete snapshot');
echo "INTELLIGENCE-01E public-report descriptive summary PASS\n";
