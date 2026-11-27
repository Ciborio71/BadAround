<?php
/**
 * BA-IDENTITY-01C — isolated, transactional fixed-window rate limiter.
 * Never included by WordPress; uses a dedicated ephemeral table in tests.
 * Caller must use a server-derived IP (trusted proxy config) and a HMAC
 * email lookup, never a raw email or arbitrary caller-provided identity.
 */
declare(strict_types=1);
require_once __DIR__ . '/challenge-primitives.php';

final class BadAround_Identity_Mysql_Rate_Limiter {
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
     * Transactional global budget per HMAC bucket. Returns false after limit.
     * Scope must be an allowlisted purpose: login, enrollment, verification.
     * Bucket must be an opaque SHA-256 digest, never PII.
     */
    public function consume(string $purpose, string $bucket, int $now, int $limit, int $window): bool {
        if (!in_array($purpose, ['login','enrollment','verification','access'], true)
            || !preg_match('/^[a-f0-9]{64}$/D', $bucket)
            || $now <= 0 || $limit < 1 || $limit > 100 || $window < 60 || $window > 86400) {
            throw new InvalidArgumentException('Invalid rate-limit input');
        }
        if ($this->pdo->inTransaction()) { throw new LogicException('Unexpected transaction'); }
        $start = intdiv($now, $window) * $window;
        $this->pdo->beginTransaction();
        try {
            // Unique key on purpose+bucket+window_start serializes concurrent usage.
            $stmt = $this->pdo->prepare("INSERT IGNORE INTO `{$this->table}`
                (purpose,bucket,window_start,hits,expires_at)
                VALUES (:purpose,:bucket,:start,0,:expiry)");
            $stmt->execute([
                ':purpose'=>$purpose, ':bucket'=>$bucket, ':start'=>$start,
                ':expiry'=>$start+$window
            ]);
            $stmt = $this->pdo->prepare("SELECT hits FROM `{$this->table}`
                WHERE purpose=:purpose AND bucket=:bucket
                    AND window_start=:start FOR UPDATE");
            $stmt->execute([':purpose'=>$purpose, ':bucket'=>$bucket, ':start'=>$start]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row === false || (int)$row['hits'] >= $limit) {
                $this->pdo->rollBack();
                return false;
            }
            $stmt = $this->pdo->prepare("UPDATE `{$this->table}` SET hits=hits+1
                WHERE purpose=:purpose AND bucket=:bucket AND window_start=:start");
            $stmt->execute([':purpose'=>$purpose,':bucket'=>$bucket,':start'=>$start]);
            if ($stmt->rowCount() !== 1) { throw new RuntimeException('Rate limit conflict'); }
            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); }
            throw $e; // caller must fail closed: do NOT send an OTP on store failure
        }
    }

    public static function bucket(string $purpose, string $type, string $value, string $serverKey): string {
        if (!in_array($purpose, ['login','enrollment','verification','access'],true)
            || !in_array($type, ['email','ip'],true) || $value === '') {
            throw new InvalidArgumentException('Invalid rate limit dimensions');
        }
        return BadAround_Identity_Challenge_Primitives::digest(
            'rate-limit-'.$purpose, $type, $value, $serverKey
        );
    }
}
