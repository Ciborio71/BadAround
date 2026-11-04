<?php
/**
 * BA-IDENTITY-01D — independent current-status check.
 * The session record is NOT proof that the identity is still active.
 */
declare(strict_types=1);
interface BadAround_Identity_Active_Status {
    /** Must read verified_at/revoked_at from trusted persistent storage. */
    public function is_active_verified(string $identityId): bool;
}
final class BadAround_Identity_Active_Memory_Status implements BadAround_Identity_Active_Status {
    public array $active = [];
    public function is_active_verified(string $identityId): bool {
        return ($this->active[$identityId] ?? false) === true;
    }
}
