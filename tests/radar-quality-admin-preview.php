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
