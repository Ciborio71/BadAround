<?php
/**
 * BA-IDENTITY-WIZARD-15: lock the native submission while rereading its
 * canonical receipt following a Golden Path submission.
 *
 * Dormant, no hooks, no SQL on include. Acquire/release only the native
 * submission lock; an unavailable lock cannot provide an ownership proof.
 * Caller must not already hold this same lock.
 */
declare(strict_types=1);
require_once __DIR__.'/pre-reserved-native-intake.php';
require_once __DIR__.'/report-otp-wordpress-reconciler.php';

final class BadAround_Identity_Locked_Canonical_Reader
    implements BadAround_Identity_Pre_Intake_Canonical_Reader {
    public function __construct(
        private BadAround_Report_Otp_Native_Lock $nativeLock,
        private BadAround_Identity_Pre_Intake_Canonical_Reader $canonical
    ) {}

    public function completed(string $submissionId):?array {
        if (!BadAround_Identity_Pre_Intake_Reservation::valid_submission($submissionId))
            return null;
        $acquired=false;
        try {
            $acquired=$this->nativeLock->acquire($submissionId);
            if (!$acquired)return null;
            $result=$this->canonical->completed($submissionId);
            if (!is_array($result) ||
                ($result['submission_id']??null)!==$submissionId ||
                ($result['duplicate']??null)!==false ||
                !is_int($result['report_id']??null) ||
                $result['report_id']<=0 ||
                !is_int($result['event_id']??null) ||
                $result['event_id']<=0) return null;
            return $result;
        } catch(Throwable $error) {
            return null;
        } finally {
            if($acquired) {
                try{$this->nativeLock->release($submissionId);}
                catch(Throwable $error){}
            }
        }
    }
}
