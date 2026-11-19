<?php
/**
 * BA-IDENTITY-01D — dormant post-intake ownership boundary.
 * Only a verified, server-resolved session AND newly created canonical
 * report receipt may request a binding. No WordPress hooks are registered.
 */
declare(strict_types=1);
require_once __DIR__ . '/session-policy.php';
require_once __DIR__ . '/identity-active-status.php';

interface BadAround_Identity_Intake_Receipt {
    /** Called only using a server-sourced receipt, not browser JSON. */
    public function is_new_canonical_report(int $reportId, int $eventId): bool;
}

interface BadAround_Identity_Ownership_Grant {
    /** Implementation must audit, persist and recover any partial failure. */
    public function grant_creator(string $identityId, string $resourceKind, int $resourceId, int $now): bool;
}

final class BadAround_Identity_Post_Intake_Binder {
    public function __construct(
        private BadAround_Identity_Intake_Receipt $receipts,
        private BadAround_Identity_Ownership_Grant $grants,
        private BadAround_Identity_Sessions $sessions,
        private BadAround_Identity_Active_Status $identityStatus
    ) {}

    /**
     * Accepts a bearer session from the verified cookie and a trusted server-side
     * receipt (report_id,event_id,duplicate). Never accepts a claimed identity id.
     * Never assigns ownership to a duplicate or pre-existing report.
     * Caller is responsible for transaction/outbox integration; a false
     * result MUST NOT be presented as a successful ownership grant.
     */
    public function bind_created_report(
        ?string $sessionToken, array $receipt, int $now
    ): bool {
        if ($now <= 0 || $sessionToken === null
            || !array_key_exists('duplicate', $receipt)
            || $receipt['duplicate'] !== false) {
            return false;
        }
        $reportId = $receipt['report_id'] ?? null;
        $eventId = $receipt['event_id'] ?? null;
        if (!is_int($reportId) || !is_int($eventId)
            || $reportId <= 0 || $eventId <= 0) {
            return false;
        }
        try {
            $verifiedIdentity = $this->sessions->identity_for($sessionToken,$now);
            if (!$verifiedIdentity || !$this->identityStatus->is_active_verified($verifiedIdentity)
                || !$this->receipts->is_new_canonical_report($reportId,$eventId)) {
                return false;
            }
            // A second status read closes ordinary sequential revocation before
            // granting. This is NOT a transactional guarantee against a race.
            if (!$this->identityStatus->is_active_verified($verifiedIdentity)) {
                return false;
            }
            return $this->grants->grant_creator($verifiedIdentity,'report',$reportId,$now) === true;
        } catch (Throwable $e) {
            // Never grant or report success on unavailable session, status,
            // receipt or ownership infrastructure. Controllers may audit a
            // non-sensitive failure code outside this dormant policy class.
            return false;
        }
    }
}
