<?php
/**
 * BA-REPORT-IDENTITY-03 — isolated atomic intake/OTP coordinator.
 *
 * A future canonical adapter must use the SAME PDO connection, report database
 * and existing native intake locks. This prototype intentionally does NOT
 * register WordPress routes or modify real reports.
 */
declare(strict_types=1);
require_once __DIR__.'/report-otp-store.php';

interface BadAround_Report_Canonical_Transaction_Writer {
    /**
     * Run exclusively INSIDE the same PDO transaction supplied by coordinator.
     * Must be idempotent and persist both canonical private report and event.
     * Return a server-loaded canonical receipt, never request JSON.
     */
    public function insert_new_canonical_report(
        PDO $connection,string $submissionId,int $now
    ): array;
}

final class BadAround_Report_Otp_Atomic_Intake {
    public function __construct(
        private PDO $db,
        private BadAround_Report_Otp_Store $otp,
        private BadAround_Report_Canonical_Transaction_Writer $writer
    ) {
        $this->db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    }

    /** No report confirmation until both OTP claim and canonical insert commit. */
    public function submit(
        string $challengeId,string $submissionId,string $trustedEmailLookup,int $now
    ):?array {
        if ($now<=0 || $this->db->inTransaction()) {return null;}
        try {
            $this->db->beginTransaction();
            $proof=$this->otp->claim_in_transaction(
                $challengeId,$submissionId,$trustedEmailLookup,$now
            );
            if (!is_array($proof) || !BadAround_Report_Verification_Policy::verified_receipt(
                $proof,$trustedEmailLookup,$submissionId,$now
            )) {
                $this->db->rollBack();
                return null;
            }
            // Writer is the trusted server-side canonical adapter, NOT an
            // untrusted callback coming from a request or browser payload.
            $canonical=$this->writer->insert_new_canonical_report(
                $this->db,$submissionId,$now
            );
            if (!is_array($canonical)
                || ($canonical['duplicate']??null)!==false
                || ($canonical['submission_id']??null)!==$submissionId
                || !is_int($canonical['report_id']??null)
                || !is_int($canonical['event_id']??null)
                || $canonical['report_id']<=0 || $canonical['event_id']<=0) {
                throw new RuntimeException('Canonical receipt not created');
            }
            $this->db->commit();
            // No raw OTP/email, internal IDs, or private record is exposed.
            return ['status'=>'received_for_moderation','duplicate'=>false];
        } catch(Throwable $error) {
            if($this->db->inTransaction()){$this->db->rollBack();}
            return null;
        }
    }
}
