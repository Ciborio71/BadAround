<?php
/** Disposable live-like registration composition end-to-end, no real mail. */
declare(strict_types=1);
define('BADAROUND_IDENTITY_REGISTRATION_ENABLED',true);
require __DIR__.'/schema-candidate.php';
require __DIR__.'/wordpress-registration-composition.php';
$dsn=(string)getenv('BA_TEST_DB_DSN');
if (!preg_match('/(?:^|;)dbname=ba_identity_ci(?:;|$)/',$dsn)) {
    throw new RuntimeException('CI database only');
}
$db=new PDO($dsn,getenv('BA_TEST_DB_USER')?:'ba_ci',getenv('BA_TEST_DB_PASS')?:'');
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$prefix='ba_regcompose_ci_';
$ddl=BadAround_Identity_Schema_Candidate::statements($prefix);
foreach (['ba_identity_challenges','ba_identity_pending','ba_identities',
    'ba_identity_rate_limits'] as $name) {
    $db->exec($ddl[$name]);
    $db->exec('DELETE FROM `'.$prefix.$name.'`');
}
function is_ssl():bool{return true;}
function rest_ensure_response($value){return $value;}
final class ComposeMailer {
    public array $messages=[];
    public function send(string $recipient,string $subject,string $message):bool{
        $this->messages[]=[$recipient,$subject,$message];
        return true;
    }
}
final class ComposeRequest {
    public function __construct(private array $data,private array $headers=[]){}
    public function get_header($name){
        return $this->headers[$name]??[
            'content-type'=>'application/json','origin'=>'https://badaround.it',
            'sec-fetch-site'=>'same-origin'
        ][$name]??null;
    }
    public function get_json_params():array{return $this->data;}
    public function get_body():string{return json_encode($this->data,JSON_THROW_ON_ERROR);}
}
function composed_check(bool $ok,string $name):void {
    if (!$ok)throw new RuntimeException('FAIL '.$name);
    echo 'PASS '.$name.PHP_EOL;
}
$root=str_repeat('Y',48);
$mailer=new ComposeMailer();
$api=BadAround_Identity_WordPress_Registration_Composition::assemble(
    $db,$prefix,$root,'privacy-2026-11',$mailer,'https://badaround.it'
);
$_SERVER['REMOTE_ADDR']='127.0.0.1';
$profile=['first_name'=>'Anna','last_name'=>'Rossi','email'=>'anna@example.com'];
$refused=$api->start(new ComposeRequest($profile+['privacy_acknowledged'=>false]));
composed_check(($refused['status']??null)===400 && count($mailer->messages)===0,
    'no private profile or email without explicit consent');
$started=$api->start(new ComposeRequest($profile+['privacy_acknowledged'=>true]));
composed_check(($started['ok']??false) && count($mailer->messages)===1
    && preg_match('/^[a-f0-9]{32}$/D',$started['challenge_id']),
    'valid registration creates one opaque pending challenge');
$code=null;
if (preg_match('/\b([0-9]{8})\b/',$mailer->messages[0][2],$match)) {
    $code=$match[1];
}
composed_check(is_string($code),'trusted fake email received delivery code');
$pending=$db->prepare('SELECT protected_profile FROM `'.$prefix.'ba_identity_pending`
    WHERE challenge_id=?');
$pending->execute([$started['challenge_id']]);
$encrypted=(string)$pending->fetchColumn();
composed_check(strlen($encrypted)>40
    && !str_contains($encrypted,'Anna')
    && !str_contains($encrypted,'privacy-2026-11'),
    'private profile and consent evidence encrypted on MariaDB');
$verified=$api->verify(new ComposeRequest([
    'challenge_id'=>$started['challenge_id'],'code'=>$code
]));
composed_check(($verified['status']??null)==='verified'
    && !isset($verified['identity_id']),
    'OTP activates exactly one identity without revealing ID');
composed_check((int)$db->query('SELECT COUNT(*) FROM `'.$prefix.'ba_identities`')
    ->fetchColumn()===1,'verified identity persisted once');
$replay=$api->verify(new ComposeRequest([
    'challenge_id'=>$started['challenge_id'],'code'=>$code
]));
composed_check(($replay['status']??null)===403,
    'replayed enrollment OTP denied');
composed_check((int)$db->query('SELECT COUNT(*) FROM `'.$prefix.'ba_identity_moderation`')
    ->fetchColumn()===0,'registration never creates moderation or public content');
echo "ALL_WORDPRESS_REGISTRATION_COMPOSITION_TESTS_PASS\n";
