<?php
/**
 * BA-IDENTITY-01H — dormant WordPress community composition root.
 *
 * This deliberately does NOT connect to MySQL, read wp-config secrets, define
 * flags or register hooks on include. A reviewed production bootstrap must
 * provide an already connected PDO, dedicated key and verified site origin.
 * No retroactive owner grants; legacy contributions remain untouched.
 */
declare(strict_types=1);
require_once __DIR__.'/wordpress-community-rest-adapter.php';
require_once __DIR__.'/wordpress-authority.php';
require_once __DIR__.'/mysql-session-store.php';
require_once __DIR__.'/mysql-identity-active-status.php';
require_once __DIR__.'/mysql-wp-actor-binding.php';
require_once __DIR__.'/mysql-moderation-inbox.php';
require_once __DIR__.'/mysql-community-budget.php';

final class BadAround_Identity_Pdo_Community_Inbox implements BadAround_Identity_Community_Inbox_Writer {
    public function __construct(private BadAround_Identity_Moderation_Inbox $store) {}
    public function submit(string $id,string $kind,string $identity,int $resource,array $intent,int $now):bool {
        return $this->store->submit($id,$kind,$identity,$resource,$intent,$now);
    }
    public function decide(string $id,int $version,string $decision,string $moderator,int $now):bool {
        return $this->store->decide($id,$version,$decision,$moderator,$now);
    }
}

final class BadAround_Identity_WordPress_Composition {
    private static function derive_key(string $secret,string $purpose):string {
        return hash_hmac('sha256','BA-ID-01H:'.$purpose,$secret,true);
    }
    public static function assemble(
        PDO $db,string $wpPrefix,string $rootSecret,string $siteOrigin
    ): BadAround_Identity_Community_Rest_Adapter {
        if (!preg_match('/^[A-Za-z0-9_]{1,48}$/D',$wpPrefix)
            || strlen($rootSecret)<32
            || !BadAround_Identity_Csrf_Policy::origin_allowed($siteOrigin,$siteOrigin)) {
            throw new InvalidArgumentException('Invalid WordPress Identity integration configuration');
        }
        $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
        // Different HMAC/encryption domains must not share a digest key.
        $sessionKey=self::derive_key($rootSecret,'sessions');
        $csrfKey=self::derive_key($rootSecret,'csrf');
        $payloadKey=self::derive_key($rootSecret,'moderation-encryption');
        $budgetKey=self::derive_key($rootSecret,'community-budget');

        $sessions=new BadAround_Identity_Sessions(
            new BadAround_Identity_Mysql_Session_Store($db,$wpPrefix.'ba_identity_sessions'),
            $sessionKey
        );
        $status=new BadAround_Identity_Mysql_Active_Status($db,$wpPrefix.'ba_identities');
        $ownership=new BadAround_Identity_Mysql_Ownership_Store($db,$wpPrefix.'ba_identity_ownership');
        $wpBinding=new BadAround_Identity_Mysql_WP_Actor_Binding($db,$wpPrefix.'ba_identity_wp_actors');
        $authority=new BadAround_Identity_WordPress_Authority(
            $sessions,$status,$ownership,$wpBinding
        );
        $inbox=new BadAround_Identity_Pdo_Community_Inbox(
            new BadAround_Identity_Moderation_Inbox(
                $db,$wpPrefix.'ba_identity_moderation',$payloadKey
            )
        );
        $editGuard=new BadAround_Identity_Edit_Guard(
            $sessions,$csrfKey,$siteOrigin,$status
        );
        $gateway=new BadAround_Identity_Community_Gateway(
            $authority,$inbox,$editGuard,$csrfKey,$siteOrigin
        );
        $budget=new BadAround_Identity_Mysql_Community_Budget(
            $db,$wpPrefix.'ba_identity_community_budget',$budgetKey
        );
        return new BadAround_Identity_Community_Rest_Adapter($gateway,$budget);
    }
}
