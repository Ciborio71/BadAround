<?php
/** Isolated, disposable MySQL QA readiness tests; no real site or PII. */
declare(strict_types=1);
require_once __DIR__.'/../wordpress/plugins/badaround-core/identity-dormant/wordpress-qa-readiness.php';
$dsn=(string)getenv('BA_TEST_DB_DSN');
if (!str_contains($dsn,'dbname=ba_identity_ci'))
    throw new RuntimeException('Safety stop: disposable CI DB required');
$db=new PDO($dsn,(string)getenv('BA_TEST_DB_USER'),(string)getenv('BA_TEST_DB_PASS'));
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
function qaReadyCheck(bool $ok,string $name):void {
    if(!$ok)throw new RuntimeException('FAIL '.$name);
    echo 'PASS '.$name.PHP_EOL;
}
function home_url(string $path='/'):string{return 'https://badaround.it/';}
$prefix='ci_readiness_';
$table=BadAround_Identity_Pre_Intake_Schema::table($prefix);
$db->exec("DROP TABLE IF EXISTS `{$table}`");
$initial=BadAround_Identity_QA_Readiness::inspect($db,$prefix);
qaReadyCheck($initial['ready']===false,'default flags never show ready');
qaReadyCheck($initial['checks']['pre_intake_schema_valid']===false,
    'missing private outbox schema reported without mutations');
qaReadyCheck($initial['checks']['approved_privacy_notice']===false,
    'missing approved privacy notice blocks readiness');
qaReadyCheck($initial['checks']['qa_mail_recipients_verified']===false,
    'missing QA-only recipient allowlist blocks readiness');
qaReadyCheck($initial['checks']['registration_enabled']===false,
    'registration must be explicitly enabled before QA can be ready');
qaReadyCheck($initial['checks']['login_enabled']===false,
    'login must be explicitly enabled before QA can be ready');
qaReadyCheck(count($initial['checks'])===12,
    'readiness returns only twelve non-sensitive boolean facts');
$db->exec(BadAround_Identity_Pre_Intake_Schema::create_sql($prefix));
try {
    $partial=BadAround_Identity_QA_Readiness::inspect($db,$prefix);
    qaReadyCheck($partial['checks']['pre_intake_schema_valid']===true,
        'valid schema recognised by metadata only');
    qaReadyCheck($partial['ready']===false,
        'database readiness does not bypass missing privacy or flags');
    qaReadyCheck(array_keys($partial)===['ready','checks'],
        'readiness response excludes DB data and secrets');
    qaReadyCheck($partial['checks']['network_allowlisted']===false,
        'unknown visitor is denied by private network fence');
} finally {
    $db->exec("DROP TABLE IF EXISTS `{$table}`");
}
echo 'ALL_QA_READINESS_MYSQL_TESTS_PASS'.PHP_EOL;
