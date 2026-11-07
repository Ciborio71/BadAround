<?php
/**
 * BA-IDENTITY-WIZARD-08: dormant pre-reserved native submission coordinator.
 *
 * No WordPress hooks/routes or database initialization on include.
 * $trustedSubmit must be a server-chosen canonical intake callback.
 * $canonicalReader MUST load a canonical persisted receipt by submission UUID
 * under the native submission lock AFTER native intake releases it.
 * Incomplete/ambiguous rows are never automatically resubmitted.
 */
declare(strict_types=1);
require_once __DIR__ . '/pre-intake-reservation.php';
require_once __DIR__ . '/native-intake-gate.php';
require_once __DIR__ . '/durable-ownership-handoff.php';

interface BadAround_Identity_Pre_Intake_Canonical_Reader {
    public function completed(string $submissionId): ?array;
}

final class BadAround_Identity_Pre_Reserved_Intake {
    public const DENIED='denied';
    public const CONFLICT='conflict';
    public const PENDING='pending';
    public const OWNER_CONFIRMED='owner_confirmed';

    public function __construct(
        private BadAround_Identity_Native_Intake_Gate $gate,
        private BadAround_Identity_Pre_Intake_Reservation $reservation,
        private BadAround_Identity_Pre_Intake_Canonical_Reader $canonical,
        private BadAround_Identity_Durable_Ownership_Handoff $handoff
    ) {}

    /**
     * $submissionId must be extracted by the trusted controller from
     * validated canonical native input, not from an ownership claim.
     */
    public function execute(
        ?string $serverCookie,string $submissionId,callable $trustedSubmit,int $now
    ):array {
        if (!BadAround_Identity_Pre_Intake_Reservation::valid_submission($submissionId))
            return ['state'=>self::DENIED];
        $actor=$this->gate->authorize($serverCookie,$now);
        if ($actor===null)return ['state'=>self::DENIED];

        $reserve=$this->reservation->start(
            $actor['operation_id'],$submissionId,$actor['identity_id'],$now
        );
        if ($reserve===BadAround_Identity_Pre_Intake_Reservation::CONFLICT)
            return ['state'=>self::CONFLICT];
        if ($reserve!==BadAround_Identity_Pre_Intake_Reservation::RESERVED)
            return ['state'=>self::PENDING];

        try { $native=$trustedSubmit(); }
        catch(Throwable $error) {return ['state'=>self::PENDING];}

        // A durable reservation exists even if native persistence failed.
        if (!is_array($native) || ($native['status']??null)!=='success'
            || ($native['submission_id']??null)!==$submissionId
            || ($native['duplicate']??null)!==false
            || ($native['persistence']['duplicate']??null)!==false
            || ($native['persistence']['recovered']??null)!==false
            || ($native['persistence']['completed']??null)!==true) {
            return ['state'=>self::PENDING];
        }

        try {
            $receipt=$this->canonical->completed($submissionId);
            if (!is_array($receipt)
                || ($receipt['submission_id']??null)!==$submissionId
                || ($receipt['report_id']??null)!==($native['report_id']??null)
                || ($receipt['event_id']??null)!==($native['event_id']??null)
                || ($receipt['duplicate']??null)!==false) return ['state'=>self::PENDING];

            if ($this->reservation->complete(
                $actor['operation_id'],$submissionId,$receipt,$now+1
            )!==BadAround_Identity_Pre_Intake_Reservation::LINKED) {
                return ['state'=>self::PENDING];
            }
            // IMPORTANT: post-link crash is recoverable from the pre-intake
            // outbox, but the private recovery worker still needs an adapter
            // from linked row to durable creator grant before activation.
            $ownership=$this->handoff->handoff(
                $serverCookie,$native,$actor['operation_id'],$now+2
            );
            return ['state'=>$ownership===BadAround_Identity_Durable_Ownership_Handoff::CONFIRMED
                ? self::OWNER_CONFIRMED:self::PENDING];
        } catch(Throwable $error) {return ['state'=>self::PENDING];}
    }
}
