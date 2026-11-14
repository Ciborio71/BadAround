<?php
/**
 * BA-IDENTITY-01R: production-private bootstrap. No hooks on include.
 * Only an explicit server configuration may activate this module.
 * DO NOT expose public routes before privacy and mail/browser acceptance.
 */
declare(strict_types=1);
require_once __DIR__.'/wordpress-privacy-notice.php';
require_once __DIR__.'/wordpress-private-access.php';

final class BadAround_Identity_WordPress_Private_Bootstrap {
    public static function enabled():bool {
        return defined('BADAROUND_IDENTITY_PRIVATE_QA_ENABLED')
            && BADAROUND_IDENTITY_PRIVATE_QA_ENABLED===true;
    }

    /**
     * Fail closed, with no emitted email, cookies, or user content at bootstrap.
     * Caller supplies already trusted PDO and the canonical WP prefix.
     */
    public static function install(PDO $db,string $prefix):bool {
        if (!self::enabled() || !BadAround_Identity_Private_Access::authorized()) {return false;}
        if (!defined('BADAROUND_IDENTITY_ROOT_SECRET')
            || !is_string(BADAROUND_IDENTITY_ROOT_SECRET)
            || strlen(BADAROUND_IDENTITY_ROOT_SECRET)<48
            || !preg_match('/^[A-Za-z0-9_]{1,48}$/D',$prefix)
            || !function_exists('home_url')
            || !function_exists('add_action')
            || !class_exists('BadAround_Transactional_Mailer')
            || !defined('BADAROUND_TURBOSMTP_CONSUMER_KEY')
            || !defined('BADAROUND_TURBOSMTP_CONSUMER_SECRET')
            || !defined('BADAROUND_TURBOSMTP_FROM_EMAIL')) {return false;}
        $privacy=BadAround_Identity_WordPress_Privacy_Notice::runtime_version();
        if ($privacy===null) {return false;}
        try {
            $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
            $names=['ba_identity_challenges','ba_identity_pending','ba_identities',
                'ba_identity_sessions','ba_identity_rate_limits','ba_identity_ownership',
                'ba_identity_recovery','ba_identity_moderation',
                'ba_identity_wp_actors','ba_identity_community_budget'];
            $check=$db->prepare("SELECT COUNT(*) FROM information_schema.TABLES
                WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table AND ENGINE='InnoDB'");
            foreach($names as $name) {
                $check->execute([':table'=>$prefix.$name]);
                if ((int)$check->fetchColumn()!==1) {return false;}
            }
            $origin=rtrim((string)home_url('/'),'/');
            if ($origin!=='https://badaround.it') {return false;}
            require_once __DIR__.'/wordpress-registration-composition.php';
            require_once __DIR__.'/wordpress-login-composition.php';
            require_once __DIR__.'/wordpress-login-rest.php';
            require_once __DIR__.'/wordpress-session-rest.php';
            require_once __DIR__.'/wordpress-identity-portal.php';
            require_once __DIR__.'/wordpress-activity-rest.php';
            require_once __DIR__.'/wordpress-comment-form.php';
            require_once __DIR__.'/wordpress-composition.php';
            $key=BADAROUND_IDENTITY_ROOT_SECRET;
            // QA traffic may ONLY deliver OTP to expressly allowlisted
            // synthetic inboxes. Never invoke the production mailer directly.
            require_once __DIR__.'/wordpress-qa-mail-fence.php';
            if (!BadAround_Identity_QA_Mail_Fence::configured())return false;
            $mailer=new BadAround_Identity_QA_Mail_Fence(new BadAround_Transactional_Mailer());
            // Private QA is independently gated per controller: any absent
            // flag leaves that REST component unregistered.
            $registration=BadAround_Identity_WordPress_Registration_Composition::assemble(
                $db,$prefix,$key,$privacy,$mailer,$origin
            );
            $challengeStore=new BadAround_Identity_Mysql_Challenge_Store(
                $db,$prefix.'ba_identity_challenges'
            );
            $quota=new BadAround_Identity_Issuance_Quota_Adapter(
                new BadAround_Identity_Mysql_Rate_Limiter(
                    $db,$prefix.'ba_identity_rate_limits'
                ),hash_hmac('sha256','BA-ID-01R:login-quota',$key,true)
            );
            $issuer=new BadAround_Identity_Otp_Issuer(
                $key,$quota,$challengeStore,new BadAround_Identity_Transport_Adapter($mailer)
            );
            $sessions=new BadAround_Identity_Sessions(
                new BadAround_Identity_Mysql_Session_Store(
                    $db,$prefix.'ba_identity_sessions'
                ),hash_hmac('sha256','BA-ID-01H:sessions',$key,true)
            );
            require_once __DIR__.'/wordpress-access-rest.php';
            $accessQuota=new BadAround_Identity_Issuance_Quota_Adapter(
                new BadAround_Identity_Mysql_Rate_Limiter($db,$prefix.'ba_identity_rate_limits'),
                hash_hmac('sha256','BA-ID-ACCESS:quota',$key,true));
            $accessService=new BadAround_Identity_Unified_Access($db,$prefix,$key,$privacy,$accessQuota,
                new BadAround_Identity_Transport_Adapter($mailer),$sessions);
            $accessApi=new BadAround_Identity_WordPress_Access_REST($accessService,$sessions,
                new BadAround_Identity_WordPress_Session_Cookie(new BadAround_Identity_Native_Cookie_Writer()),$origin);
            $csrf=hash_hmac('sha256','BA-ID-01H:csrf',$key,true);
            $login=new BadAround_Identity_WordPress_Login_REST(
                $issuer,
                BadAround_Identity_WordPress_Login_Composition::assemble($db,$prefix,$key),
                $sessions,new BadAround_Identity_WordPress_Session_Cookie(
                    new BadAround_Identity_Native_Cookie_Writer()
                ),$csrf,$origin
            );
            $community=BadAround_Identity_WordPress_Composition::assemble(
                $db,$prefix,$key,$origin
            );
            $review=new BadAround_Identity_Community_Review($db,$prefix,$key);
            $sessionApi=new BadAround_Identity_WordPress_Session_REST(
                $sessions,new BadAround_Identity_Mysql_Active_Status($db,$prefix.'ba_identities'),
                new BadAround_Identity_WordPress_Session_Cookie(new BadAround_Identity_Native_Cookie_Writer()),
                $csrf,$origin
            );
            $activityApi=new BadAround_Identity_WordPress_Activity_REST(
                new BadAround_Identity_Private_Activity(
                    $db,$prefix.'ba_identity_moderation',$sessions,
                    new BadAround_Identity_Mysql_Active_Status($db,$prefix.'ba_identities'),
                    $csrf,$origin
                ),[$review,'my_reports']
            );
            // Private report QA cannot be registered unless the entire
            // passwordless runtime, IP fence, notice and dedicated schema
            // pass independently. When disabled, not even the composer loads.
            $qaReport=null;
            if (defined('BADAROUND_IDENTITY_QA_REPORTS_ENABLED')
                && BADAROUND_IDENTITY_QA_REPORTS_ENABLED===true) {
                require_once __DIR__.'/wordpress-qa-report-bootstrap.php';
                $qaReport=BadAround_Identity_WordPress_QA_Report_Bootstrap::prepare($db,$prefix);
                if ($qaReport===null) return false;
            }
            // Assemble the complete graph before registering ANY hook:
            // partial initialization must never expose a functioning route.
            // Individual REST controllers still require their own flags.
            if ($qaReport!==null
                && !BadAround_Identity_WordPress_QA_Report_Bootstrap::register($qaReport))
                return false;
            $accessApi->register_hooks();
            $registration->register_hooks();
            $login->register_hooks();
            $sessionApi->register_hooks();
            $activityApi->register_hooks();
            $community->register_hooks();
            $review->register_hooks();
            BadAround_Identity_WordPress_Portal::bind_session_status([$sessionApi,'authenticated']);
            BadAround_Identity_WordPress_Portal::register_hooks();
            BadAround_Identity_WordPress_Comment_Form::register_hooks();
            // Community authentication has its own cookie and CSRF checks.
            // Its private QA requests must not require an admin REST nonce.
            // Keep the public maintenance gate in place everywhere else.
            if (function_exists('add_filter'))add_filter('rest_pre_dispatch',
                [self::class,'private_qa_maintenance_request'],6,3);
            return true;
        } catch(Throwable $error) {
            // Never echo database errors or private key material.
            return false;
        }
    }

    public static function private_qa_maintenance_request($result,$server,$request) {
        if (!function_exists('is_wp_error') || !is_wp_error($result)
            || $result->get_error_codes()!==['badaround_maintenance']
            || ($result->get_error_data()['status']??null)!==503
            || !is_object($request) || !method_exists($request,'get_route')
            || !method_exists($request,'get_method')
            || !defined('BADAROUND_IDENTITY_RUNTIME_INTEGRATION_ENABLED')
            || BADAROUND_IDENTITY_RUNTIME_INTEGRATION_ENABLED!==true
            || !BadAround_Identity_Private_Access::authorized()
            || !BadAround_Identity_WordPress_Privacy_Notice::qa_mode()
            || BadAround_Identity_WordPress_Privacy_Notice::runtime_version()===null)return $result;
        $routes=[
            '/badaround/v1/identity/register/start','/badaround/v1/identity/register/verify',
            '/badaround/v1/identity/login/start','/badaround/v1/identity/login/verify',
            '/badaround/v1/identity/access/start','/badaround/v1/identity/access/verify','/badaround/v1/identity/access/check',
            '/badaround/v1/identity/session','/badaround/v1/identity/logout',
            '/badaround/v1/identity/my-activity','/badaround/v1/identity/my-reports','/badaround/v1/community-comments',
            '/badaround/v1/community-revisions','/badaround-identity-qa/v1/reports',
        ];
        $route=$request->get_route();$method=$request->get_method();
        if ($method==='POST' && in_array($route,$routes,true))return null;
        // Private media QA still runs the native origin/marker/capability
        // checks. Only the maintenance response is cleared, never a denial.
        if (!defined('BADAROUND_IDENTITY_QA_REPORTS_ENABLED')
            || BADAROUND_IDENTITY_QA_REPORTS_ENABLED!==true
            || !method_exists($request,'get_header')
            || $request->get_header('x-badaround-media')!=='badaround-report-media/v1')return $result;
        $uuid='[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';
        $base='/badaround/v1/report-media-sessions';
        $allowed=($route===$base && $method==='POST')
            || ($method==='GET' && preg_match('~^'.$base.'/'.$uuid.'$~D',$route))
            || ($method==='POST' && preg_match('~^'.$base.'/'.$uuid.'/items$~D',$route))
            || (in_array($method,['GET','DELETE'],true)
                && preg_match('~^'.$base.'/'.$uuid.'/items/'.$uuid.'$~D',$route));
        return $allowed?null:$result;
    }
}
