<?php
/**
 * BA-IDENTITY-01C dormant PDO/MySQL session persistence.
 * No WordPress bootstrap and no cookie emission.
 */
declare(strict_types=1);
require_once __DIR__ . '/session-policy.php';

final class BadAround_Identity_Mysql_Session_Store implements BadAround_Identity_Session_Store {
    private PDO $pdo;
    private string $table;

    public function __construct(PDO $pdo, string $table) {
        if (!preg_match('/^[A-Za-z0-9_]+$/D', $table)) {
            throw new InvalidArgumentException('Invalid identifier');
        }
        $this->pdo = $pdo;
        $this->table = $table;
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function insert(array $record): void {
        $sql = "INSERT INTO `{$this->table}`
            (session_digest,identity_id,created_at,last_seen_at,expires_at,revoked_at)
            VALUES (:digest,:identity,:created,:seen,:expires,NULL)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':digest'=>$record['session_digest'],
            ':identity'=>$record['identity_id'],
            ':created'=>$record['created_at'],
            ':seen'=>$record['last_seen_at'],
            ':expires'=>$record['expires_at']
        ]);
    }

    public function find(string $digest): ?array {
        $stmt = $this->pdo->prepare("SELECT session_digest,identity_id,created_at,
            last_seen_at,expires_at,revoked_at FROM `{$this->table}`
            WHERE session_digest=:digest LIMIT 1");
        $stmt->execute([':digest'=>$digest]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) { return null; }
        foreach (['created_at','last_seen_at','expires_at'] as $key) {
            $row[$key] = (int)$row[$key];
        }
        $row['revoked_at'] = $row['revoked_at'] === null ? null : (int)$row['revoked_at'];
        return $row;
    }

    public function revoke(string $digest, int $now): bool {
        $stmt = $this->pdo->prepare("UPDATE `{$this->table}`
            SET revoked_at=:now WHERE session_digest=:digest AND revoked_at IS NULL");
        $stmt->execute([':now'=>$now,':digest'=>$digest]);
        return $stmt->rowCount() === 1;
    }
}
