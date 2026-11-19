<?php
/**
 * BA-IDENTITY-01C — isolated PDO ownership repository.
 * Requires trusted server-side verification; NEVER grants ownership using
 * an email address or public event identifier supplied by the browser.
 * Not bootstrapped into WordPress or deployed.
 */
declare(strict_types=1);
require_once __DIR__ . '/ownership-policy.php';

final class BadAround_Identity_Mysql_Ownership_Store {
    private PDO $pdo;
    private string $table;

    public function __construct(PDO $pdo, string $table) {
        if (!preg_match('/^[a-zA-Z0-9_]+$/D', $table)) {
            throw new InvalidArgumentException('Invalid table');
        }
        $this->pdo = $pdo;
        $this->table = $table;
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /**
     * A grant is allowed only from a trusted operation which has verified
     * identity and resource creation. The grant itself does not establish
     * those facts. The caller must perform it in the owning transaction.
     */
    public function grant_creator(
        string $identityId, string $resourceKind, int $resourceId,
        int $verifiedAt
    ): bool {
        self::validate_resource($identityId, $resourceKind, $resourceId);
        if ($verifiedAt <= 0) { throw new InvalidArgumentException('Invalid verification time'); }
        $sql = "INSERT INTO `{$this->table}`
            (identity_id,resource_kind,resource_id,relation,verified_binding_at,revoked_at)
            VALUES (:identity,:kind,:resource,'creator',:verified,NULL)";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':identity'=>$identityId, ':kind'=>$resourceKind,
                ':resource'=>$resourceId, ':verified'=>$verifiedAt
            ]);
            return $stmt->rowCount() === 1;
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') { return false; }
            throw $e;
        }
    }

    /** Read ownership only from trusted database records. */
    public function find_for_resource(string $resourceKind, int $resourceId): ?array {
        if (!in_array($resourceKind, ['report','contribution','comment','sentinel'], true)
            || $resourceId <= 0) { return null; }
        $stmt = $this->pdo->prepare("SELECT identity_id,resource_kind,resource_id,
            relation,verified_binding_at,revoked_at FROM `{$this->table}`
            WHERE resource_kind=:kind AND resource_id=:resource LIMIT 1");
        $stmt->execute([':kind'=>$resourceKind, ':resource'=>$resourceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) { return null; }
        $row['resource_id'] = (int)$row['resource_id'];
        return $row;
    }

    public function may_edit(string $identityId, string $resourceKind, int $resourceId): bool {
        return BadAround_Identity_Ownership_Policy::may_edit(
            $identityId,$resourceKind,$resourceId,
            $this->find_for_resource($resourceKind,$resourceId) ?? []
        );
    }

    private static function validate_resource(string $identity, string $kind, int $id): void {
        if (!preg_match('/^[A-Za-z0-9_-]{8,128}$/D', $identity)
            || !in_array($kind, ['report','contribution','comment','sentinel'],true)
            || $id <= 0) { throw new InvalidArgumentException('Invalid binding'); }
    }
}
