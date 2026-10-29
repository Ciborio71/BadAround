<?php
declare(strict_types=1);
define('ABSPATH','/');
$hook=null;$route=null;
function add_action($name,$cb){global $hook;if($name!=='rest_api_init')throw new RuntimeException('Wrong hook');$hook=$cb;}
function register_rest_route($ns,$path,$args){global $route;$route=[$ns,$path,$args];}
function is_user_logged_in(){return false;}
function current_user_can($cap){throw new RuntimeException('Must not check capabilities for anonymous request');}
require __DIR__.'/intelligence-runtime-probe.php';
BadAround_Intelligence_Runtime_Probe::register();
$hook();
if($route[0]!=='badaround-internal/v1'||$route[1]!=='/intelligence-verify'||$route[2]['methods']!=='GET'
 ||($route[2]['permission_callback'])()!==false)throw new RuntimeException('Anonymous access not denied');
$src=file_get_contents(__DIR__.'/intelligence-runtime-probe.php');
foreach(['post_title','public_geo','get_post_meta','update_option','wp_insert_post'] as $forbidden)
 if(strpos($src,$forbidden)!==false)throw new RuntimeException('Unexpected data exposure or write API');
echo "INTELLIGENCE-01P admin-only ephemeral probe gate PASS\n";
