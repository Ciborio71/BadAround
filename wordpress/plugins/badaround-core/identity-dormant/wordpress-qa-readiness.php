<?php
/**
 * BA-IDENTITY-WIZARD-18: read-only private QA readiness evaluation.
 *
 * No hooks, routes, mail, cookies, mutations or secrets are exposed.
 * Can run with flags OFF to explain missing prerequisites without enabling
 * any Identity service. No user identifiers or SQL content in output.
 */
declare(strict_types=1);
require_once __DIR__.'/pre-intake-schema-candidate.php';
require_once __DIR__.'/wordpress-private-access.php';
require_once __DIR__.'/wordpress-privacy-notice.php';
require_once __DIR__.'/wordpress-qa-mail-fence.php';

final class BadAround_Identity_QA_Readiness {
    public static function inspect(PDO $db,string $prefix):array {
        $check=[
            'site_canonical'=>false,
            'runtime_enabled'=>false,
            'private_qa_enabled'=>false,
            'report_qa_enabled'=>false,
            'network_allowlisted'=>false,
            'strong_secret_configured'=>false,
            'mail_configuration_present'=>false,
            'qa_mail_recipients_verified'=>false,
            'approved_privacy_notice'=>false,
            'pre_intake_schema_valid'=>false,
        ];
        try {
            $check['site_canonical']=function_exists('home_url')
                && rtrim((string)home_url('/'),'/')==='https://badaround.it';
            $check['runtime_enabled']=defined('BADAROUND_IDENTITY_RUNTIME_INTEGRATION_ENABLED')
                && BADAROUND_IDENTITY_RUNTIME_INTEGRATION_ENABLED===true;
            $check['private_qa_enabled']=defined('BADAROUND_IDENTITY_PRIVATE_QA_ENABLED')
                && BADAROUND_IDENTITY_PRIVATE_QA_ENABLED===true;
            $check['report_qa_enabled']=defined('BADAROUND_IDENTITY_QA_REPORTS_ENABLED')
                && BADAROUND_IDENTITY_QA_REPORTS_ENABLED===true;
            $check['network_allowlisted']=BadAround_Identity_Private_Access::authorized();
            $check['strong_secret_configured']=defined('BADAROUND_IDENTITY_ROOT_SECRET')
                && is_string(BADAROUND_IDENTITY_ROOT_SECRET)
                && strlen(BADAROUND_IDENTITY_ROOT_SECRET)>=48;
            $check['mail_configuration_present']=class_exists('BadAround_Transactional_Mailer')
                && defined('BADAROUND_TURBOSMTP_CONSUMER_KEY')
                && defined('BADAROUND_TURBOSMTP_FROM_EMAIL');
            $check['qa_mail_recipients_verified']=
                BadAround_Identity_QA_Mail_Fence::configured();
            $check['approved_privacy_notice']=
                BadAround_Identity_WordPress_Privacy_Notice::verified_version()!==null;
            $check['pre_intake_schema_valid']=
                BadAround_Identity_Pre_Intake_Schema::inspect($db,$prefix);
        } catch (Throwable $error) {
            // Must be safe even when the database is unavailable. Never expose
            // private configuration, connection details or personal data.
            return ['ready'=>false,'checks'=>$check];
        }
        return ['ready'=>!in_array(false,$check,true),'checks'=>$check];
    }
}
