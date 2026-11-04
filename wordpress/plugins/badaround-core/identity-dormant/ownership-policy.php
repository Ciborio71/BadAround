<?php
/**
 * BA-IDENTITY-01C: pure authorization policy. Not loaded by WordPress.
 * Actual ownership records must be established through a verified binding.
 */
declare(strict_types=1);

final class BadAround_Identity_Ownership_Policy {
    private const ALLOWED_KINDS = ['report', 'contribution', 'comment', 'sentinel'];
    private const ALLOWED_RELATIONS = ['creator', 'authorized_editor'];

    public static function may_edit(
        ?string $authenticatedIdentityId,
        string $resourceKind,
        int $resourceId,
        array $ownershipRecord
    ): bool {
        if (!$authenticatedIdentityId || $resourceId <= 0
            || !in_array($resourceKind, self::ALLOWED_KINDS, true)) {
            return false;
        }
        return ($ownershipRecord['identity_id'] ?? null) === $authenticatedIdentityId
            && ($ownershipRecord['resource_kind'] ?? null) === $resourceKind
            && (int)($ownershipRecord['resource_id'] ?? 0) === $resourceId
            && in_array(($ownershipRecord['relation'] ?? ''), self::ALLOWED_RELATIONS, true)
            && !empty($ownershipRecord['verified_binding_at'])
            && empty($ownershipRecord['revoked_at']);
    }
}
