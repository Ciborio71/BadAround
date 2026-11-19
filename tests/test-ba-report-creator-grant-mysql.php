<?php
/** Real PDO/row-lock tests. Disposable ba_identity_ci only. */
declare(strict_types=1);
require_once __DIR__.'/../wordpress/plugins/badaround-core/identity-dormant/mysql-report-creator-grant.php';
$dsn=(string)getenv('BA_TEST_DB_DSN');
if (!preg_match('/(?:^|;)dbname=ba_identity_ci(?:;|$)/D',preg_replace('/^mysql:/','',$dsn)))
    throw new RuntimeException('CI database required');
$db=new PDO($dsn,(string)getenv('BA_TEST_DB_USER'),(string)getenv('BA_TEST_DB_PASS'),
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$p='ci_creator_';
$tables=['postmeta','posts','ba_reports','ba_identity_recovery','ba_identity_ownership',
    'ba_identity_pre_intake','ba_identities'];
final class CreatorTestLock implements BadAround_Report_Otp_Native_Lock {
    public bool $available=true;public int $depth=0;public int $released=0;
    public function acquire(string $uuid):bool {if(!$this->available)return false;++$this->depth;return true;}
    public function release(string $uuid):void {--$this->depth;++$this->released;}
}
final class CreatorDatabaseLock implements BadAround_Report_Otp_Native_Lock {
    public function __construct(private PDO $db){}
    public function acquire(string $uuid):bool {
        $q=$this->db->prepare('SELECT GET_LOCK(?,0)');
        $q->execute(['ci:'.substr(hash('sha256','native:'.$uuid),0,60)]);
        $acquired=(int)$q->fetchColumn()===1;
        if (($GLOBALS['argv'][1]??null)==='--worker') {echo "LOCKED\n";flush();}
        return $acquired;
    }
    public function release(string $uuid):void {
        $q=$this->db->prepare('SELECT RELEASE_LOCK(?)');
        $q->execute(['ci:'.substr(hash('sha256','native:'.$uuid),0,60)]);
    }
}
function creatorCheck(bool $yes,string $label):void {
    if(!$yes)throw new RuntimeException('FAIL '.$label);
    echo 'PASS '.$label.PHP_EOL;
}
$uuid='123e4567-e89b-42d3-a456-426614174000';$op=str_repeat('a',32);$id='creator_identity_01';
$now=1793880000;$lock=new CreatorTestLock();
$guard=new BadAround_Identity_Mysql_Report_Creator_Grant($db,$p,$lock);
if (($argv[1]??null)==='--worker') {
    echo "READY\n";flush();
    $worker=new BadAround_Identity_Mysql_Report_Creator_Grant($db,$p,new CreatorDatabaseLock($db));
    echo $worker->grant_creator($id,'report',10,$now)?"GRANTED\n":"DENIED\n";
    exit;
}
try {
    foreach($tables as $t)$db->exec("DROP TABLE IF EXISTS `{$p}{$t}`");
    creatorCheck(!$guard->grant_creator($id,'report',10,$now),'missing schema denies without writes');
    $db->exec("CREATE TABLE `{$p}ba_identities` (identity_id VARCHAR(128) PRIMARY KEY,
        verified_at BIGINT NULL,revoked_at BIGINT NULL) ENGINE=InnoDB");
    $db->exec(BadAround_Identity_Pre_Intake_Schema::create_sql($p));
    $db->exec("CREATE TABLE `{$p}ba_identity_recovery` (operation_id CHAR(32) PRIMARY KEY,
        identity_id VARCHAR(128),report_id BIGINT UNIQUE,event_id BIGINT,state VARCHAR(24)) ENGINE=InnoDB");
    $db->exec("CREATE TABLE `{$p}ba_identity_ownership` (identity_id VARCHAR(128),resource_kind VARCHAR(24),
        resource_id BIGINT,relation VARCHAR(24),verified_binding_at BIGINT,revoked_at BIGINT NULL,
        UNIQUE KEY resource(resource_kind,resource_id)) ENGINE=InnoDB");
    $db->exec("CREATE TABLE `{$p}ba_reports` (id BIGINT PRIMARY KEY,event_id BIGINT,content_original LONGTEXT,
        status VARCHAR(32),source_type VARCHAR(32),source_form_id BIGINT,source_entry_id BIGINT,
        created_at DATETIME) ENGINE=InnoDB");
    $db->exec("CREATE TABLE `{$p}posts` (ID BIGINT PRIMARY KEY,post_type VARCHAR(32),post_status VARCHAR(32)) ENGINE=InnoDB");
    $db->exec("CREATE TABLE `{$p}postmeta` (post_id BIGINT,meta_key VARCHAR(64),meta_value VARCHAR(64),
        KEY binding(post_id,meta_key)) ENGINE=InnoDB");
    $reset=static function()use($db,$p,$tables,$uuid,$op,$id,$now):void {
        foreach($tables as $t)$db->exec("DELETE FROM `{$p}{$t}`");
        $db->prepare("INSERT INTO `{$p}ba_identities` VALUES (?, ?, NULL)")->execute([$id,$now-10]);
        $db->prepare("INSERT INTO `{$p}ba_identity_pre_intake`
            (operation_id,submission_id,identity_id,state,report_id,event_id,created_at,linked_at,revision)
            VALUES (?,?,?,'linked',10,20,?,?,1)")->execute([$op,$uuid,$id,$now-2,$now-1]);
        $db->prepare("INSERT INTO `{$p}ba_identity_recovery` VALUES (?,?,10,20,'pending')")->execute([$op,$id]);
        $hash=hash('sha256',$uuid);
        $body=json_encode(['submission_id'=>$uuid,'canonical'=>['submission_id'=>$uuid]],JSON_THROW_ON_ERROR);
        $db->prepare("INSERT INTO `{$p}ba_reports` VALUES (10,20,?,'validated','native',?,?,?)")->execute(
            [$body,(int)hexdec(substr($hash,0,7)),(int)hexdec(substr($hash,7,15)),gmdate('Y-m-d H:i:s',$now-1)]);
        $db->exec("INSERT INTO `{$p}posts` VALUES (20,'ba_evento','pending')");
        $db->exec("INSERT INTO `{$p}postmeta` VALUES (20,'_ba_primary_report_id','10')");
    };
    $reset();
    creatorCheck($guard->grant_creator($id,'report',10,$now),'trusted reserved report grants creator');
    creatorCheck($guard->grant_creator($id,'report',10,$now),'same canonical owner is idempotent');
    creatorCheck((int)$db->query("SELECT COUNT(*) FROM `{$p}ba_identity_ownership`")->fetchColumn()===1,
        'idempotency creates no duplicate owner');
    creatorCheck(!$guard->grant_creator('other_identity_01','report',10,$now),'foreign identity denied');
    creatorCheck(!$guard->grant_creator($id,'comment',10,$now),'report boundary refuses other resource kinds');
    $reset();$db->exec("UPDATE `{$p}ba_identities` SET revoked_at=1");
    creatorCheck(!$guard->grant_creator($id,'report',10,$now),'revoked identity denied inside transaction');
    creatorCheck(!$db->inTransaction(),'denial rolls back owned transaction');
    $reset();$db->exec("UPDATE `{$p}ba_identities` SET verified_at=NULL");
    creatorCheck(!$guard->grant_creator($id,'report',10,$now),'unverified identity denied');
    $reset();$db->exec("DELETE FROM `{$p}ba_identity_recovery`");
    creatorCheck(!$guard->grant_creator($id,'report',10,$now),'missing durable intent denied');
    $reset();$db->exec("UPDATE `{$p}ba_identity_recovery` SET event_id=21");
    creatorCheck(!$guard->grant_creator($id,'report',10,$now),'journal event mismatch denied');
    $reset();$db->exec("UPDATE `{$p}ba_identity_pre_intake` SET state='reserved'");
    creatorCheck(!$guard->grant_creator($id,'report',10,$now),'unlinked reservation denied');
    $reset();$db->prepare("UPDATE `{$p}ba_reports` SET created_at=?")->execute([gmdate('Y-m-d H:i:s',$now-3)]);
    creatorCheck(!$guard->grant_creator($id,'report',10,$now),'report predating reservation denied');
    $reset();$db->exec("INSERT INTO `{$p}postmeta` VALUES (20,'_ba_primary_report_id','10')");
    creatorCheck(!$guard->grant_creator($id,'report',10,$now),'ambiguous canonical binding denied');
    $reset();$db->exec("UPDATE `{$p}ba_reports` SET content_original='{}'");
    creatorCheck(!$guard->grant_creator($id,'report',10,$now),'invalid canonical receipt denied');
    $reset();$db->exec("INSERT INTO `{$p}ba_identity_ownership` VALUES ('foreign_identity','report',10,'creator',1,NULL)");
    creatorCheck(!$guard->grant_creator($id,'report',10,$now),'conflicting creator never overwritten');
    $reset();$db->prepare("INSERT INTO `{$p}ba_identity_ownership` VALUES (?,'report',10,'creator',1,1)")->execute([$id]);
    creatorCheck(!$guard->grant_creator($id,'report',10,$now),'revoked ownership never restored');
    $reset();$db->exec("UPDATE `{$p}ba_identity_recovery` SET state='bound'");
    creatorCheck(!$guard->grant_creator($id,'report',10,$now),'bound journal with missing owner requires review');
    $reset();$lock->available=false;
    creatorCheck(!$guard->grant_creator($id,'report',10,$now),'native lock unavailable denies');
    $lock->available=true;
    $db->beginTransaction();
    creatorCheck(!$guard->grant_creator($id,'report',10,$now) && $db->inTransaction(),
        'caller transaction is never committed or rolled back');
    $db->rollBack();
    // Real independent process/connection: hold identity revocation uncommitted
    // while a grant reaches SELECT FOR UPDATE, then commit. The grant must
    // observe revocation after waiting, rather than a stale pre-lock status.
    $reset();
    $db->beginTransaction();
    $db->exec("UPDATE `{$p}ba_identities` SET revoked_at=1");
    $worker=proc_open([PHP_BINARY,__FILE__,'--worker'],
        [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if(!is_resource($worker))throw new RuntimeException('Unable to start concurrency worker');
    fclose($pipes[0]);
    stream_set_timeout($pipes[1],10);
    stream_set_timeout($pipes[2],10);
    creatorCheck(trim((string)fgets($pipes[1]))==='READY','independent grant process started');
    $entered=trim((string)fgets($pipes[1]))==='LOCKED';
    usleep(200000);
    $waiting=$entered && proc_get_status($worker)['running'];
    // Commit before assertions, so a failed assertion cannot strand a worker.
    $db->commit();
    $output=stream_get_contents($pipes[1]);$errors=stream_get_contents($pipes[2]);
    fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($worker);
    creatorCheck($waiting,'grant remains blocked while identity revocation is uncommitted');
    creatorCheck($exit===0 && trim($output)==='DENIED' && $errors==='',
        'concurrent committed revocation prevents creator grant');
    creatorCheck((int)$db->query("SELECT COUNT(*) FROM `{$p}ba_identity_ownership`")->fetchColumn()===0,
        'revocation race leaves no owner');
    $reset();
    $nativeLock=new CreatorDatabaseLock($db);
    creatorCheck($nativeLock->acquire($uuid),'recovery acquires canonical native lock');
    $nestedGuard=new BadAround_Identity_Mysql_Report_Creator_Grant($db,$p,$nativeLock);
    creatorCheck($nestedGuard->grant_creator($id,'report',10,$now),
        'grant works under the recovery caller native lock');
    $q=$db->prepare('SELECT IS_USED_LOCK(?)');$q->execute(['ci:'.substr(hash('sha256','native:'.$uuid),0,60)]);
    creatorCheck($q->fetchColumn()!==null,'nested grant preserves caller native lock');
    $nativeLock->release($uuid);
    $reset();
    $db->exec("ALTER TABLE `{$p}ba_identity_ownership` DROP INDEX resource");
    creatorCheck(!$guard->grant_creator($id,'report',10,$now),'missing unique ownership constraint denies');
    creatorCheck($lock->depth===0,'all acquired native locks released');
    creatorCheck((int)$db->query("SELECT COUNT(*) FROM `{$p}ba_identity_ownership`")->fetchColumn()===0,
        'denied scenarios leave no creator');
} finally {
    if($db->inTransaction())$db->rollBack();
    foreach($tables as $t)$db->exec("DROP TABLE IF EXISTS `{$p}{$t}`");
}
echo 'ALL_REPORT_CREATOR_GRANT_MYSQL_TESTS_PASS'.PHP_EOL;
