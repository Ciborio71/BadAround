<?php
/** Disposable MySQL integration only: must never connect to production. */
declare(strict_types=1);
require __DIR__.'/mysql-moderation-inbox.php';
require __DIR__.'/comment-moderation-policy.php';
require __DIR__.'/revision-request-policy.php';
$dsn=getenv('BA_TEST_DB_DSN');
if (!$dsn || !str_contains($dsn,'dbname=ba_identity_ci')) {
    throw new RuntimeException('Only ba_identity_ci allowed');
}
$db=new PDO($dsn,getenv('BA_TEST_DB_USER')?:'root',getenv('BA_TEST_DB_PASS')?:'');
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec("CREATE TABLE IF NOT EXISTS ba_identity_moderation_ci (
 intent_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 kind VARCHAR(16) NOT NULL,identity_id VARCHAR(128) NOT NULL,
 resource_id BIGINT UNSIGNED NOT NULL,payload LONGBLOB NOT NULL,
 nonce VARBINARY(12) NOT NULL,tag VARBINARY(16) NOT NULL,
 state VARCHAR(16) NOT NULL,version INT NOT NULL,
 created_at BIGINT UNSIGNED NOT NULL,moderator_id VARCHAR(128) NULL,
 moderated_at BIGINT UNSIGNED NULL
) ENGINE=InnoDB");
$db->exec('DELETE FROM ba_identity_moderation_ci');
function inbox_assert(bool $ok,string $msg):void {
    if (!$ok) { throw new RuntimeException('FAIL '.$msg); }
    echo 'PASS '.$msg.PHP_EOL;
}
$now=1762156800;
$s=new BadAround_Identity_Moderation_Inbox($db,'ba_identity_moderation_ci',str_repeat('K',32));
$comment=BadAround_Identity_Comment_Moderation_Policy::propose(
    'identity_12345678',801,'Ho visto qualcosa nella zona','anonymous',null,$now);
$cid=str_repeat('a',32);
inbox_assert($s->submit($cid,'comment','identity_12345678',801,$comment,$now),
    'pending comment persisted');
inbox_assert(!$s->submit($cid,'comment','identity_12345678',801,$comment,$now),
    'duplicate comment blocked');
inbox_assert($s->load_private($cid)['intent']['body']==='Ho visto qualcosa nella zona',
    'private comment roundtrip');
$raw=$db->query("SELECT payload FROM ba_identity_moderation_ci")->fetchColumn();
inbox_assert(!str_contains($raw,'qualcosa'),'comment encrypted at rest');
inbox_assert($s->decide($cid,0,'approved','moderator_12345',$now+1),
    'moderation decision stored');
inbox_assert(!$s->decide($cid,0,'rejected','moderator_12345',$now+2),
    'stale moderation CAS denied');
$revision=BadAround_Identity_Revision_Request_Policy::propose(
    'identity_12345678',701,'Descrizione integrata della segnalazione','Correzione',
    true,hash('sha256','server-original'),$now);
$rid=str_repeat('b',32);
inbox_assert($s->submit($rid,'revision','identity_12345678',701,$revision,$now),
    'revision stored privately');
inbox_assert($s->load_private($rid)['intent']['public_mutation']===false,
    'revision cannot mutate public event');
inbox_assert($s->decide($rid,0,'rejected','moderator_12345',$now+1),
    'revision rejection stored');
echo "ALL_MYSQL_MODERATION_INBOX_TESTS_PASS\n";
