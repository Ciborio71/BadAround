<?php
/**
 * End-to-end WordPress composition -> REST -> verified PDO session ->
 * moderation inbox + budget. Disposable ba_identity_ci ONLY.
 */
declare(strict_types=1);
define('BADAROUND_IDENTITY_COMMUNITY_ENABLED',true);
require __DIR__.'/schema-candidate.php';
require __DIR__.'/wordpress-composition.php';

$dsn=(string)getenv('BA_TEST_DB_DSN');
if (!preg_match('/(?:^|;)dbname=ba_identity_ci(?:;|$)/',$dsn)) {
    throw new RuntimeException('Safety stop: only ephemeral ba_identity_ci is allowed');
}
$db=new PDO($dsn,getenv('BA_TEST_DB_USER')?:'ba_ci',getenv('BA_TEST_DB_PASS')?:'');
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$prefix='ba_composition_ci_';
foreach (BadAround_Identity_Schema_Candidate::statements($prefix) as $ddl) { $db->exec($ddl); }

function get_post($id) {
    return $id===801 ? (object)['post_type'=>'ba_evento','post_status'=>'publish'] : null;
}
function get_post_meta($id,$key,$single) {
    return $id===801 && $key==='_ba_moderation_status' ? 'published' : '';
}
function assert_composed(bool $pass,string $label):void {
    if (!$pass) { throw new RuntimeException('FAIL '.$label); }
    echo 'PASS '.$label.PHP_EOL;
}
final class ComposedRequest {
    public function __construct(private array $headers,private array $json) {}
    public function get_header(string $name) { return $this->headers[$name]??null; }
    public function get_json_params() { return $this->json; }
    public function get_body() { return json_encode($this->json,JSON_THROW_ON_ERROR); }
}

$root=str_repeat('Q',48); $now=time();
$identity='identity_12345678';
$db->prepare("INSERT INTO `{$prefix}ba_identities`
    (identity_id,email_lookup,protected_profile,verified_at,revoked_at)
    VALUES (:id,:email,:profile,:verified,NULL)")->execute([
    ':id'=>$identity,':email'=>str_repeat('e',64),':profile'=>'test-encrypted-placeholder',
    ':verified'=>$now-100
]);
$sessionKey=hash_hmac('sha256','BA-ID-01H:sessions',$root,true);
$csrfKey=hash_hmac('sha256','BA-ID-01H:csrf',$root,true);
$sessions=new BadAround_Identity_Sessions(
    new BadAround_Identity_Mysql_Session_Store($db,$prefix.'ba_identity_sessions'),
    $sessionKey
);
$session=$sessions->issue($identity,$now);
$csrf=BadAround_Identity_Csrf_Policy::issue($session,$csrfKey);
$adapter=BadAround_Identity_WordPress_Composition::assemble(
    $db,$prefix,$root,'https://badaround.it'
);
$_COOKIE[BadAround_Identity_Community_Rest_Adapter::COOKIE_NAME]=$session;
$headers=['content-type'=>'application/json','origin'=>'https://badaround.it',
    'x-badaround-csrf'=>$csrf];
$request=new ComposedRequest($headers,[
    'event_id'=>801,'body'=>'Ho visto qualcosa presso la stazione',
    'display_mode'=>'anonymous'
]);
$response=$adapter->submit($request,'comment');
assert_composed(($response['status']??null)==='pending_moderation',
    'real persisted Identity and REST request accepted');
$count=(int)$db->query("SELECT COUNT(*) FROM `{$prefix}ba_identity_moderation`")->fetchColumn();
assert_composed($count===1,'private comment written exactly once');
$states=$db->query("SELECT state,kind,payload FROM `{$prefix}ba_identity_moderation`")
    ->fetch(PDO::FETCH_ASSOC);
assert_composed($states['state']==='pending' && $states['kind']==='comment',
    'public publication not bypassed');
assert_composed(!str_contains($states['payload'],'stazione'),
    'original comment is encrypted');
$quota=(int)$db->query("SELECT COUNT(*) FROM `{$prefix}ba_identity_community_budget`")
    ->fetchColumn();
assert_composed($quota===1,'persistent quota consumed');
$invalid=$adapter->submit(new ComposedRequest(array_merge($headers,
    ['origin'=>'https://other.example']),['event_id'=>801,
    'body'=>'Altra informazione utile','display_mode'=>'anonymous']),'comment');
assert_composed(($invalid['status']??null)===403,
    'foreign Origin denied');
assert_composed((int)$db->query("SELECT COUNT(*) FROM `{$prefix}ba_identity_moderation`")
    ->fetchColumn()===1,'cross-origin did not save new content');
$revoke=$sessions->revoke($session,$now+1);
assert_composed($revoke,'persisted session revoked');
$revoked=$adapter->submit(new ComposedRequest($headers,[
    'event_id'=>801,'body'=>'Tentativo dopo revoca','display_mode'=>'anonymous'
]),'comment');
assert_composed(($revoked['status']??null)===403,
    'revoked session fails closed');
echo "ALL_WORDPRESS_COMPOSITION_E2E_TESTS_PASS\n";
