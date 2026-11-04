<?php
/**
 * BA-IDENTITY-01C: pure policy for authenticated modification requests.
 * Prototype only, never registered as an API route. Must not be confused
 * with WordPress admin authentication or the public contribution endpoint.
 */
declare(strict_types=1);
require_once __DIR__ . '/session-policy.php';
require_once __DIR__ . '/csrf-policy.php';
require_once __DIR__ . '/ownership-policy.php';
require_once __DIR__ . '/identity-active-status.php';

final class BadAround_Identity_Edit_Guard {
    private BadAround_Identity_Sessions $sessions;
    private string $serverKey;
    private string $siteOrigin;

    public function __construct(
        BadAround_Identity_Sessions $sessions,
        string $serverKey,
        string $siteOrigin,
        private BadAround_Identity_Active_Status $identityStatus
    ) {
        if (strlen($serverKey) < 32 || !str_starts_with($siteOrigin, 'https://')) {
            throw new InvalidArgumentException('Guard configuration invalid');
        }
        $this->sessions = $sessions;
        $this->serverKey = $serverKey;
        $this->siteOrigin = $siteOrigin;
    }

    /**
     * Fail-closed authorization for a resource-changing request.
     * The ownership row MUST be fetched by the server from trusted storage
     * for the requested resource, never supplied by the browser.
     * Returns an internal boolean; no account enumeration response.
     */
    public function may_submit_revision(
        ?string $sessionToken,
        ?string $csrfToken,
        ?string $origin,
        string $resourceKind,
        int $resourceId,
        array $serverLoadedOwnership,
        int $now
    ): bool {
        if ($sessionToken === null || $csrfToken === null
            || !BadAround_Identity_Csrf_Policy::origin_allowed($origin, $this->siteOrigin)) {
            return false;
        }
        $identity = $this->sessions->identity_for($sessionToken, $now);
        if ($identity === null
            || !$this->identityStatus->is_active_verified($identity)
            || !BadAround_Identity_Csrf_Policy::verify($csrfToken, $sessionToken, $this->serverKey)) {
            return false;
        }
        return BadAround_Identity_Ownership_Policy::may_edit(
            $identity, $resourceKind, $resourceId, $serverLoadedOwnership
        );
    }
}
