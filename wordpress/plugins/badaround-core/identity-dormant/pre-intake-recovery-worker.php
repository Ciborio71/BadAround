<?php
/**
 * BA-IDENTITY-WIZARD-09: dormant private recovery worker.
 *
 * A trusted backend must hold a per-submission native lock while invoking
 * recover(). No hook, route, scheduled job or SQL is registered on inclusion.
 * It NEVER replays native submission. All identities come from the durable
 * pre-intake row, not HTTP input. No public success on uncertain state.
 */
declare(strict_types=1);
require_once __DIR__.'/pre-reserved-native-intake.php';
require_once __DIR__.'/ownership-recovery-reconciler.php';

interface BadAround_Identity_Pre_Intake_Recovery_Reader {
    public function find_by_submission(string $submissionId):?array;
    public function link(string $operationId,string $submissionId,int $reportId,int $eventId,int $now):bool;
}
final class BadAround_Identity_Pre_Intake_Recovery_Worker {
    public const PENDING='pending';
    public const LINKED='linked';
    public const REVIEW='needs_review';
    public const UNAVAILABLE='unavailable';

    public function __construct(
        private BadAround_Identity_Pre_Intake_Recovery_Reader $preIntake,
        private BadAround_Identity_Pre_Intake_Canonical_Reader $canonical,
        private BadAround_Identity_Active_Status $identityStatus,
        private BadAround_Identity_Recovery_Owner_Reader $owners,
        private BadAround_Identity_Recovery_Journal $journal,
        private BadAround_Identity_Ownership_Grant $grants,
        private BadAround_Identity_Intake_Receipt $receiptProof
    ) {}

    /**
     * MUST be called under a server-held native submission lock.
     * Returns LINKED only when canonical ownership has been confirmed.
     */
    public function recover(string $submissionId,int $now):string {
        if (!BadAround_Identity_Pre_Intake_Reservation::valid_submission($submissionId)
            || $now<=0) return self::UNAVAILABLE;
        try {
            $row=$this->preIntake->find_by_submission($submissionId);
            if (!self::valid_row($row,$submissionId))return self::UNAVAILABLE;
            if (!$this->identityStatus->is_active_verified($row['identity_id']))return self::REVIEW;
            $canonical=$this->canonical->completed($submissionId);
            if (!is_array($canonical) || ($canonical['submission_id']??null)!==$submissionId
                || ($canonical['duplicate']??null)!==false
                || !is_int($canonical['report_id']??null) || $canonical['report_id']<=0
                || !is_int($canonical['event_id']??null) || $canonical['event_id']<=0) {
                return self::PENDING;
            }
            $report=$canonical['report_id'];$event=$canonical['event_id'];
            if (!$this->receiptProof->is_new_canonical_report($report,$event))return self::REVIEW;
            if ($row['state']==='reserved') {
                if (!$this->preIntake->link($row['operation_id'],$submissionId,$report,$event,$now)) {
                    return self::PENDING;
                }
            } elseif ($row['state']==='linked') {
                if ($row['report_id']!==$report || $row['event_id']!==$event)return self::REVIEW;
            } else return self::UNAVAILABLE;
            // Journal before attempting creator grant. Existing journal must
            // match every trusted identity/resource field exactly.
            $operation=$row['operation_id'];
            $intent=BadAround_Identity_Ownership_Recovery_Policy::new_intent(
                $row['identity_id'],['report_id'=>$report,'event_id'=>$event,'duplicate'=>false],
                $operation,$now
            );
            if (!$this->journal->create($intent)) {
                $existing=$this->journal->load($operation);
                if (!self::matches_intent($existing,$intent))return self::REVIEW;
            }
            $existingOwner=$this->owners->find_for_report($report);
            if ($existingOwner!==null && !self::matches_owner($existingOwner,$intent))return self::REVIEW;
            // Use shared reconciler which performs another owner read after
            // a possible grant, handles CAS and refuses ambiguity.
            $reconciler=new BadAround_Identity_Ownership_Recovery_Reconciler(
                $this->journal,$this->receiptProof,$this->identityStatus,
                $this->owners,$this->grants
            );
            $state=$reconciler->reconcile($operation,$now+1);
            return $state===BadAround_Identity_Ownership_Recovery_Reconciler::CONFIRMED
                ? self::LINKED
                : ($state===BadAround_Identity_Ownership_Recovery_Reconciler::REVIEW
                    ? self::REVIEW:self::PENDING);
        } catch(Throwable $error) {return self::UNAVAILABLE;}
    }

    private static function valid_row(?array $r,string $submission):bool {
        return is_array($r) && ($r['submission_id']??null)===$submission
            && is_string($r['operation_id']??null)
            && preg_match('/^[a-f0-9]{32}$/D',$r['operation_id'])===1
            && is_string($r['identity_id']??null)
            && preg_match('/^[A-Za-z0-9_-]{8,128}$/D',$r['identity_id'])===1
            && in_array($r['state']??null,['reserved','linked'],true)
            && is_int($r['created_at']??null) && $r['created_at']>0;
    }
    private static function matches_intent(?array $stored,array $expected):bool {
        return is_array($stored)
            && ($stored['operation_id']??null)===$expected['operation_id']
            && ($stored['identity_id']??null)===$expected['identity_id']
            && ($stored['report_id']??null)===$expected['report_id']
            && ($stored['event_id']??null)===$expected['event_id']
            && in_array($stored['state']??null,['pending','bound'],true);
    }
    private static function matches_owner(array $r,array $intent):bool {
        return ($r['identity_id']??null)===$intent['identity_id']
            && ($r['resource_kind']??null)==='report'
            && ($r['resource_id']??null)===$intent['report_id']
            && ($r['relation']??null)==='creator'
            && ($r['revoked_at']??null)===null;
    }
}
