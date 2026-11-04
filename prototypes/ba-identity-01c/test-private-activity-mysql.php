<?php
/** BA-IDENTITY-02E disposable PDO regression: own-only safe activity. */
declare(strict_types=1);
require __DIR__.'/schema-candidate.php';
require __DIR__.'/mysql-private-activity.php';
require_once __DIR__.'/mysql-session-store.php';
$dsn=(string)getenv('BA_TEST_DB_DSN');
if(!preg_match('/(?:^|;)dbname=ba_identity_ci(?:;|$)/',$dsn)) {
    throw new RuntimeException('CI database required');
}
$db=new PDO($dsn,getenv('BA_TEST_DB_USER')?:'ba_ci',getenv('BA_TEST_DB_PASS')?:'');
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$prefix='ba_activity_ci_';
$ddl=BadAround_Identity_Schema_Candidate::statements($prefix);
foreach(['ba_identity_moderation','ba_identity_sessions'] as $name) {
    $db->exec($ddl[$name]);
    $db->exec('DELETE FROM `'.$prefix.$name.'`');
}
function chk_activity(bool $ok,string $label):void {
    if(!$ok)throw new RuntimeException('FAIL '.$label);
    echo 'PASS '.$label.PHP_EOL;
}
class ActivityStatus implements BadAround_Identity_Active_Status {
    public bool $enabled=true;
    public function is_active_verified(string $id):bool {
        return $this->enabled && $id==='identity_12345678';
    }
}
$now=1762156800;$identity='identity_12345678';$other='identity_87654321';
$sessions=new BadAround_Identity_Sessions(
    new BadAround_Identity_Mysql_Session_Store($db,$prefix.'ba_identity_sessions'),
    str_repeat('S',32)
);
$token=$sessions->issue($identity,$now);
$status=new ActivityStatus();$csrfKey=str_repeat('C',32);
$csrf=BadAround_Identity_Csrf_Policy::issue($token,$csrfKey);
$stmt=$db->prepare("INSERT INTO `{$prefix}ba_identity_moderation`
    (intent_id,kind,identity_id,resource_id,payload,nonce,tag,state,created_at)
    VALUES (:id,:kind,:identity,:resource,:payload,:nonce,:tag,:state,:created)");
foreach([
    [str_repeat('a',32),'comment',$identity,123,'pending'],
    [str_repeat('b',32),'revision',$identity,87,'approved'],
    [str_repeat('c',32),'comment',$other,999,'rejected']
] as [$id,$kind,$who,$resource,$state]) {
    $stmt->execute([':id'=>$id,':kind'=>$kind,':identity'=>$who,
        ':resource'=>$resource,':payload'=>'encrypted-secret',
        ':nonce'=>str_repeat('N',12),':tag'=>str_repeat('T',16),
        ':state'=>$state,':created'=>$now]);
}
$service=new BadAround_Identity_Private_Activity(
    $db,$prefix.'ba_identity_moderation',$sessions,$status,$csrfKey,'https://badaround.it'
);
$mine=$service->mine($token,$csrf,'https://badaround.it',$now+1);
chk_activity(is_array($mine) && count($mine)===2,'only own activity visible');
chk_activity(!str_contains(json_encode($mine),'encrypted-secret')
    && !str_contains(json_encode($mine),$other)
    && !str_contains(json_encode($mine),'moderator_id'),
    'no encrypted body, other identity or private audit fields');
chk_activity($service->mine($token,'bad','https://badaround.it',$now+1)===null,
    'CSRF mandatory');
chk_activity($service->mine($token,$csrf,'https://evil.example',$now+1)===null,
    'strict origin check');
$status->enabled=false;
chk_activity($service->mine($token,$csrf,'https://badaround.it',$now+1)===null,
    'revoked identity cannot read activity');
$status->enabled=true;
$sessions->revoke($token,$now+2);
chk_activity($service->mine($token,$csrf,'https://badaround.it',$now+3)===null,
    'revoked session cannot read activity');
echo "ALL_PRIVATE_ACTIVITY_MYSQL_TESTS_PASS\n";
