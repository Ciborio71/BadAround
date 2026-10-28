<?php
/** INTELLIGENCE-01K: assert bootstrap remains passive and read model loaded. */
declare(strict_types=1);
$core=file_get_contents(__DIR__.'/../../wordpress/plugins/badaround-core/badaround-core.php');
$runtime=file_get_contents(__DIR__.'/../../wordpress/plugins/badaround-core/includes/class-badaround-core.php');
$model=file_get_contents(__DIR__.'/../../wordpress/plugins/badaround-core/includes/class-badaround-intelligence-read-model.php');
if(!is_string($core)||!is_string($runtime)||!is_string($model))throw new RuntimeException('Missing source');
if(substr_count($core,"require_once BADAROUND_CORE_PATH . 'includes/class-badaround-intelligence-read-model.php';")!==1)throw new RuntimeException('Not uniquely loaded');
foreach(['register_rest_route','add_action','add_filter','wp_insert_post','update_post_meta','wpdb->'] as $token){
 if(stripos($model,$token)!==false)throw new RuntimeException('Passive model violation: '.$token);
}
if(strpos($runtime,'BadAround_Intelligence_Read_Model')!==false)throw new RuntimeException('Unexpected active Core wiring');
if(strpos($model,'BadAround_Discovery_Query')===false||strpos($model,'published_reports_not_unique_incidents')===false)throw new RuntimeException('Wrong read model contract');
echo "INTELLIGENCE-01K passive Core bootstrap PASS\n";
