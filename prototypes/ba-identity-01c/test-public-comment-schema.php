<?php
/** BA-IDENTITY-02G public projection schema tests, disposable DB ONLY. */
declare(strict_types=1);
require __DIR__.'/public-comment-schema-candidate.php';
$dsn=(string)getenv('BA_TEST_DB_DSN');
if (!preg_match('/(?:^|;)dbname=ba_identity_ci(?:;|$)/',$dsn)) {
    throw new RuntimeException('CI disposable database only');
}
$pdo=new PDO($dsn,getenv('BA_TEST_DB_USER')?:'ba_ci',getenv('BA_TEST_DB_PASS')?:'');
$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$prefix='ba_publiccomment_ci_';
$pdo->exec(BadAround_Identity_Public_Comment_Schema::statement($prefix));
$table=$prefix.'ba_identity_public_comments';
$pdo->exec("DELETE FROM `{$table}`");
function pass_public(bool $ok,string $name):void {
    if (!$ok) throw new RuntimeException('FAIL '.$name);
    echo 'PASS '.$name.PHP_EOL;
}
$insert=$pdo->prepare("INSERT INTO `{$table}`
    (intent_id,event_id,parent_id,display_name,body,published_at,status)
    VALUES (:intent,:event,:parent,:display,:body,:time,'visible')");
$base=[
    ':intent'=>str_repeat('a',32),':event'=>123,':parent'=>null,
    ':display'=>'Anonimo',':body'=>'Informazione utile sulla zona',':time'=>1762220000
];
$insert->execute($base);
$parent=(int)$pdo->lastInsertId();
pass_public($parent>0,'approved comment receives stable numeric id');
$reply=$base;
$reply[':intent']=str_repeat('b',32);
$reply[':parent']=$parent;
$reply[':body']='Grazie, aggiornamento confermato';
$insert->execute($reply);
pass_public((int)$pdo->query("SELECT COUNT(*) FROM `{$table}` WHERE parent_id={$parent}")->fetchColumn()===1,
    'one-level reply represented by canonical parent id');
$rejected=false;
try {$insert->execute($base);}catch(PDOException $e){$rejected=$e->getCode()==='23000';}
pass_public($rejected,'one intent cannot publish twice');
$q=$pdo->prepare("SELECT comment_id,parent_id,display_name,body,published_at
    FROM `{$table}` WHERE event_id=:event AND status='visible'
    ORDER BY published_at ASC, comment_id ASC");
$q->execute([':event'=>123]);
$visible=$q->fetchAll(PDO::FETCH_ASSOC);
pass_public(count($visible)===2 && !array_key_exists('identity_id',$visible[0])
    && !array_key_exists('email',$visible[0]),
    'public read model has no private identity fields');
$pdo->exec("UPDATE `{$table}` SET status='hidden'
    WHERE intent_id='".str_repeat('a',32)."'");
$q->execute([':event'=>123]);
pass_public(count($q->fetchAll(PDO::FETCH_ASSOC))===1,
    'hidden comment excluded by visible-only query');
echo "ALL_PUBLIC_COMMENT_SCHEMA_CI_TESTS_PASS\n";
