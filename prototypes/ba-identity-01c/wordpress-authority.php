<?php
/**
 * Dormant WordPress authority adapter for BA-IDENTITY-01E.
 * NO hook registration; NOT included from badaround-core.php.
 * Caller must supply persistent session, active identity and ownership stores.
 * Capabilities must come from current_user_can(), never request JSON.
 */
declare(strict_types=1);
require_once __DIR__.'/community-gateway.php';
require_once __DIR__.'/mysql-ownership-store.php';

/**
 * Must be backed by a server-verified WP-account-to-BadAround-identity mapping.
 * Never infer equality from email address or request-submitted identifiers.
 */
interface BadAround_Identity_WP_Actor_Binding {
    public function matches_current_user(int $wpUserId,string $identityId): bool;
}

final class BadAround_Identity_WordPress_Authority implements BadAround_Identity_Community_Authority {
    public function __construct(
        private BadAround_Identity_Sessions $sessions,
        private BadAround_Identity_Active_Status $status,
        private BadAround_Identity_Mysql_Ownership_Store $ownership,
        private BadAround_Identity_WP_Actor_Binding $actorBinding
    ) {}

    public function active_identity(?string $sessionToken,int $now): ?string {
        if ($sessionToken===null || $now<=0) { return null; }
        try {
            $id=$this->sessions->identity_for($sessionToken,$now);
            return $id!==null && $this->status->is_active_verified($id) ? $id : null;
        } catch (Throwable $error) { return null; }
    }

    public function canonical_event_is_published(int $eventId): bool {
        if ($eventId<=0 || !function_exists('get_post') ||
            !function_exists('get_post_meta')) { return false; }
        try {
            $post=get_post($eventId);
            if (!is_object($post) || ($post->post_type ?? null)!=='ba_evento'
                || ($post->post_status ?? null)!=='publish') { return false; }
            return (string)get_post_meta($eventId,'_ba_moderation_status',true)==='published';
        } catch (Throwable $error) { return false; }
    }

    public function verified_owner_record(int $reportId): ?array {
        if ($reportId<=0) { return null; }
        try {
            $owner=$this->ownership->find_for_resource('report',$reportId);
            if (!is_array($owner) || ($owner['resource_kind'] ?? null)!=='report'
                || ($owner['resource_id'] ?? null)!==$reportId
                || ($owner['relation'] ?? null)!=='creator'
                || ($owner['revoked_at'] ?? null)!==null
                || !is_string($owner['identity_id'] ?? null)
                || !$this->status->is_active_verified($owner['identity_id'])) {
                return null;
            }
            return $owner;
        } catch (Throwable $error) { return null; }
    }

    public function may_moderate(string $moderatorIdentityId): bool {
        if (!function_exists('current_user_can') ||
            !function_exists('is_user_logged_in') || !is_user_logged_in()
            || !function_exists('get_current_user_id')) {
            return false;
        }
        try {
            $wpUserId=(int)get_current_user_id();
            return $wpUserId>0
                && $this->status->is_active_verified($moderatorIdentityId)
                && $this->actorBinding->matches_current_user($wpUserId,$moderatorIdentityId)
                && current_user_can('ba_moderate_contributions');
        } catch (Throwable $error) { return false; }
    }
}
