<?php
/**
 * BA-IDENTITY-WIZARD-17: QA reports private bootstrap seam.
 *
 * Never registers hooks on inclusion. Requires all runtime AND individual QA
 * flags, IP fence and reviewed privacy binding. No automatic flag activation.
 */
declare(strict_types=1);

final class BadAround_Identity_WordPress_QA_Report_Bootstrap {
    public static function prepare(PDO $db,string $prefix):?object {
        if (!defined('BADAROUND_IDENTITY_RUNTIME_INTEGRATION_ENABLED')
            || BADAROUND_IDENTITY_RUNTIME_INTEGRATION_ENABLED!==true
            || !defined('BADAROUND_IDENTITY_PRIVATE_QA_ENABLED')
            || BADAROUND_IDENTITY_PRIVATE_QA_ENABLED!==true
            || !defined('BADAROUND_IDENTITY_QA_REPORTS_ENABLED')
            || BADAROUND_IDENTITY_QA_REPORTS_ENABLED!==true
            || !defined('BADAROUND_IDENTITY_ROOT_SECRET')
            || !is_string(BADAROUND_IDENTITY_ROOT_SECRET)
            || strlen(BADAROUND_IDENTITY_ROOT_SECRET)<48
            || !class_exists('BadAround_Identity_Private_Access')
            || !BadAround_Identity_Private_Access::authorized()
            || !class_exists('BadAround_Identity_WordPress_Privacy_Notice')
            || BadAround_Identity_WordPress_Privacy_Notice::verified_version()===null
            || !class_exists('BadAround_Report_Repository')
            || !function_exists('home_url')
            || rtrim((string)home_url('/'),'/')!=='https://badaround.it') {
            return null;
        }
        try {
            require_once __DIR__.'/wordpress-qa-report-composition.php';
            return BadAround_Identity_WordPress_QA_Report_Composition::assemble(
                $db,$prefix,new BadAround_Report_Repository()
            );
        } catch(Throwable $error) {
            return null;
        }
    }

    public static function register(?object $controller):bool {
        if ($controller===null || !method_exists($controller,'register_hooks')
            || !defined('BADAROUND_IDENTITY_QA_REPORTS_ENABLED')
            || BADAROUND_IDENTITY_QA_REPORTS_ENABLED!==true
            || !BadAround_Identity_Private_Access::authorized())return false;
        try {
            return $controller->register_hooks()===true;
        } catch(Throwable $error) {
            return false;
        }
    }
}
