<?php
/**
 * BA-IDENTITY-01C: prototype PDO/MySQL repository.
 * This file is NOT included by BadAround Core and requires explicitly
 * provisioned, versioned tables. Never call from production yet.
 */
declare(strict_types=1);
require_once __DIR__ . '/challenge-store.php';
require_once __DIR__ . '/otp-issuer.php';

final class BadAround_Identity_Mysql_Challenge_Store implements BadAround_Identity_Challenge_Store, BadAround_Identity_Challenge_Writer {
    private PDO $db;
    private string $table;

    public function __construct(PDO $db, string $table) {
        if (!preg_match('/^[A-Za-z0-9_]+$/D', $table)) {
            throw new InvalidArgumentException('Invalid identifier');
        }
        $this->db = $db;
        $this->table = $table;
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function insert(array $record): void {
        $sql = "INSERT INTO `{$this->table}`
                (challenge_id,email_lookup,code_digest,created_at,expires_at,
                 attempts,consumed_at,version)
                VALUES (:id,:email,:digest,:created,:expires,0,NULL,0)";
        $statement = $this->db->prepare($sql);
        $statement->execute([
            ':id'=>$record['challenge_id'],
            ':email'=>$record['email_lookup'],
            ':digest'=>$record['code_digest'],
            ':created'=>(int)$record['created_at'],
            ':expires'=>(int)$record['expires_at'],
        ]);
    }

    /** Expire an unsent OTP. Idempotent: a missing/already-consumed row stays unusable. */
    public function invalidate(string $challengeId, int $now): void {
        if (!preg_match('/^[a-f0-9]{32}$/D', $challengeId) || $now <= 0) {
            throw new InvalidArgumentException('Invalid challenge invalidation');
        }
        $statement = $this->db->prepare(
            "UPDATE `{$this->table}` SET consumed_at=:now, version=version+1
             WHERE challenge_id=:id AND consumed_at IS NULL"
        );
        $statement->execute([':now'=>$now, ':id'=>$challengeId]);
    }

    public function load(string $challengeId): ?array {
        $statement = $this->db->prepare(
            "SELECT challenge_id,email_lookup,code_digest,created_at,expires_at,
                    attempts,consumed_at,version FROM `{$this->table}`
             WHERE challenge_id=:id LIMIT 1"
        );
        $statement->execute([':id'=>$challengeId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }
        foreach (['created_at','expires_at','attempts','version'] as $name) {
            $row[$name] = (int)$row[$name];
        }
        $row['consumed_at'] = $row['consumed_at'] === null ? null : (int)$row['consumed_at'];
        return $row;
    }

    /**
     * Single SQL conditional UPDATE: stale writers cannot consume twice.
     * The underlying table must use InnoDB or another transactional engine.
     */
    public function compare_and_swap(string $challengeId, int $expectedVersion, array $newRecord): bool {
        if (($newRecord['challenge_id'] ?? null) !== $challengeId ||
            ($newRecord['version'] ?? null) !== $expectedVersion + 1 ||
            !array_key_exists('consumed_at', $newRecord)) {
            throw new InvalidArgumentException('Invalid CAS transition');
        }
        $sql = "UPDATE `{$this->table}`
            SET attempts=:attempts, consumed_at=:consumed,
                version=version+1
            WHERE challenge_id=:id AND version=:expected
                AND consumed_at IS NULL";
        $statement = $this->db->prepare($sql);
        $statement->bindValue(':attempts', (int)$newRecord['attempts'], PDO::PARAM_INT);
        if ($newRecord['consumed_at'] === null) {
            $statement->bindValue(':consumed', null, PDO::PARAM_NULL);
        } else {
            $statement->bindValue(':consumed', (int)$newRecord['consumed_at'], PDO::PARAM_INT);
        }
        $statement->bindValue(':id', $challengeId, PDO::PARAM_STR);
        $statement->bindValue(':expected', $expectedVersion, PDO::PARAM_INT);
        $statement->execute();
        return $statement->rowCount() === 1;
    }
}
