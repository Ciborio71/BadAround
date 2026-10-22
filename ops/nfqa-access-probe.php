<?php
/** Temporary staging-only read-only access diagnostic. No application hooks or mutations. */
if (!defined('ABSPATH') || ($_SERVER['HTTP_HOST'] ?? '') !== 'staging.badaround.it') return;
$nfqa_path = '/wp-json/nfqa-access/v1/__ROUTE__';
if (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) === $nfqa_path && hash_equals('__TOKEN__', (string)($_SERVER['HTTP_X_NFQA_TOKEN'] ?? ''))) {
 header('X-NFQA-Bootstrap: mu-plugin-loaded');
 header('Cache-Control: private, no-store, max-age=0');
}
add_action('rest_api_init', static function () {
 register_rest_route('nfqa-access/v1', '/__ROUTE__', array(
  'methods' => array('GET','HEAD'),
  'permission_callback' => static function ($r) {
   if (!hash_equals('__TOKEN__', (string)$r->get_header('x-nfqa-token'))) return new WP_Error('nfqa_unauthorized','QA authorization required.',array('status'=>403));
   return true;
  },
  'callback' => static function () {
   nocache_headers();
   $r = new WP_REST_Response(array('staging'=>true,'read_only'=>true,'core_loaded'=>class_exists('BadAround_Core',false),'conditional_class'=>class_exists('BadAround_Report_Conditional_Contract',false),'native_version'=>badaround_native_report_config()['schemaVersion'],'contract_sha'=>BadAround_Report_Conditional_Contract::SHA256,'definition_loaded'=>count(BadAround_Report_Conditional_Contract::definition()['matrix'])===33),200);
   $r->header('Cache-Control','private, no-store, max-age=0');$r->header('X-NFQA-Bootstrap','wordpress-rest-callback');return $r;
  }
 ));
});
