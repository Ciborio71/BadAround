<?php
/** End-to-end dormant gateway -> MySQL inbox against disposable CI only. */
declare(strict_types=1);
require __DIR__.'/community-gateway.php';
require __DIR__.'/mysql-moderation-inbox.php';
$dsn=getenv('BA_TEST_DB_DSN');
if (!$dsn || !str_contains($dsn,'dbname=ba_identity_ci')) {
    throw new RuntimeException('Safety stop: disposable database required');
}
$db=new PDO($dsn,getenv('BA_TEST_DB_USER')?:'root',getenv('BA_TEST_DB_PASS')?:'');
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec("CREATE TABLE IF NOT EXISTS ba_identity_gateway_ci (
 intent_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 kind VARCHAR(16) NOT NULL, identity_id VARCHAR(128) NOT NULL,
 resource_id BIGINT UNSIGNED NOT NULL, payload LONGBLOB NOT NULL,
 nonce VARBINARY(12) NOT NULL, tag VARBINARY(16) NOT NULL,
 state VARCHAR(16) NOT NULL, version INT NOT NULL,
 created_at BIGINT UNSIGNED NOT NULL, moderator_id VARCHAR(128) NULL,
 moderated_at BIGINT UNSIGNED NULL
) ENGINE=InnoDB");
$db->exec('DELETE FROM ba_identity_gateway_ci');
final class GatewayDbAuthority implements BadAround_Identity_Community_Authority {
    public string $token='';
    public bool $active=true;
    public bool $moderator=false;
    public function active_identity(?string $token,int $now):?string {
        return $this->active && $token===$this->token ? 'identity_12345678':null;
    }
    public function canonical_event_is_published(int $eventId):bool { return $eventId===801; }
    public function verified_owner_record(int $reportId):?array {
        if ($reportId!==701) { return null; }
        return ['identity_id'=>'identity_12345678','resource_kind'=>'report',
            'resource_id'=>701,'relation'=>'creator',
            'verified_binding_at'=>'2026-11-03T09:00:00Z','revoked_at'=>null];
    }
    public function may_moderate(string $identityId):bool { return $this->moderator; }
}
final class GatewayDbWriter implements BadAround_Identity_Community_Inbox_Writer {
    public function __construct(private BadAround_Identity_Moderation_Inbox $storage) {}
    public function submit(string $id,string $kind,string $identity,int $resource,array $intent,int $now):bool {
        return $this->storage->submit($id,$kind,$identity,$resource,$intent,$now);
    }
    public function decide(string $id,int $version,string $decision,string $moderator,int $now):bool {
        return $this->storage->decide($id,$version,$decision,$moderator,$now);
    }
}
function integration_assert(bool $ok,string $description):void {
    if (!$ok) { throw new RuntimeException('FAIL: '.$description); }
    echo 'PASS: '.$description.PHP_EOL;
}
$now=1762156800; $key=str_repeat('K',32);
$sessionStore=new BadAround_Identity_Session_Memory_Store();
$sessions=new BadAround_Identity_Sessions($sessionStore,$key);
$token=$sessions->issue('identity_12345678',$now);
$csrf=BadAround_Identity_Csrf_Policy::issue($token,$key);
$status=new BadAround_Identity_Active_Memory_Status();
$status->active['identity_12345678']=true;
$guard=new BadAround_Identity_Edit_Guard($sessions,$key,'https://badaround.it',$status);
$authority=new GatewayDbAuthority(); $authority->token=$token;
$storage=new BadAround_Identity_Moderation_Inbox($db,'ba_identity_gateway_ci',str_repeat('M',32));
$gateway=new BadAround_Identity_Community_Gateway(
    $authority,new GatewayDbWriter($storage),$guard,$key,'https://badaround.it'
);
integration_assert($gateway->submit_comment($token,$csrf,'https://badaround.it',
    801,'Ho visto un veicolo nella zona','anonymous',null,$now),
    'authenticated comment stored in encrypted MySQL');
integration_assert($gateway->submit_revision($token,$csrf,'https://badaround.it',
    701,'Descrizione aggiornata della segnalazione','Nuovi dettagli',
    hash('sha256','original snapshot'),$now),
    'creator revision stored in encrypted MySQL');
$rows=$db->query("SELECT intent_id,kind,state,payload FROM ba_identity_gateway_ci ORDER BY kind")->fetchAll(PDO::FETCH_ASSOC);
integration_assert(count($rows)===2 && $rows[0]['kind']==='comment' && $rows[1]['kind']==='revision',
    'two independent private requests persisted');
integration_assert($rows[0]['state']==='pending' && $rows[1]['state']==='pending',
    'no public publication by submit');
integration_assert(!str_contains($rows[0]['payload'],'veicolo') &&
    !str_contains($rows[1]['payload'],'Descrizione'),
    'private content is ciphertext');
integration_assert(!$gateway->moderate($token,$csrf,'https://badaround.it',
    $rows[0]['intent_id'],0,'approved',$now+1),
    'nonmoderator cannot approve');
$authority->moderator=true;
integration_assert($gateway->moderate($token,$csrf,'https://badaround.it',
    $rows[0]['intent_id'],0,'approved',$now+1),
    'moderator decision persists');
integration_assert(!$gateway->moderate($token,$csrf,'https://badaround.it',
    $rows[0]['intent_id'],0,'rejected',$now+2),
    'stale moderation decision blocked');
integration_assert($storage->load_private($rows[1]['intent_id'])['intent']['public_mutation']===false,
    'revision cannot mutate original report');
$authority->active=false;
integration_assert(!$gateway->submit_comment($token,$csrf,'https://badaround.it',
    801,'Altro dettaglio nella zona','anonymous',null,$now),
    'inactive identity cannot create new request');
echo "ALL_GATEWAY_MYSQL_E2E_TESTS_PASS\n";
