<?php
/**
 * BA-REPORT-IDENTITY-06: private reconciler for multi-phase WordPress intake.
 *
 * Neither HTTP route nor WordPress hook is registered here.
 * This module NEVER calls native submit, publishes an event or sends an OTP.
 * The lock adapter MUST be backed by the existing native submission lock.
 */
declare(strict_types=1);

interface BadAround_Report_Otp_Native_Lock {
    public function acquire(string $submissionId):bool;
    public function release(string $submissionId):void;
}
interface BadAround_Report_Otp_Reconcile_Outbox {
    public function read(string $operationId):?array;
    public function link(string $operationId,array $canonical,int $now):bool;
    public function confirmed(string $operationId):bool;
}
interface BadAround_Report_Otp_Reconcile_Reader {
    public function completed(string $submissionId):?array;
}
final class BadAround_Report_Otp_WordPress_Reconciler {
    public function __construct(
        private BadAround_Report_Otp_Native_Lock $lock,
        private BadAround_Report_Otp_Reconcile_Outbox $outbox,
        private BadAround_Report_Otp_Reconcile_Reader $reader
    ) {}

    /**
     * Call after the canonical native pipeline has returned, or during
     * administrator-controlled recovery. The native intake's own lock must
     * already have been released, otherwise acquisition correctly fails.
     * Ambiguous/missing reports remain reserved for later reconciliation.
     */
    public function reconcile(string $operationId,string $expectedSubmission,int $now):string {
        if(!preg_match('/^[a-f0-9]{32}$/D',$operationId)
            || !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D',$expectedSubmission)
            || $now<=0) return 'unavailable';
        $acquired=false;
        try {
            $row=$this->outbox->read($operationId);
            if(!is_array($row)
                || !is_string($row['submission_id']??null)
                || !hash_equals($expectedSubmission,$row['submission_id']))return 'unavailable';
            if(($row['state']??null)==='linked') {
                return $this->outbox->confirmed($operationId)?'linked':'unavailable';
            }
            if(($row['state']??null)!=='reserved')return 'pending';
            $acquired=$this->lock->acquire($expectedSubmission);
            if(!$acquired)return 'pending';
            // Re-read under native UUID lock before inspecting canonical state.
            $fresh=$this->outbox->read($operationId);
            if(!is_array($fresh)
                || ($fresh['state']??null)!=='reserved'
                || !hash_equals($expectedSubmission,(string)($fresh['submission_id']??'')))return 'pending';
            $canonical=$this->reader->completed($expectedSubmission);
            if(!is_array($canonical))return 'pending';
            if(($canonical['duplicate']??null)!==false
                || ($canonical['submission_id']??null)!==$expectedSubmission)return 'unavailable';
            if(!$this->outbox->link($operationId,$canonical,$now))return 'pending';
            return $this->outbox->confirmed($operationId)?'linked':'pending';
        }catch(Throwable $error){return 'unavailable';}
        finally {
            if($acquired) {
                try{$this->lock->release($expectedSubmission);}catch(Throwable $ignored){}
            }
        }
    }
}
