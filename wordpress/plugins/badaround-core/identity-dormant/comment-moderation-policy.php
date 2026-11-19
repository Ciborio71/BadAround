<?php
/** Dormant moderation policy: comments never publish themselves. */
declare(strict_types=1);
require_once __DIR__.'/community-policy.php';
final class BadAround_Identity_Comment_Moderation_Policy {
    public static function propose(
        string $identityId, int $eventId, string $body, string $mode,
        ?array $trustedParent, int $now
    ): array {
        if ($now <= 0) { throw new InvalidArgumentException('Invalid time'); }
        $proposal=BadAround_Identity_Community_Policy::comment_intent(
            $identityId,$eventId,$body,$mode,$trustedParent
        );
        $proposal['created_at']=$now;
        $proposal['moderated_at']=null;
        $proposal['moderator_id']=null;
        $proposal['published_at']=null;
        return $proposal;
    }
    /** Caller MUST load comment and moderator from trusted server storage. */
    public static function decide(array $stored,string $decision,string $moderatorId,int $now): array {
        if (($stored['moderation_status'] ?? null)!=='pending'
            || !in_array($decision,['approved','rejected'],true)
            || !preg_match('/^[A-Za-z0-9_-]{8,128}$/D',$moderatorId)
            || !is_int($stored['event_id'] ?? null) || $stored['event_id'] <= 0
            || !is_int($stored['created_at'] ?? null)
            || $now <= $stored['created_at']) {
            throw new DomainException('Invalid moderation decision');
        }
        $next=$stored;
        $next['moderation_status']=$decision;
        $next['moderator_id']=$moderatorId;
        $next['moderated_at']=$now;
        $next['published_at']=$decision==='approved' ? $now : null;
        return $next;
    }
    public static function public_view(array $stored): ?array {
        if (($stored['moderation_status'] ?? null)!=='approved'
            || !is_int($stored['published_at'] ?? null) || $stored['published_at']<=0) {
            return null;
        }
        // Do not expose identity_id, private name/email, moderator or audit fields.
        return [
            'event_id'=>$stored['event_id'],
            'parent_id'=>$stored['parent_id'],
            'body'=>$stored['body'],
            'display_mode'=>$stored['display_mode'],
            'published_at'=>$stored['published_at'],
        ];
    }
}
