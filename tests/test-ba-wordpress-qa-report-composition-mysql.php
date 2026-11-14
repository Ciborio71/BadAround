<?php
/** Disposable QA composition test. No production SQL or report writes. */
declare(strict_types=1);
require_once __DIR__.'/../wordpress/plugins/badaround-core/identity-dormant/wordpress-qa-report-composition.php';
require_once __DIR__.'/../wordpress/plugins/badaround-core/identity-dormant/wordpress-qa-report-bootstrap.php';

$dsn=(string)getenv('BA_TEST_DB_DSN');
if (!str_contains($dsn,'dbname=ba_identity_ci'))
    throw new RuntimeException('Safety stop: CI database only');
$db=new PDO($dsn,(string)getenv('BA_TEST_DB_USER'),(string)getenv('BA_TEST_DB_PASS'));
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);

function qa_comp_check(bool $yes,string $label):void {
    if(!$yes)throw new RuntimeException('FAIL '.$label);
    echo 'PASS '.$label.PHP_EOL;
}
function home_url(string $path='/'):string{return 'https://badaround.it/';}
class BadAround_Report_Version_Registry {
    public static function prepare(array $raw):array{return $raw;}
}
class BadAround_Report_Repository {
    public function acquire_native_lock(string $id,int $timeout):bool{return true;}
    public function release_native_lock(string $id):void{}
}
class BadAround_Native_Report_Golden_Path_Service {
    public function submit(array $raw):array {throw new RuntimeException('Never submit real content in CI smoke');}
}
class BadAround_Native_Media_Report_Adapter {
    public function submit(array $raw,string $cap):array {throw new RuntimeException('Never submit media in CI smoke');}
}
$qaNoticeReviewed=false;
function get_post(int $id):object {
    global $qaNoticeReviewed;
    return (object)[
        'ID'=>3,'post_type'=>'page','post_status'=>'publish',
        'post_name'=>'privacy-policy',
        'post_modified_gmt'=>BadAround_Identity_WordPress_Privacy_Notice::EXPECTED_MODIFIED_GMT,
        'post_content'=>$qaNoticeReviewed
            ? 'Synthetic reviewed CI fixture, never real consent.'
            : 'Bozza pre-pubblicazione da sottoporre a revisione',
    ];
}
$qaRegisteredHooks=[];
function add_action(string $hook,$callback):void {
    global $qaRegisteredHooks;
    $qaRegisteredHooks[]=$hook;
}
final class QAComposerReceipt implements BadAround_Identity_Intake_Receipt {
    public function is_new_canonical_report(int $report,int $event):bool{return false;}
}
$prefix='qa_composer_';
$table=BadAround_Identity_Pre_Intake_Schema::table($prefix);
$db->exec("DROP TABLE IF EXISTS `{$table}`");
$proof=new QAComposerReceipt();
$repository=new BadAround_Report_Repository();
qa_comp_check(BadAround_Identity_WordPress_QA_Report_Composition::assemble(
    $db,$prefix,$repository,$proof
)===null,'disabled flags prohibit composition');
define('BADAROUND_IDENTITY_PRIVATE_QA_ENABLED',true);
define('BADAROUND_IDENTITY_QA_REPORTS_ENABLED',true);
define('BADAROUND_IDENTITY_QA_IP_ALLOWLIST',['127.0.0.1']);
define('BADAROUND_IDENTITY_ROOT_SECRET',str_repeat('q',64));
$_SERVER['REMOTE_ADDR']='127.0.0.1';
qa_comp_check(
    BadAround_Identity_WordPress_QA_Report_Bootstrap::prepare($db,'qa_composer_')===null,
    'private report bootstrap requires independent full runtime flag'
);
define('BADAROUND_IDENTITY_RUNTIME_INTEGRATION_ENABLED',true);
qa_comp_check(
    BadAround_Identity_WordPress_QA_Report_Bootstrap::prepare($db,'qa_composer_')===null,
    'unreviewed notice blocks report bootstrap'
);

qa_comp_check(BadAround_Identity_WordPress_QA_Report_Composition::assemble(
    $db,$prefix,$repository,$proof
)===null,'missing pre-intake table never auto-created');
$db->exec(BadAround_Identity_Pre_Intake_Schema::create_sql($prefix));
try {
    qa_comp_check(
        BadAround_Identity_WordPress_QA_Report_Bootstrap::prepare($db,$prefix)===null,
        'pre-intake schema alone never bypasses privacy gate'
    );
    $qaNoticeReviewed=true;
    $qaController=BadAround_Identity_WordPress_QA_Report_Bootstrap::prepare($db,$prefix);
    qa_comp_check($qaController instanceof BadAround_Identity_QA_Report_REST,
        'private bootstrap assembles controller with all flags and synthetic notice');
    qa_comp_check($qaRegisteredHooks===[], 'controller composition alone does not register routes');
    qa_comp_check(
        BadAround_Identity_WordPress_QA_Report_Bootstrap::register($qaController),
        'private bootstrap registers only QA REST hook'
    );
    qa_comp_check($qaRegisteredHooks===['rest_api_init'],
        'only REST initialization registered for private report QA');

    $controller=BadAround_Identity_WordPress_QA_Report_Composition::assemble(
        $db,$prefix,$repository,$proof
    );
    qa_comp_check($controller instanceof BadAround_Identity_QA_Report_REST,
        'protected production-private QA dependencies composed');
    qa_comp_check(
        BadAround_Identity_WordPress_QA_Report_Composition::assemble(
            $db,$prefix,$repository
        ) instanceof BadAround_Identity_QA_Report_REST,
        'default persistent canonical proof composes without client-supplied receipt'
    );
    $_SERVER['REMOTE_ADDR']='192.0.2.4';
    qa_comp_check(
        !BadAround_Identity_WordPress_QA_Report_Bootstrap::register($qaController),
        'registration rechecks allowlisted peer'
    );
    qa_comp_check(BadAround_Identity_WordPress_QA_Report_Composition::assemble(
        $db,$prefix,$repository,$proof
    )===null,'non-allowlisted peer denied even with schema');
} finally {
    $db->exec("DROP TABLE IF EXISTS `{$table}`");
}
echo 'ALL_WORDPRESS_QA_REPORT_COMPOSITION_MYSQL_TESTS_PASS'.PHP_EOL;
