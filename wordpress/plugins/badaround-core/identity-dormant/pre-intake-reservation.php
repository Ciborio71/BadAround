<?php
/**
 * BA-IDENTITY-WIZARD-07 — dormant identity/native intake preflight outbox.
 *
 * No WordPress hooks or routes. A trusted caller must resolve session and
 * canonical submission ID independently; never use a client identity claim.
 * Reserve BEFORE calling native persistence. Recovery must inspect the
 * canonical report by submission UUID before a retry. Never auto-resubmit.
 */
declare(strict_types=1);

interface BadAround_Identity_Pre_Intake_Store {
    public function reserve(string $operationId,string $submissionId,string $identityId,int $now): bool;
    public function find_by_submission(string $submissionId): ?array;
    public function link(string $operationId,string $submissionId,int $reportId,int $eventId,int $now): bool;
}
final class BadAround_Identity_Pre_Intake_Reservation {
    public const RESERVED='reserved';
    public const LINKED='linked';
    public const UNAVAILABLE='unavailable';
    public const CONFLICT='conflict';

    public function __construct(private BadAround_Identity_Pre_Intake_Store $store) {}

    /**
     * Caller identity MUST be supplied by the server's verified session gate.
     * Do not return the identity or opaque operation ID to the browser.
     */
    public function start(
        string $operationId,string $submissionId,string $verifiedIdentity,int $now
    ): string {
        if (!self::valid_operation($operationId) ||
            !self::valid_submission($submissionId) ||
            preg_match('/^[A-Za-z0-9_-]{8,128}$/D',$verifiedIdentity)!==1 ||
            $now<=0) return self::UNAVAILABLE;
        try {
            return $this->store->reserve($operationId,$submissionId,$verifiedIdentity,$now)
                ? self::RESERVED : self::CONFLICT;
        } catch (Throwable $error) {return self::UNAVAILABLE;}
    }

    /** Strict match with a canonical, server-loaded report receipt. */
    public function complete(string $operationId,string $submissionId,array $canonical,int $now):string {
        if (!self::valid_operation($operationId) || !self::valid_submission($submissionId)
            || $now<=0 || ($canonical['submission_id']??null)!==$submissionId
            || ($canonical['duplicate']??null)!==false
            || !is_int($canonical['report_id']??null) || $canonical['report_id']<=0
            || !is_int($canonical['event_id']??null) || $canonical['event_id']<=0) {
            return self::UNAVAILABLE;
        }
        try {
            $row=$this->store->find_by_submission($submissionId);
            if (!is_array($row) || ($row['state']??null)!==self::RESERVED
                || ($row['operation_id']??null)!==$operationId
                || ($row['submission_id']??null)!==$submissionId) return self::CONFLICT;
            return $this->store->link($operationId,$submissionId,
                $canonical['report_id'],$canonical['event_id'],$now)
                ? self::LINKED : self::CONFLICT;
        } catch(Throwable $error) {return self::UNAVAILABLE;}
    }

    public static function valid_operation(string $id):bool {
        return preg_match('/^[a-f0-9]{32}$/D',$id)===1;
    }
    public static function valid_submission(string $id):bool {
        return preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-8][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D',$id)===1;
    }
}
