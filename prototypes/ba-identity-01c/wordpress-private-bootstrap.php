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
            || !defined('BADAROUND_TURBOSMTP_FROM_EMAIL')) {return false;}
        $privacy=BadAround_Identity_WordPress_Privacy_Notice::verified_version();
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
            $mailer=new BadAround_Transactional_Mailer();
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
                )
            );
            // Assemble the complete graph before registering ANY hook:
            // partial initialization must never expose a functioning route.
            // Individual REST controllers still require their own flags.
            $registration->register_hooks();
            $login->register_hooks();
            $sessionApi->register_hooks();
            $activityApi->register_hooks();
            $community->register_hooks();
            BadAround_Identity_WordPress_Portal::register_hooks();
            BadAround_Identity_WordPress_Comment_Form::register_hooks();
            return true;
        } catch(Throwable $error) {
            // Never echo database errors or private key material.
            return false;
        }
    }
}
