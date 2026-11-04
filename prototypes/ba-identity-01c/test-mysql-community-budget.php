<?php
/** MySQL-only authenticated community quota regression. */
declare(strict_types=1);
require __DIR__.'/schema-candidate.php';
require __DIR__.'/mysql-community-budget.php';
$dsn=(string)getenv('BA_TEST_DB_DSN');
if (!preg_match('/(?:^|;)dbname=ba_identity_ci(?:;|$)/',$dsn)) {
    throw new RuntimeException('Safety stop: disposable ba_identity_ci only');
}
$db=new PDO($dsn,getenv('BA_TEST_DB_USER')?:'ba_ci',getenv('BA_TEST_DB_PASS')?:'');
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$prefix='ba_budget_ci_';
$sql=BadAround_Identity_Schema_Candidate::statements($prefix)['ba_identity_community_budget'];
$db->exec($sql);
$table=$prefix.'ba_identity_community_budget';
$db->exec("DELETE FROM `{$table}`");
$key=str_repeat('Z',32);
$budget=new BadAround_Identity_Mysql_Community_Budget($db,$table,$key);
$session=str_repeat('a',64);
$now=1762156800;
function budget_assert(bool $ok,string $label):void {
    if (!$ok) { throw new RuntimeException('FAIL '.$label); }
    echo 'PASS '.$label.PHP_EOL;
}
for ($i=0;$i<6;$i++) {
    budget_assert($budget->consume($session,'comment',$now),'comment budget slot '.($i+1));
}
budget_assert(!$budget->consume($session,'comment',$now),'seventh comment refused');
budget_assert($budget->consume($session,'comment',$now+901),'quota resets next window');
budget_assert($budget->consume(str_repeat('b',64),'comment',$now),'separate session independent');
for ($i=0;$i<3;$i++) {
    budget_assert($budget->consume($session,'revision',$now),'revision budget slot '.($i+1));
}
budget_assert(!$budget->consume($session,'revision',$now),'fourth revision refused');
budget_assert(!$budget->consume('invalid','comment',$now),'invalid token refused');
$rows=$db->query("SELECT bucket FROM `{$table}`")->fetchAll(PDO::FETCH_COLUMN);
budget_assert(count($rows)>=2 && !in_array($session,$rows,true),
    'database stores opaque digest rather than bearer session');
echo "ALL_MYSQL_COMMUNITY_BUDGET_TESTS_PASS\n";
