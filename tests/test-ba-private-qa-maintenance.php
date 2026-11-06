<?php
declare(strict_types=1);
require_once __DIR__.'/../wordpress/plugins/badaround-core/identity-dormant/wordpress-private-bootstrap.php';
class WP_Error {
    public function __construct(private array $codes,private int $status=503){}
    public function get_error_codes(){return $this->codes;}
    public function get_error_data(){return ['status'=>$this->status];}
}
function is_wp_error($value){return $value instanceof WP_Error;}
function home_url($path='/'){return 'https://badaround.it'.$path;}
$body='<p>Informativa QA adottata</p>';$hash=hash('sha256',$body);
$notice=['page_id'=>388,'page_slug'=>'identity-qa-informativa','sha256'=>$hash,
    'version'=>'qa-privacy-'.substr($hash,0,16),'adopted_at_gmt'=>'2026-11-05T22:33:18',
    'scope'=>'private_identity_qa','state'=>'owner_adopted','legal_review_verified'=>false,
    'approval_source'=>'explicit_user_approval_in_conversation'];
function get_option($name,$default=null){return $GLOBALS['notice'];}
function get_post($id){return (object)['ID'=>388,'post_type'=>'page','post_status'=>'private',
    'post_name'=>'identity-qa-informativa','post_content'=>$GLOBALS['body']];}
define('BADAROUND_IDENTITY_RUNTIME_INTEGRATION_ENABLED',true);
define('BADAROUND_IDENTITY_PRIVATE_QA_ENABLED',true);
define('BADAROUND_IDENTITY_QA_NOTICE_ENABLED',true);
define('BADAROUND_IDENTITY_QA_IP_ALLOWLIST',['203.0.113.42']);
$_SERVER['REMOTE_ADDR']='203.0.113.42';
function maintenanceCheck(bool $ok,string $label):void {
    if(!$ok)throw new RuntimeException('FAIL '.$label);
    echo 'PASS '.$label.PHP_EOL;
}
$request=new class {
    public string $route='/badaround/v1/identity/session';public string $method='POST';
    public string $marker='badaround-report-media/v1';
    public function get_header($key){return $key==='x-badaround-media'?$this->marker:null;}
    public function get_route(){return $this->route;}
    public function get_method(){return $this->method;}
};
$filter=[BadAround_Identity_WordPress_Private_Bootstrap::class,'private_qa_maintenance_request'];
$error=new WP_Error(['badaround_maintenance']);
maintenanceCheck($filter($error,null,$request)===null,'explicit peer and adopted QA notice permit maintenance exception for private session POST');
$request->route='/wp/v2/users';
maintenanceCheck($filter($error,null,$request)===$error,'WordPress and admin routes remain blocked by maintenance');
$request->route='/badaround/v1/identity/session/extra';
maintenanceCheck($filter($error,null,$request)===$error,'route prefixes cannot broaden private exception');
$request->route='/badaround/v1/identity/session';$request->method='GET';
maintenanceCheck($filter($error,null,$request)===$error,'other methods unchanged');
$request->method='POST';$_SERVER['REMOTE_ADDR']='203.0.113.43';
maintenanceCheck($filter($error,null,$request)===$error,'other peers remain blocked');
$_SERVER['HTTP_X_FORWARDED_FOR']='203.0.113.42';
maintenanceCheck($filter($error,null,$request)===$error,'spoofed forwarding header cannot open QA');
$_SERVER['REMOTE_ADDR']='203.0.113.42';$body.=' changed';
maintenanceCheck($filter($error,null,$request)===$error,'changed notice keeps maintenance block');
$body='<p>Informativa QA adottata</p>';
foreach([new WP_Error(['rest_forbidden'],403),new WP_Error(['badaround_maintenance','rest_forbidden']),
    new WP_Error(['badaround_maintenance'],403)] as $protected)
    maintenanceCheck($filter($protected,null,$request)===$protected,'other security errors are never cleared');
maintenanceCheck($filter(null,null,$request)===null,'normal null dispatch remains untouched');
define('BADAROUND_IDENTITY_QA_REPORTS_ENABLED',true);
$base='/badaround/v1/report-media-sessions';$id='12345678-1234-4123-8123-123456789abc';
foreach([[$base,'POST'],[$base.'/'.$id,'GET'],[$base.'/'.$id.'/items','POST'],
    [$base.'/'.$id.'/items/'.$id,'GET'],[$base.'/'.$id.'/items/'.$id,'DELETE']] as [$route,$method]) {
    $request->route=$route;$request->method=$method;
    maintenanceCheck($filter($error,null,$request)===null,'exact private media path/method clears only maintenance');
    $security=new WP_Error(['media_capability_invalid'],401);
    maintenanceCheck($filter($security,null,$request)===$security,'media authorization errors remain intact');
}
foreach([[$base.'/extra','POST'],[$base.'/'.$id,'DELETE'],[$base.'/'.$id.'/items/'.$id.'/extra','GET']] as [$route,$method]) {
    $request->route=$route;$request->method=$method;
    maintenanceCheck($filter($error,null,$request)===$error,'media route/method mismatch remains blocked');
}
$request->route=$base;$request->method='POST';$request->marker='';
maintenanceCheck($filter($error,null,$request)===$error,'missing media request marker remains blocked');
$request->marker='badaround-report-media/v1';$_SERVER['REMOTE_ADDR']='203.0.113.43';
maintenanceCheck($filter($error,null,$request)===$error,'media QA cannot open other peers');
echo "ALL_PRIVATE_QA_MAINTENANCE_TESTS_PASS\n";
