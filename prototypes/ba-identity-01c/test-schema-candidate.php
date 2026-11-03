<?php
/** BA-IDENTITY-01G. Disposable MySQL only. NEVER run on production. */
declare(strict_types=1);
require __DIR__.'/schema-candidate.php';
require __DIR__.'/mysql-wp-actor-binding.php';
$dsn=(string)getenv('BA_TEST_DB_DSN');
if (!preg_match('/(?:^|;)dbname=ba_identity_ci(?:;|$)/',$dsn)) {
    throw new RuntimeException('Safety stop: only disposable ba_identity_ci database');
}
$db=new PDO($dsn,getenv('BA_TEST_DB_USER')?:'ba_ci',getenv('BA_TEST_DB_PASS')?:'');
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
function migration_assert(bool $ok,string $name):void {
    if (!$ok) { throw new RuntimeException('FAIL '.$name); }
    echo 'PASS '.$name.PHP_EOL;
}
$prefix='ba_migration_ci_';
$statements=BadAround_Identity_Schema_Candidate::statements($prefix);
migration_assert(count($statements)===9,'nine additive identity tables defined');
foreach ($statements as $suffix=>$sql) {
    migration_assert(str_starts_with($sql,'CREATE TABLE IF NOT EXISTS'),
        'additive statement for '.$suffix);
    $db->exec($sql);
}
foreach ($statements as $sql) { $db->exec($sql); }
migration_assert(count($statements)===9,'second migration pass is idempotent');
$statement=$db->prepare("SELECT COUNT(*) FROM information_schema.TABLES
 WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME LIKE :prefix");
$statement->execute([':prefix'=>$prefix.'%']);
migration_assert((int)$statement->fetchColumn()===9,'all nine prefixed tables exist');
$actor=new BadAround_Identity_Mysql_WP_Actor_Binding($db,
    $prefix.'ba_identity_wp_actors');
$now=1762156800;
migration_assert($actor->grant_verified(42,'identity_12345678',$now),
    'actor adapter works on candidate DDL');
migration_assert($actor->matches_current_user(42,'identity_12345678'),
    'verified WP actor lookup works');
migration_assert(!$actor->grant_verified(43,'identity_12345678',$now),
    'unique identity binding prevents takeover');
migration_assert($actor->revoke(42,'identity_12345678',0,$now+1),
    'revocation works on candidate DDL');
migration_assert(!$actor->matches_current_user(42,'identity_12345678'),
    'revoked actor denied');
$bad=false;
try { BadAround_Identity_Schema_Candidate::statements('bad-name;DROP'); }
catch (InvalidArgumentException $e) { $bad=true; }
migration_assert($bad,'unsafe prefix rejected');
echo "ALL_IDENTITY_SCHEMA_CANDIDATE_TESTS_PASS\n";
