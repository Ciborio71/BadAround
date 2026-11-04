<?php
/**
 * BA-IDENTITY-WIZARD-06 — dormant authenticated intake orchestration seam.
 *
 * NO REST route, WP hook, SQL, email, cookies or side effects on inclusion.
 * Both ordinary and media transports must call this boundary with an
 * independently validated request; $trustedSubmit must invoke the actual
 * canonical native pipeline. Do not pass an arbitrary client callback.
 *
 * IMPORTANT: this is not deployment-ready. There remains a crash window
 * between canonical creation and recovery intent insertion. A durable,
 * submission-ID keyed pre-intake reservation/atomic outbox is required before
 * any production controller may invoke this service.
 */
declare(strict_types=1);

require_once __DIR__ . '/native-intake-gate.php';
require_once __DIR__ . '/durable-ownership-handoff.php';

final class BadAround_Identity_Native_Intake_Orchestrator {
    public const DENIED = 'denied';
    public const SUBMISSION_FAILED = 'submission_failed';
    public const OWNER_PENDING = 'owner_pending';
    public const OWNER_CONFIRMED = 'owner_confirmed';
    public const DUPLICATE = 'duplicate';

    public function __construct(
        private BadAround_Identity_Native_Intake_Gate $gate,
        private BadAround_Identity_Durable_Ownership_Handoff $handoff
    ) {}

    /**
     * @param callable $trustedSubmit Already trusted canonical submit closure
     *   (ordinary or media), never a callback accepted from the request.
     * @return array{state:string,event_id?:int}
     *   Never returns identity ID, session token or recovery operation ID.
     */
    public function submit(?string $serverCookieToken, callable $trustedSubmit, int $now): array {
        // Neither media nor regular intake may persist anything before auth.
        $authorization = $this->gate->authorize($serverCookieToken, $now);
        if ($authorization === null) return ['state' => self::DENIED];

        try {
            $result = $trustedSubmit();
        } catch (Throwable $error) {
            return ['state' => self::SUBMISSION_FAILED];
        }

        if (!is_array($result) || ($result['status'] ?? null) !== 'success') {
            return ['state' => self::SUBMISSION_FAILED];
        }
        // A duplicate or recovered report cannot create a new owner grant.
        if (($result['duplicate'] ?? null) !== false ||
            ($result['persistence']['duplicate'] ?? null) !== false ||
            ($result['persistence']['recovered'] ?? null) !== false) {
            return ['state' => self::DUPLICATE];
        }

        $eventId = $result['event_id'] ?? null;
        if (!is_int($eventId) || $eventId <= 0) {
            return ['state' => self::OWNER_PENDING];
        }

        // No ownership confirmation until a durable intent has been recorded
        // and CAS-confirmed by the handoff. A failed handoff is never success.
        try {
            $state = $this->handoff->handoff(
                $serverCookieToken,
                $result,
                $authorization['operation_id'],
                $now
            );
        } catch (Throwable $error) {
            $state = BadAround_Identity_Durable_Ownership_Handoff::PENDING;
        }
        return [
            'state' => $state === BadAround_Identity_Durable_Ownership_Handoff::CONFIRMED
                ? self::OWNER_CONFIRMED : self::OWNER_PENDING,
            'event_id' => $eventId
        ];
    }
}
