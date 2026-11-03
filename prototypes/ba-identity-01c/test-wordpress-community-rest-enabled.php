<?php
/** CI-only enabled REST behavior; no WordPress bootstrap or public URL. */
declare(strict_types=1);
define('BADAROUND_IDENTITY_COMMUNITY_ENABLED',true);
require __DIR__.'/wordpress-community-rest-adapter.php';
$GLOBALS['ba_test_routes']=[];
function add_action($hook,$callback) { $GLOBALS['ba_test_routes']['hook']=$hook; }
function register_rest_route($namespace,$route,$args) {
    $GLOBALS['ba_test_routes'][$route]=[$namespace,$args];
}
function rest_ensure_response($value) { return $value; }
final class RestEnabledRequest {
    public function __construct(private array $headers,private array $json) {}
    public function get_header(string $key) { return $this->headers[$key]??null; }
    public function get_body() { return json_encode($this->json); }
    public function get_json_params() { return $this->json; }
}
final class RestEnabledAuthority implements BadAround_Identity_Community_Authority {
    public string $token='';
    public function active_identity(?string $token,int $now):?string {
        return $token===$this->token ? 'identity_12345678':null;
    }
    public function canonical_event_is_published(int $id):bool { return $id===801; }
    public function verified_owner_record(int $id):?array { return null; }
    public function may_moderate(string $id):bool { return false; }
}
final class RestEnabledInbox implements BadAround_Identity_Community_Inbox_Writer {
    public array $entries=[];
    public function submit(string $id,string $kind,string $actor,int $resource,array $intent,int $now):bool {
        $this->entries[]=[$id,$kind,$actor,$resource,$intent];
        return true;
    }
    public function decide(string $id,int $version,string $decision,string $actor,int $now):bool {
        return false;
    }
}
function rest_assert(bool $ok,string $label):void {
    if (!$ok) { throw new RuntimeException('FAIL '.$label); }
    echo 'PASS '.$label.PHP_EOL;
}
$key=str_repeat('R',32);
$now=time();
$sessions=new BadAround_Identity_Sessions(new BadAround_Identity_Session_Memory_Store(),$key);
$token=$sessions->issue('identity_12345678',$now);
$csrf=BadAround_Identity_Csrf_Policy::issue($token,$key);
$status=new BadAround_Identity_Active_Memory_Status();
$status->active['identity_12345678']=true;
$guard=new BadAround_Identity_Edit_Guard($sessions,$key,'https://badaround.it',$status);
$authority=new RestEnabledAuthority();$authority->token=$token;
$inbox=new RestEnabledInbox();
$gateway=new BadAround_Identity_Community_Gateway($authority,$inbox,$guard,$key,'https://badaround.it');
$rest=new BadAround_Identity_Community_Rest_Adapter($gateway);
rest_assert($rest->register_hooks(),'feature flag permits hook registration in isolated test');
$rest->register_routes();
rest_assert(isset($GLOBALS['ba_test_routes']['/community-comments'])
 && isset($GLOBALS['ba_test_routes']['/community-revisions'])
 && !isset($GLOBALS['ba_test_routes']['/community-moderate']),
 'only comment and blocked revision routes are declared');
$_COOKIE[BadAround_Identity_Community_Rest_Adapter::COOKIE_NAME]=$token;
$headers=['content-type'=>'application/json',
    'origin'=>'https://badaround.it','x-badaround-csrf'=>$csrf];
$payload=['event_id'=>801,'body'=>'Ho visto il veicolo in zona','display_mode'=>'anonymous'];
$request=new RestEnabledRequest($headers,$payload);
$result=$rest->submit($request,'comment');
rest_assert(($result['status']??null)==='pending_moderation' && count($inbox->entries)===1,
 'valid owner comment enters private moderation only');
$reply=$rest->submit(new RestEnabledRequest($headers,array_merge($payload,['parent_id'=>5])),'comment');
rest_assert(($reply['status']??null)===400 && count($inbox->entries)===1,
 'untrusted reply metadata rejected');
$badHeaders=$headers;$badHeaders['origin']='https://evil.example';
$result=$rest->submit(new RestEnabledRequest($badHeaders,$payload),'comment');
rest_assert(($result['status']??null)===403 && count($inbox->entries)===1,
 'cross-origin comment denied');
$revision=$rest->submit(new RestEnabledRequest($headers,['report_id'=>701,
    'snapshot_digest'=>str_repeat('a',64)]),'revision');
rest_assert(($revision['status']??null)===503 && count($inbox->entries)===1,
 'untrusted revision snapshot cannot be promoted');
echo "ALL_ENABLED_REST_BOUNDARY_TESTS_PASS\n";
