<?php
/**
 * BA-IDENTITY-WIZARD-03 — dormant durable ownership handoff.
 * No WordPress hooks, controller registration, cookies, or direct SQL.
 * Native Golden Path return and verified server-side session ONLY.
 *
 * Never grant first and journal later: persist a unique recovery intent
 * before attempting creator grant. A failed/ambiguous grant stays pending
 * for reconciliation by an independently controlled worker.
 */
declare(strict_types=1);
require_once __DIR__ . '/native-report-receipt.php';
require_once __DIR__ . '/post-intake-binder.php';
require_once __DIR__ . '/ownership-recovery-policy.php';

interface BadAround_Identity_Recovery_Journal {
    public function create(array $intent): bool;
    public function load(string $operationId): ?array;
    public function transition(string $operationId,int $revision,string $outcome,int $now): bool;
}
final class BadAround_Identity_Durable_Ownership_Handoff {
    public const INELIGIBLE='ineligible';
    public const PENDING='pending';
    public const CONFIRMED='confirmed';

    public function __construct(
        private BadAround_Identity_Sessions $sessions,
        private BadAround_Identity_Active_Status $status,
        private BadAround_Identity_Intake_Receipt $receipts,
        private BadAround_Identity_Ownership_Grant $grants,
        private BadAround_Identity_Recovery_Journal $journal
    ) {}

    /**
     * $operationId is a fresh cryptographically random server-generated
     * 32-hex identifier; it must not be obtained from user-controlled input.
     * $goldenResult is the trusted native result, never the REST request.
     */
    public function handoff(
        ?string $sessionToken,array $goldenResult,string $operationId,int $now
    ):string {
        if ($now<=0 || $sessionToken===null
            || preg_match('/^[a-f0-9]{64}$/D',$sessionToken)!==1
            || preg_match('/^[a-f0-9]{32}$/D',$operationId)!==1) {
            return self::INELIGIBLE;
        }
        $receipt=BadAround_Identity_Native_Report_Receipt::from_golden_path($goldenResult);
        if ($receipt===null) return self::INELIGIBLE;
        try {
            $identity=$this->sessions->identity_for($sessionToken,$now);
            if (!is_string($identity) || !$this->status->is_active_verified($identity)
                || !$this->receipts->is_new_canonical_report($receipt['report_id'],$receipt['event_id'])) {
                return self::INELIGIBLE;
            }
            if (!$this->status->is_active_verified($identity)) return self::INELIGIBLE;
            $intent=BadAround_Identity_Ownership_Recovery_Policy::new_intent(
                $identity,$receipt,$operationId,$now
            );
            if (!$this->journal->create($intent)) {
                // A duplicate row or unknown state is never success.
                return self::PENDING;
            }
            // After journaling, do not report ownership on any failure.
            if (!$this->status->is_active_verified($identity)) return self::PENDING;
            if (!$this->grants->grant_creator($identity,'report',$receipt['report_id'],$now)) {
                return self::PENDING;
            }
            // CAS failure is ambiguous: a reconciliation process must inspect
            // the canonical owner record before retrying.
            return $this->journal->transition($operationId,0,'granted',$now+1)
                ? self::CONFIRMED : self::PENDING;
        } catch (Throwable $error) {
            return self::PENDING;
        }
    }
}
