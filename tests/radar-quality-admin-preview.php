<?php
declare(strict_types=1);
$s=file_get_contents(dirname(__DIR__).'/wordpress/plugins/badaround-core/includes/class-badaround-radar-review-admin.php');
foreach (['BADAROUND_RADAR_QUALITY_REVIEW_PREVIEW !== true',"current_user_can('manage_options')",'BadAround_Radar_Quality_Registry::classify','BadAround_Radar_Canonical_Adapter::rows','Nessuna classificazione viene salvata'] as $test) {
 if(!str_contains($s,$test))throw new RuntimeException('Missing review guard');
}
foreach (['update_post_meta(','add_post_meta(','register_rest_route(','wp_insert_post('] as $unsafe) {
 if(str_contains($s,$unsafe))throw new RuntimeException('Unexpected write');
}
echo "RADAR QUALITY ADMIN PREVIEW: PASS\n";

$bootstrap=file_get_contents(dirname(__DIR__).'/wordpress/plugins/badaround-core/badaround-core.php');
foreach (["BADAROUND_RADAR_QUALITY_REVIEW_PREVIEW === true",
 "class-badaround-radar-quality-registry.php",
 "class-badaround-radar-review-admin.php",
 "BadAround_Radar_Review_Admin::register()"] as $needle)
 if(!str_contains($bootstrap,$needle))throw new RuntimeException('Bootstrap missing '.$needle);
if(str_contains($bootstrap,'class-badaround-radar-review-store.php'))
 throw new RuntimeException('Write service included in live bootstrap');
echo "RADAR QUALITY BOOTSTRAP: PASS\n";
