<?php
/**
 * BA-IDENTITY-01D — dormant application boundary for community changes.
 * WordPress must implement each trusted read and durable write adapter.
 * No live routes, login cookies, schema migrations or publication calls.
 */
declare(strict_types=1);
require_once __DIR__.'/edit-guard.php';
require_once __DIR__.'/comment-moderation-policy.php';
require_once __DIR__.'/revision-request-policy.php';
interface BadAround_Identity_Community_Authority {
    /** Server-only, never accept identity or ownership from request JSON. */
    public function active_identity(?string $sessionToken, int $now): ?string;
    public function canonical_event_is_published(int $eventId): bool;
    public function verified_owner_record(int $reportId): ?array;
    public function may_moderate(string $moderatorIdentityId): bool;
}
interface BadAround_Identity_Community_Inbox_Writer {
    public function submit(string $id,string $kind,string $identity,int $resource,array $intent,int $now): bool;
    public function decide(string $id,int $version,string $decision,string $moderator,int $now): bool;
}
final class BadAround_Identity_Community_Gateway {
    public function __construct(
        private BadAround_Identity_Community_Authority $authority,
        private BadAround_Identity_Community_Inbox_Writer $inbox,
        private BadAround_Identity_Edit_Guard $editGuard,
        private string $csrfKey,
        private string $siteOrigin
    ) {
        if (strlen($csrfKey)<32) { throw new InvalidArgumentException('Invalid key'); }
    }
    private function actor(?string $session,?string $csrf,?string $origin,int $now): ?string {
        if ($session===null || $csrf===null || $now<=0
            || !BadAround_Identity_Csrf_Policy::origin_allowed($origin,$this->siteOrigin)
            || !BadAround_Identity_Csrf_Policy::verify($csrf,$session,$this->csrfKey)) {
            return null;
        }
        return $this->authority->active_identity($session,$now);
    }
    /** Submits a private comment intent only; no publication. */
    public function submit_comment(
        ?string $session,?string $csrf,?string $origin,int $eventId,
        string $body,string $mode,?array $serverLoadedParent,int $now
    ): bool {
        try {
            $identity=$this->actor($session,$csrf,$origin,$now);
            if ($identity===null || !$this->authority->canonical_event_is_published($eventId)) {
                return false;
            }
            $intent=BadAround_Identity_Comment_Moderation_Policy::propose(
                $identity,$eventId,$body,$mode,$serverLoadedParent,$now
            );
            return $this->inbox->submit(bin2hex(random_bytes(16)),'comment',$identity,
                $eventId,$intent,$now)===true;
        } catch (Throwable $error) { return false; }
    }
    /** May request an edit only for a server-loaded verified creator binding. */
    public function submit_revision(
        ?string $session,?string $csrf,?string $origin,int $reportId,
        string $description,string $reason,string $serverLoadedSnapshotDigest,int $now
    ): bool {
        try {
            $identity=$this->actor($session,$csrf,$origin,$now);
            if ($identity===null) { return false; }
            $ownership=$this->authority->verified_owner_record($reportId);
            if ($ownership===null || !$this->editGuard->may_submit_revision(
                $session,$csrf,$origin,'report',$reportId,$ownership,$now
            )) { return false; }
            $intent=BadAround_Identity_Revision_Request_Policy::propose(
                $identity,$reportId,$description,$reason,true,
                $serverLoadedSnapshotDigest,$now
            );
            return $this->inbox->submit(bin2hex(random_bytes(16)),'revision',
                $identity,$reportId,$intent,$now)===true;
        } catch (Throwable $error) { return false; }
    }
    /** Permission checked from server storage, never from request parameters. */
    public function moderate(
        ?string $session,?string $csrf,?string $origin,string $intentId,
        int $version,string $decision,int $now
    ): bool {
        try {
            $identity=$this->actor($session,$csrf,$origin,$now);
            if ($identity===null || !$this->authority->may_moderate($identity)) {
                return false;
            }
            return $this->inbox->decide($intentId,$version,$decision,$identity,$now)===true;
        } catch (Throwable $error) { return false; }
    }
}
