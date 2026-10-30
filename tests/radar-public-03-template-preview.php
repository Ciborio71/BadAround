<?php
declare(strict_types=1);
$path=dirname(__DIR__).'/wordpress/themes/badaround-child/page-radar-demo.php';
$src=file_get_contents($path);
$checks=[
 'Explicit OFF gate'=>str_contains($src,"BADAROUND_RADAR_PUBLIC_COUNTS_PREVIEW_ENABLED === true"),
 'Administrator only'=>str_contains($src,"current_user_can('manage_options')"),
 'Existing Radar reports preserved'=>str_contains($src,'id="rad-reports"'),
 'Existing map preserved'=>str_contains($src,'id="rad-google-map"'),
 'Existing toolbar preserved'=>str_contains($src,'id="rad-place"'),
 'Canonical Discovery only'=>str_contains($src,'BadAround_Radar_Canonical_Adapter::rows'),
 'Quality gate used'=>str_contains($src,"$"."ba_radar_snapshot['quality']"),
 'Safe renderer'=>str_contains($src,'BadAround_Radar_Public_Renderer::render'),
 'Graceful fallback'=>str_contains($src,'Anteprima Radar non disponibile'),
];
foreach($checks as $name=>$ok){if(!$ok)throw new RuntimeException($name);echo "PASS $name\n";}
echo "RADAR-PUBLIC-03: PASS ".count($checks)." source invariants\n";
