<?php
/** Private WordPress recovery wiring smoke test — disposable MySQL only. */
declare(strict_types=1);
require_once __DIR__.'/../wordpress/plugins/badaround-core/identity-dormant/wordpress-pre-intake-recovery-composition.php';

$dsn=(string)getenv('BA_TEST_DB_DSN');
if (!str_contains($dsn,'dbname=ba_identity_ci'))
    throw new RuntimeException('Safety stop: CI database only');
$db=new PDO($dsn,(string)getenv('BA_TEST_DB_USER'),(string)getenv('BA_TEST_DB_PASS'));
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
function expectComposition(bool $yes,string $message):void {
    if (!$yes) throw new RuntimeException('FAIL '.$message);
    echo 'PASS '.$message.PHP_EOL;
}
final class QARepo {
    public int $acquired=0;
    public int $released=0;
    public function acquire_native_lock(string $submission,int $wait):bool {
        if($wait!==0)return false;
        ++$this->acquired;return true;
    }
    public function release_native_lock(string $submission):void {++$this->released;}
}
final class QAProof implements BadAround_Identity_Intake_Receipt {
    public function is_new_canonical_report(int $report,int $event):bool{return false;}
}
$status=new BadAround_Identity_Active_Memory_Status();
$repo=new QARepo();
$proof=new QAProof();
$prefix='ci_recovery_';
$table=BadAround_Identity_Pre_Intake_Schema::table($prefix);
$db->exec("DROP TABLE IF EXISTS `{$table}`");
expectComposition(
    BadAround_Identity_WordPress_Recovery_Composition::assemble($db,$prefix,$repo,$status,$proof)===null,
    'disabled private QA never assembles recovery'
);
define('BADAROUND_IDENTITY_PRIVATE_QA_ENABLED',true);
expectComposition(
    BadAround_Identity_WordPress_Recovery_Composition::assemble($db,$prefix,$repo,$status,$proof)===null,
    'missing schema fails closed without creating any table'
);
$db->exec(BadAround_Identity_Pre_Intake_Schema::create_sql($prefix));
try {
    $worker=BadAround_Identity_WordPress_Recovery_Composition::assemble(
        $db,$prefix,$repo,$status,$proof
    );
    expectComposition($worker instanceof BadAround_Identity_Pre_Intake_Recovery_Worker,
        'validated QA schema permits private worker composition');
    $id='123e4567-e89b-42d3-a456-426614174000';
    expectComposition($worker->recover($id,12345)==='unavailable',
        'unknown submission cannot be assigned ownership');
    expectComposition($repo->acquired===1 && $repo->released===1,
        'native report lock acquired and released by composed worker');
} finally {
    $db->exec("DROP TABLE IF EXISTS `{$table}`");
}
echo 'ALL_WORDPRESS_RECOVERY_COMPOSITION_TESTS_PASS'.PHP_EOL;
