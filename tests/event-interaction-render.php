<?php
/** Actual PHP presenters with synthetic data and no external services. */
define('ABSPATH',__DIR__);define('DAY_IN_SECONDS',86400);define('HOUR_IN_SECONDS',3600);define('MINUTE_IN_SECONDS',60);
define('BADAROUND_IDENTITY_PRIVATE_QA_ENABLED',true);define('BADAROUND_IDENTITY_PORTAL_ENABLED',true);define('BADAROUND_IDENTITY_COMMUNITY_ENABLED',true);define('BADAROUND_IDENTITY_QA_IP_ALLOWLIST',['127.0.0.1']);define('BADAROUND_IDENTITY_PRIVACY_APPROVED_VERSION','privacy-20261024T162603Z');define('BADAROUND_CORE_FILE','/synthetic/badaround-core.php');$_SERVER['REMOTE_ADDR']='127.0.0.1';
function get_post($id){return $id===3?(object)['ID'=>3,'post_type'=>'page','post_status'=>'publish','post_name'=>'privacy-policy','post_modified_gmt'=>'2026-10-24 16:26:03','post_content'=>'Informativa revisionata']:(object)['ID'=>501,'post_type'=>'ba_evento','post_status'=>'publish'];}
function get_queried_object_id(){return 501;}function get_post_status(){return 'publish';}function get_post_meta(){return 'published';}
function plugins_url($p,$root){return 'https://example.test/'.$p;}function wp_enqueue_script(){}function add_shortcode(){}function absint($n){return abs((int)$n);}function sanitize_key($v){return $v;}function apply_filters($key,$value){return $value;}function rest_url($p){return 'https://example.test/wp-json/'.$p;}
function esc_url($v){return htmlspecialchars($v,ENT_QUOTES);}function esc_html($v){return htmlspecialchars($v,ENT_QUOTES);}function esc_attr($v){return htmlspecialchars($v,ENT_QUOTES);}
class BadAround_Publication_Service{const STATUS_PUBLISHED='published';}class BadAround_Contribution_Post_Type{const POST_TYPE='contribution';}class WP_Query{function __construct($args){}function have_posts(){return false;}}
require __DIR__.'/../wordpress/plugins/badaround-core/identity-dormant/wordpress-comment-form.php';
require __DIR__.'/../wordpress/plugins/badaround-core/includes/class-badaround-contribution-service.php';
echo '<main class="ba-event-detail">';BadAround_Contribution_Service::render_event_contribution_block(501);echo BadAround_Identity_WordPress_Comment_Form::render();echo '</main>';
