<?php
/**
 * BA-IDENTITY-01D — pure, dormant community policy.
 * This produces moderation intents, never public post edits.
 */
declare(strict_types=1);

final class BadAround_Identity_Community_Policy {
    public const DISPLAY_MODES = ['anonymous','pseudonym','first_name','first_name_initial'];
    private const MAX_COMMENT = 2500;
    private const MAX_REVISION = 5000;

    public static function public_identity(string $firstName, string $lastName, string $mode, string $alias = ''): string {
        if (!in_array($mode,self::DISPLAY_MODES,true)) {
            throw new InvalidArgumentException('Unknown identity mode');
        }
        if ($mode === 'anonymous') { return 'Anonimo'; }
        if ($mode === 'pseudonym') {
            $alias = trim($alias);
            if ($alias === '' || mb_strlen($alias,'UTF-8') > 80
                || preg_match('/[<>\\x00-\\x1f]/u',$alias)) {
                throw new InvalidArgumentException('Invalid pseudonym');
            }
            return $alias;
        }
        $first = trim($firstName);
        $last = trim($lastName);
        if ($first === '' || $last === ''
            || mb_strlen($first,'UTF-8') > 191 || mb_strlen($last,'UTF-8') > 191
            || preg_match('/[<>\\x00-\\x1f]/u',$first)
            || preg_match('/[<>\\x00-\\x1f]/u',$last)) {
            throw new InvalidArgumentException('Mandatory private identity invalid');
        }
        if ($mode === 'first_name') { return $first; }
        return $first.' '.mb_substr($last,0,1,'UTF-8').'.';
    }

    /**
     * Only a text-only request is modelled; media, PII and GEO must be
     * validated separately by the private intake pipeline.
     */
    public static function comment_intent(
        string $identityId, int $eventId, string $body,
        string $displayMode, ?array $parent = null
    ): array {
        self::assert_actor($identityId,$eventId);
        if (!in_array($displayMode,self::DISPLAY_MODES,true)) {
            throw new InvalidArgumentException('Invalid display mode');
        }
        $body = trim($body);
        if (mb_strlen($body,'UTF-8') < 3 || mb_strlen($body,'UTF-8') > self::MAX_COMMENT) {
            throw new InvalidArgumentException('Invalid comment body');
        }
        $parentId = null;
        if ($parent !== null) {
            if (($parent['event_id'] ?? null) !== $eventId
                || ($parent['moderation_status'] ?? null) !== 'approved'
                || !(is_int($parent['comment_id'] ?? null) && $parent['comment_id']>0
                    || is_string($parent['comment_id']??null) && preg_match('/^[a-f0-9]{32}$/D',$parent['comment_id']))
                || ($parent['parent_id'] ?? null) !== null) {
                throw new InvalidArgumentException('Invalid reply parent');
            }
            $parentId = $parent['comment_id'];
        }
        return [
            'identity_id'=>$identityId,'event_id'=>$eventId,
            'parent_id'=>$parentId,'body'=>$body,
            'display_mode'=>$displayMode,
            'moderation_status'=>'pending',
        ];
    }

    /** Edits are private requests, never direct public mutations. */
    public static function revision_intent(
        string $identityId, int $reportId, string $proposedDescription,
        string $reason, bool $verifiedOwnership
    ): array {
        self::assert_actor($identityId,$reportId);
        if (!$verifiedOwnership) { throw new DomainException('Verified ownership required'); }
        $content=trim($proposedDescription);
        $reason=trim($reason);
        if (mb_strlen($content,'UTF-8') < 10
            || mb_strlen($content,'UTF-8') > self::MAX_REVISION
            || mb_strlen($reason,'UTF-8') > 500) {
            throw new InvalidArgumentException('Invalid proposed revision');
        }
        return [
            'identity_id'=>$identityId,'report_id'=>$reportId,
            'proposed_description'=>$content,'reason'=>$reason,
            'moderation_status'=>'pending','public_mutation'=>false
        ];
    }

    private static function assert_actor(string $identityId,int $resourceId): void {
        if (!preg_match('/^[a-zA-Z0-9_-]{8,128}$/D',$identityId) || $resourceId <= 0) {
            throw new InvalidArgumentException('Invalid actor/resource');
        }
    }
}
