<?php
/**
 * BA-REPORT-IDENTITY-02B — pure report/OTP reservation-outbox contract.
 *
 * Isolated (no WordPress hooks, routes, DB writes). Every transition MUST
 * be persisted using a conditional version CAS by its future adapter.
 * A RESERVED intent MUST be reconciled against canonical repository storage
 * BEFORE any retry. Never infer success from a request body or email equality.
 */
declare(strict_types=1);

final class BadAround_Report_Otp_Reservation_Policy {
    public const VERIFIED='verified';
    public const RESERVED='reserved';
    public const LINKED='linked';
    public const NEEDS_REVIEW='needs_review';

    public static function verified(string $challengeId,string $submissionId,string $emailLookup,int $now):array {
        if (!preg_match('/^[a-f0-9]{32}$/D',$challengeId)
            || !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-8][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D',$submissionId)
            || !preg_match('/^[a-f0-9]{64}$/D',$emailLookup)
            || $now<=0) {
            throw new InvalidArgumentException('Untrusted verification receipt');
        }
        return ['challenge_id'=>$challengeId,'submission_id'=>$submissionId,
            'email_lookup'=>$emailLookup,'state'=>self::VERIFIED,
            'version'=>0,'created_at'=>$now,'reserved_at'=>null,
            'report_id'=>null,'event_id'=>null,'resolved_at'=>null];
    }
    /** Reserve once; do not retry or reset a reservation without reconciliation. */
    public static function reserve(array $stored,string $expectedSubmission,string $expectedEmailLookup,int $now):array {
        if (($stored['state']??null)!==self::VERIFIED
            || !is_int($stored['version']??null)
            || $stored['version']<0 || $now<=(int)($stored['created_at']??0)
            || !is_string($stored['submission_id']??null)
            || !hash_equals($stored['submission_id'],$expectedSubmission)
            || !is_string($stored['email_lookup']??null)
            || !hash_equals($stored['email_lookup'],$expectedEmailLookup)) {
            throw new DomainException('Reservation denied');
        }
        $stored['state']=self::RESERVED;
        $stored['reserved_at']=$now;
        $stored['version']++;
        return $stored;
    }
    /**
     * Only canonical post-persistence data loaded by a privileged backend,
     * after the original native submission lock and recovery checks.
     */
    public static function link(array $stored,array $trustedCanonical,int $now):array {
        if (($stored['state']??null)!==self::RESERVED
            || !is_int($stored['version']??null)
            || !is_int($stored['reserved_at']??null)
            || $now<=$stored['reserved_at']
            || ($trustedCanonical['duplicate']??null)!==false
            || !is_string($trustedCanonical['submission_id']??null)
            || !hash_equals((string)($stored['submission_id']??''),$trustedCanonical['submission_id'])
            || !is_int($trustedCanonical['report_id']??null)
            || !is_int($trustedCanonical['event_id']??null)
            || $trustedCanonical['report_id']<=0
            || $trustedCanonical['event_id']<=0) {
            throw new DomainException('Canonical receipt mismatch');
        }
        $stored['state']=self::LINKED;
        $stored['report_id']=$trustedCanonical['report_id'];
        $stored['event_id']=$trustedCanonical['event_id'];
        $stored['resolved_at']=$now;
        $stored['version']++;
        return $stored;
    }
    /** Terminal failure requires manual recovery, never grants ownership. */
    public static function flag_for_review(array $stored,int $now):array {
        if (($stored['state']??null)!==self::RESERVED
            || !is_int($stored['version']??null)
            || !is_int($stored['reserved_at']??null)
            || $now<=$stored['reserved_at']) {
            throw new DomainException('Invalid review transition');
        }
        $stored['state']=self::NEEDS_REVIEW;
        $stored['resolved_at']=$now;
        $stored['version']++;
        return $stored;
    }
    public static function can_confirm_received(array $stored):bool {
        return ($stored['state']??null)===self::LINKED
            && is_int($stored['report_id']??null)
            && $stored['report_id']>0
            && is_int($stored['event_id']??null)
            && $stored['event_id']>0;
    }
}
