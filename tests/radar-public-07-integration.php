<?php
declare(strict_types=1);
$root=dirname(__DIR__).'/wordpress/';
$tpl=file_get_contents($root.'themes/badaround-child/page-radar-demo.php');
$renderer=file_get_contents($root.'plugins/badaround-core/includes/class-badaround-radar-window-renderer.php');
$checks=[
 'Admin gate'=>str_contains($tpl,"BADAROUND_RADAR_PUBLIC_COUNTS_PREVIEW_ENABLED === true") && str_contains($tpl,"current_user_can('manage_options')"),
 'Module wiring'=>str_contains($tpl,"class-badaround-radar-window-counts.php") && str_contains($tpl,"class-badaround-radar-window-renderer.php"),
 '30 day preview'=>str_contains($tpl,"summarize($"."ba_radar_rows, $"."ba_radar_today, 30)"),
 'Canonical only'=>str_contains($tpl,"BadAround_Radar_Canonical_Adapter::rows"),
 'No public registration'=>!str_contains($renderer,'add_action(') && !str_contains($renderer,'register_rest_route('),
 'Existing map'=>str_contains($tpl,'id="rad-google-map"'),
 'Existing search'=>str_contains($tpl,'id="rad-place"'),
 'Existing results'=>str_contains($tpl,'id="rad-reports"'),
 'No false risk'=>str_contains($renderer,'non episodi distinti o indici di rischio'),
];
foreach($checks as $name=>$ok){if(!$ok)throw new RuntimeException($name);echo "PASS $name\n";}
echo "RADAR-PUBLIC-07: PASS ".count($checks)." checks\n";
