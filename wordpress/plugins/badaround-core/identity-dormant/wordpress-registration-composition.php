<?php
/**
 * BA-IDENTITY-01Q — dormant WordPress registration composition.
 * Does not read wp-config, register routes or emit emails upon inclusion.
 * Caller must supply independently provisioned root key, trusted PDO, actual
 * privacy notice version and canonical transactional mailer.
 */
declare(strict_types=1);
require_once __DIR__.'/wordpress-registration-rest.php';
require_once __DIR__.'/mysql-enrollment-store.php';
require_once __DIR__.'/mysql-challenge-store.php';
require_once __DIR__.'/otp-adapters.php';

final class BadAround_Identity_WordPress_Registration_Composition {
    public static function assemble(
        PDO $db,string $prefix,string $rootKey,string $privacyNoticeVersion,
        object $mailer,string $siteOrigin
    ): BadAround_Identity_WordPress_Registration_REST {
        if (!preg_match('/^[A-Za-z0-9_]{1,48}$/D',$prefix)
            || strlen($rootKey)<32
            || !preg_match('/^[A-Za-z0-9._-]{1,64}$/D',$privacyNoticeVersion)
            || !BadAround_Identity_Csrf_Policy::origin_allowed($siteOrigin,$siteOrigin)) {
            throw new InvalidArgumentException('Invalid trusted enrollment configuration');
        }
        $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
        $challenges=new BadAround_Identity_Mysql_Challenge_Store(
            $db,$prefix.'ba_identity_challenges'
        );
        $enrollmentKey=BadAround_Identity_Purpose_Keys::for_purpose(
            'enrollment',$rootKey
        );
        $protectedProfileKey=hash_hmac(
            'sha256','BA-ID-01Q:enrollment-encryption',$rootKey,true
        );
        $pending=new BadAround_Identity_Mysql_Enrollment_Store(
            $db,$prefix.'ba_identity_pending',$prefix.'ba_identities',
            $protectedProfileKey,$prefix.'ba_identity_challenges'
        );
        $enrollment=new BadAround_Identity_Enrollment(
            $pending,$challenges,
            new BadAround_Identity_Challenge_Verifier($challenges,$enrollmentKey),
            $rootKey,$enrollmentKey
        );
        $quota=new BadAround_Identity_Issuance_Quota_Adapter(
            new BadAround_Identity_Mysql_Rate_Limiter(
                $db,$prefix.'ba_identity_rate_limits'
            ),hash_hmac('sha256','BA-ID-01Q:enrollment-quota',$rootKey,true)
        );
        $registration=new BadAround_Identity_Registration_Service(
            $enrollment,$quota,$challenges,
            new BadAround_Identity_Transport_Adapter($mailer),
            $rootKey,$privacyNoticeVersion
        );
        return new BadAround_Identity_WordPress_Registration_REST(
            $registration,$siteOrigin
        );
    }
}
