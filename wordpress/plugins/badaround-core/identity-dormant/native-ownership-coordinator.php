<?php
/**
 * BA-IDENTITY-WIZARD-02 — dormant server-side ownership coordination.
 *
 * No hooks, routes, cookies, SQL or WordPress dependencies. This class must
 * only receive a direct, trusted return from Golden Path, never a client JSON
 * body. Persistent transaction/outbox recovery is a separate integration.
 */
declare(strict_types=1);
require_once __DIR__ . '/native-report-receipt.php';
require_once __DIR__ . '/post-intake-binder.php';

final class BadAround_Identity_Native_Ownership_Coordinator {
    public const INELIGIBLE = 'ineligible';
    public const CONFIRMED = 'confirmed';
    public const UNCONFIRMED = 'unconfirmed';

    public function __construct(
        private BadAround_Identity_Post_Intake_Binder $binder
    ) {}

    /**
     * @return 'ineligible'|'confirmed'|'unconfirmed'
     * An unconfirmed valid candidate MUST be handled by a durable recovery
     * mechanism before a real-world controller is wired to this class.
     * Neither an event creation response nor an ownership confirmation may
     * be inferred from UNCONFIRMED.
     */
    public function handle(
        ?string $sessionToken,
        array $trustedGoldenPathResult,
        int $now
    ): string {
        if ($now <= 0) {
            return self::INELIGIBLE;
        }
        $receipt = BadAround_Identity_Native_Report_Receipt::from_golden_path(
            $trustedGoldenPathResult
        );
        if ($receipt === null) {
            return self::INELIGIBLE;
        }
        if ($sessionToken === null ||
            preg_match('/^[a-f0-9]{64}$/D', $sessionToken) !== 1) {
            return self::UNCONFIRMED;
        }
        try {
            return $this->binder->bind_created_report($sessionToken, $receipt, $now)
                ? self::CONFIRMED : self::UNCONFIRMED;
        } catch (Throwable $error) {
            return self::UNCONFIRMED;
        }
    }
}
