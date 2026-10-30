<?php
declare(strict_types=1);
$src=file_get_contents(dirname(__DIR__).'/wordpress/plugins/badaround-core/includes/class-badaround-radar-review-store.php');
foreach([
 'OFF by default'=>'BADAROUND_RADAR_EDITORIAL_WRITE_ENABLED',
 'Administrator permission'=>"current_user_can('manage_options')",
 'Nonce required'=>'wp_verify_nonce',
 'Published target only'=>"get_post_status($id)!=='publish'",
 'Optimistic revision'=>'hash_equals',
 'One-time immutable review'=>'add_post_meta($id,self::META_KEY,$record,true)',
 'Audit before commit'=>'BadAround_Audit_Log::record',
 'Transaction rollback'=>"query('ROLLBACK')",
 'No public endpoints'=>"!register_rest_route(",
] as $name=>$needle) {
 if($name==='No public endpoints' ? str_contains($src,'register_rest_route(') : !str_contains($src,$needle))
  throw new RuntimeException($name);
 echo "PASS $name\n";
}
echo "RADAR REVIEW STORE: PASS source gate\n";
