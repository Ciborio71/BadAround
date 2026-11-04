<?php
/** BA-IDENTITY-02C: no frontend exposure before reviewed privacy and QA gates. */
declare(strict_types=1);
require __DIR__.'/wordpress-identity-portal.php';
function portal_assert(bool $ok,string $name):void{
    if (!$ok)throw new RuntimeException('FAIL '.$name);
    echo 'PASS '.$name.PHP_EOL;
}
portal_assert(!BadAround_Identity_WordPress_Portal::enabled(),
    'public portal disabled by default');
portal_assert(BadAround_Identity_WordPress_Portal::render()==='',
    'shortcode is inert without authorization');
define('BADAROUND_IDENTITY_PRIVATE_QA_ENABLED',true);
define('BADAROUND_IDENTITY_PORTAL_ENABLED',true);
define('BADAROUND_IDENTITY_QA_IP_ALLOWLIST',['127.0.0.1']);
$_SERVER['REMOTE_ADDR']='127.0.0.1';
portal_assert(!BadAround_Identity_WordPress_Portal::enabled(),
    'privacy approval is mandatory even for authorized QA');
define('BADAROUND_IDENTITY_PRIVACY_APPROVED_VERSION','privacy-20261024T162603Z');
$GLOBALS['portal_page']=(object)[
    'ID'=>3,'post_type'=>'page','post_status'=>'publish',
    'post_name'=>'privacy-policy',
    'post_modified_gmt'=>'2026-10-24 16:26:03',
    'post_content'=>'Informativa revisionata'
];
function get_post($id){return $id===3 ? $GLOBALS['portal_page']:null;}
function home_url($path){return 'https://badaround.it'.$path;}
function esc_url($url){return htmlspecialchars($url,ENT_QUOTES,'UTF-8');}
function plugins_url($path,$root){return 'https://badaround.it/wp-content/plugins/badaround-core/'.$path;}
define('BADAROUND_CORE_FILE','/code/badaround-core.php');
$GLOBALS['portal_enqueue']=[];
function wp_enqueue_script($name,$url,$deps,$version,$footer){
    $GLOBALS['portal_enqueue'][]=[$name,$url,$footer];
}
function add_shortcode($shortcode,$callback){$GLOBALS['portal_shortcode']=$shortcode;}
portal_assert(BadAround_Identity_WordPress_Portal::enabled(),
    'QA peer and explicit approved version allow private portal');
portal_assert(BadAround_Identity_WordPress_Portal::register_hooks()
    && $GLOBALS['portal_shortcode']==='badaround_identity_portal',
    'private shortcode registered only for QA');
$html=BadAround_Identity_WordPress_Portal::render();
portal_assert(str_contains($html,'name="first_name"')
    && str_contains($html,'name="last_name"')
    && str_contains($html,'name="email"')
    && str_contains($html,'name="privacy_acknowledged"')
    && str_contains($html,'/privacy-policy/'),
    'registration requires name surname email privacy notice');
portal_assert(str_contains($html,'data-ba-form="login"')
    && str_contains($html,'data-ba-form="otp"')
    && str_contains($html,'data-ba-logout')
    && str_contains($html,'data-ba-activity')
    && str_contains($html,'data-ba-activity-list')
    && count($GLOBALS['portal_enqueue'])===1,
    'login/OTP/logout private JS enqueued');
$GLOBALS['portal_page']->post_modified_gmt='2026-11-04 01:00:00';
portal_assert(!BadAround_Identity_WordPress_Portal::enabled(),
    'privacy document change immediately disables portal');
$_SERVER['REMOTE_ADDR']='203.0.113.100';
portal_assert(BadAround_Identity_WordPress_Portal::render()==='',
    'public visitor cannot render private portal');
echo "ALL_WORDPRESS_IDENTITY_PORTAL_TESTS_PASS\n";
