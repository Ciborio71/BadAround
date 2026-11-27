<?php
/**
 * BA-IDENTITY-WIZARD-05: server-side preflight for future authenticated
 * native intake. Dormant: no hooks, HTTP routes, cookies or database writes.
 *
 * Call BEFORE invoking either native Golden Path, including its media path.
 * This is an authorization preflight, NOT a claim that ownership has been
 * durably reserved or bound. A final journal transaction remains mandatory.
 */
declare(strict_types=1);
require_once __DIR__ . '/session-policy.php';
require_once __DIR__ . '/identity-active-status.php';

final class BadAround_Identity_Native_Intake_Gate {
    public function __construct(
        private BadAround_Identity_Sessions $sessions,
        private BadAround_Identity_Active_Status $status
    ) {}

    /**
     * @return array{identity_id:string,operation_id:string,verified_at:int}|null
     * The token comes from the trusted, secure host-only session cookie,
     * never from REST JSON, query parameters, or local storage.
     * Operation ID is always generated on the server.
     */
    public function authorize(?string $cookieToken, int $now): ?array {
        if ($now <= 0 || !is_string($cookieToken)
            || preg_match('/^[a-f0-9]{64}$/D', $cookieToken) !== 1) {
            return null;
        }
        try {
            $identity = $this->sessions->identity_for($cookieToken, $now);
            if (!is_string($identity)
                || preg_match('/^[A-Za-z0-9_-]{8,128}$/D', $identity) !== 1
                || !$this->status->is_active_verified($identity)) {
                return null;
            }
            // Re-read current status to reject sequential revocations.
            if (!$this->status->is_active_verified($identity)) return null;
            $operation = bin2hex(random_bytes(16));
            return [
                'identity_id' => $identity,
                'operation_id' => $operation,
                'verified_at' => $now,
            ];
        } catch (Throwable $error) {
            return null;
        }
    }
}
