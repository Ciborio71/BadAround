<?php
declare(strict_types=1);
$src=file_get_contents(dirname(__DIR__).'/wordpress/themes/badaround-child/page-radar-demo.php');
$files=['class-badaround-intelligence-trends.php','class-badaround-intelligence-peak-candidates.php',
'class-badaround-intelligence-data-quality.php','class-badaround-radar-view-model.php',
'class-badaround-radar-canonical-adapter.php','class-badaround-radar-offline-bridge.php',
'class-badaround-radar-public-counts.php','class-badaround-radar-public-renderer.php'];
$last=-1;foreach($files as $file){$at=strpos($src,"'".$file."'");if($at===false||$at<=$last)throw new RuntimeException("Missing/order: $file");$last=$at;echo "PASS $file\n";}
if(!str_contains($src,"BADAROUND_RADAR_PUBLIC_COUNTS_PREVIEW_ENABLED === true") ||
 !str_contains($src,"current_user_can('manage_options')") ||
 !str_contains($src,"if ($"."ba_radar_ready) {") ||
 !str_contains($src,"require_once $"."ba_radar_dir . $"."ba_radar_file"))
 throw new RuntimeException("Private opt-in gate damaged");
echo "RADAR-PUBLIC-04: PASS ordered private dependencies\n";
