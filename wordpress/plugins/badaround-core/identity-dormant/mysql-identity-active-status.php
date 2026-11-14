<?php
/** Dormant MySQL identity-status adapter; read-only queries, no WordPress bootstrap. */
declare(strict_types=1);
require_once __DIR__ . '/identity-active-status.php';
final class BadAround_Identity_Mysql_Active_Status implements BadAround_Identity_Active_Status {
    public function __construct(private PDO $pdo, private string $table) {
        if (!preg_match('/^[A-Za-z0-9_]+$/D',$table)) {
            throw new InvalidArgumentException('Invalid identity table');
        }
        $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    }
    public function is_active_verified(string $identityId): bool {
        if (!preg_match('/^[A-Za-z0-9_-]{8,128}$/D',$identityId)) { return false; }
        $stmt=$this->pdo->prepare("SELECT 1 FROM `{$this->table}`
            WHERE identity_id=:id AND verified_at IS NOT NULL
              AND revoked_at IS NULL LIMIT 1");
        $stmt->execute([':id'=>$identityId]);
        return $stmt->fetchColumn() !== false;
    }
}
