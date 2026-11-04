<?php
/**
 * Dormant moderation-intent workflow. Does NOT modify public reports/events.
 * Private proposals must be persisted and approved through the existing
 * moderation and Publication Service only after authorization.
 */
declare(strict_types=1);
require_once __DIR__.'/community-policy.php';

final class BadAround_Identity_Revision_Request_Policy {
    public static function propose(
        string $identityId, int $reportId, string $description, string $reason,
        bool $verifiedOwnership, string $serverLoadedSnapshotDigest, int $now
    ): array {
        if (!preg_match('/^[a-f0-9]{64}$/D',$serverLoadedSnapshotDigest) || $now <= 0) {
            throw new InvalidArgumentException('Invalid server snapshot');
        }
        $intent=BadAround_Identity_Community_Policy::revision_intent(
            $identityId,$reportId,$description,$reason,$verifiedOwnership
        );
        $intent['original_snapshot_digest']=$serverLoadedSnapshotDigest;
        $intent['created_at']=$now;
        $intent['moderated_at']=null;
        $intent['moderator_id']=null;
        return $intent;
    }

    /**
     * Produces a moderation decision only. An adapter must enforce a durable,
     * CAS-protected transition and route approvals through Publication Service.
     */
    public static function decide(
        array $serverLoadedRequest, string $decision, string $moderatorId,
        string $currentSnapshotDigest, int $now
    ): array {
        if (($serverLoadedRequest['moderation_status'] ?? null)!=='pending'
            || !in_array($decision,['approved','rejected'],true)
            || !preg_match('/^[A-Za-z0-9_-]{8,128}$/D',$moderatorId)
            || !preg_match('/^[a-f0-9]{64}$/D',$currentSnapshotDigest)
            || !hash_equals((string)($serverLoadedRequest['original_snapshot_digest'] ?? ''),$currentSnapshotDigest)
            || $now <= (int)($serverLoadedRequest['created_at'] ?? 0)) {
            throw new DomainException('Invalid or stale moderation decision');
        }
        $next=$serverLoadedRequest;
        $next['moderation_status']=$decision;
        $next['moderator_id']=$moderatorId;
        $next['moderated_at']=$now;
        $next['public_mutation']=false;
        return $next;
    }
}
