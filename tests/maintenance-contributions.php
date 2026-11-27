<?php
function is_user_logged_in(){return false;}
function has_filter($a,$b){return 6;}
function get_option($a,$b=null){return ['qa@example.invalid'];}
class BadAround_Identity_QA_Report_REST {static function enabled(){return $GLOBALS['qa']??false;}}
class WP_Error {function __construct(...$args){}}
class Request {function __construct(public $route,public $method,public $email='qa@example.invalid'){} function get_route(){return $this->route;}function get_method(){return $this->method;}function get_param($key){return $this->email;}}
$s=file_get_contents(__DIR__.'/../wordpress/themes/badaround-child/functions.php');
$a=strpos($s,'function badaround_child_maintenance_enabled()');$b=strpos($s,'function badaround_child_is_maintenance_exempt()');eval(substr($s,$a,$b-$a));
$a=strpos($s,'function badaround_child_maintenance_rest_gate(');$b=strpos($s,"add_filter( 'rest_pre_dispatch'",$a);eval(substr($s,$a,$b-$a));
$verify='/badaround/v1/contributions/verify/'.str_repeat('a',36).'/'.str_repeat('b',64);
$cases=[
[false,'POST','/badaround/v1/contributions','qa@example.invalid',false],
[true,'POST','/badaround/v1/contributions','qa@example.invalid',true],
[true,'POST','/badaround/v1/contributions','other@example.invalid',false],
[true,'GET','/badaround/v1/contributions','qa@example.invalid',false],
[true,'POST','/badaround/v1/contributions/extra','qa@example.invalid',false],
[true,'GET',$verify,'qa@example.invalid',true],
[false,'GET',$verify,'qa@example.invalid',false],
[true,'POST',$verify,'qa@example.invalid',false],
[true,'GET',$verify.'/extra','qa@example.invalid',false],
[true,'POST','/wp/v2/posts','qa@example.invalid',false],
];
foreach($cases as $i=>[$qa,$method,$route,$email,$allow]){$GLOBALS['qa']=$qa;$r=badaround_child_maintenance_rest_gate(null,null,new Request($route,$method,$email));if(($r===null)!==$allow)throw new RuntimeException('case '.$i);echo "PASS contribution maintenance case $i\n";}
echo "MAINTENANCE_CONTRIBUTIONS_PASS\n";
