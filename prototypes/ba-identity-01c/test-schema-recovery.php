<?php
/** End-to-end additive schema and empty-table rollback rehearsal. */
declare(strict_types=1);
require __DIR__.'/schema-recovery-policy.php';
$dsn=(string)getenv('BA_TEST_DB_DSN');
if(!preg_match('/(?:^|;)dbname=ba_identity_ci(?:;|$)/',$dsn)) {
    throw new RuntimeException('Safety stop: only disposable ba_identity_ci');
}
$db=new PDO($dsn,getenv('BA_TEST_DB_USER')?:'ba_ci',getenv('BA_TEST_DB_PASS')?:'');
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$prefix='ba_recovery_ci_';
function must(bool $pass,string $name):void {
    if(!$pass) { throw new RuntimeException('FAIL '.$name); }
    echo 'PASS '.$name.PHP_EOL;
}
$db->exec("CREATE TABLE IF NOT EXISTS ba_recovery_ci_baseline_fixture (
    stable_id INT NOT NULL PRIMARY KEY, stable_data VARCHAR(80) NOT NULL
) ENGINE=InnoDB");
$db->exec("INSERT IGNORE INTO ba_recovery_ci_baseline_fixture VALUES (1,'baseline remains')");
foreach(BadAround_Identity_Schema_Candidate::statements($prefix) as $ddl){
    $db->exec($ddl);
}
$inventory=BadAround_Identity_Schema_Recovery_Policy::inspect($db,$prefix);
must(count($inventory)===10,'ten Identity tables inventoried');
must(count(array_filter($inventory,static fn($s):bool=>$s['exists'] && $s['rows']===0))===10,
    'new Identity tables are empty');
$refused=false;
try { BadAround_Identity_Schema_Recovery_Policy::drop_empty_disposable($db,$prefix,false); }
catch(RuntimeException $e) { $refused=true; }
must($refused,'no rollback without explicit safety switch');
$db->exec("INSERT INTO `{$prefix}ba_identity_wp_actors`
    (wp_user_id,identity_id,verified_at,version) VALUES (777,'identity_rollback_fixture',1762156800,0)");
$refused=false;
try { BadAround_Identity_Schema_Recovery_Policy::drop_empty_disposable($db,$prefix,true); }
catch(RuntimeException $e) { $refused=true; }
must($refused,'nonempty actor mapping blocks all destructive rollback');
$db->exec("DELETE FROM `{$prefix}ba_identity_wp_actors`");
BadAround_Identity_Schema_Recovery_Policy::drop_empty_disposable($db,$prefix,true);
$after=BadAround_Identity_Schema_Recovery_Policy::inspect($db,$prefix);
must(count(array_filter($after,static fn($s):bool=>$s['exists']))===0,
    'all ten empty Identity tables removed during CI rehearsal');
must((string)$db->query("SELECT stable_data FROM ba_recovery_ci_baseline_fixture WHERE stable_id=1")
    ->fetchColumn()==='baseline remains','non-Identity baseline fixture untouched');
echo "ALL_IDENTITY_RECOVERY_REHEARSAL_TESTS_PASS\n";
