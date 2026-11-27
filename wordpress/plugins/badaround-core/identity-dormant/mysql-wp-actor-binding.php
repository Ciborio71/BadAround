<?php
/**
 * Dormant persistent WordPress ↔ BadAround Identity binding.
 * Only a trusted administrator/verified enrollment coordinator can provision
 * records. Never bind accounts by email equality or HTTP-supplied identity.
 * No WordPress hooks, migrations or routes registered.
 */
declare(strict_types=1);
require_once __DIR__.'/wordpress-authority.php';

final class BadAround_Identity_Mysql_WP_Actor_Binding implements BadAround_Identity_WP_Actor_Binding {
    public function __construct(private PDO $db,private string $table) {
        if (!preg_match('/^[A-Za-z0-9_]+$/D',$table)) {
            throw new InvalidArgumentException('Invalid table');
        }
        $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    }

    public function matches_current_user(int $wpUserId,string $identityId): bool {
        if ($wpUserId<=0 || !preg_match('/^[A-Za-z0-9_-]{8,128}$/D',$identityId)) {
            return false;
        }
        try {
            $stmt=$this->db->prepare("SELECT identity_id FROM `{$this->table}`
                WHERE wp_user_id=:wp AND identity_id=:identity
                  AND verified_at IS NOT NULL AND revoked_at IS NULL LIMIT 1");
            $stmt->execute([':wp'=>$wpUserId,':identity'=>$identityId]);
            $row=$stmt->fetch(PDO::FETCH_ASSOC);
            return is_array($row) && hash_equals($identityId,(string)$row['identity_id']);
        } catch(Throwable $error) {
            return false;
        }
    }

    /**
     * Internal only. Caller must prove WordPress actor identity, explicit
     * BadAround identity association and authorization; this method does NOT
     * establish those proofs. UNIQUE keys disallow account/identity reuse.
     */
    public function grant_verified(int $wpUserId,string $identityId,int $verifiedAt): bool {
        if ($wpUserId<=0 || $verifiedAt<=0
            || !preg_match('/^[A-Za-z0-9_-]{8,128}$/D',$identityId)) {
            throw new InvalidArgumentException('Invalid binding');
        }
        try {
            $stmt=$this->db->prepare("INSERT INTO `{$this->table}`
                (wp_user_id,identity_id,verified_at,revoked_at,version)
                VALUES (:wp,:identity,:verified,NULL,0)");
            $stmt->execute([':wp'=>$wpUserId,':identity'=>$identityId,':verified'=>$verifiedAt]);
            return $stmt->rowCount()===1;
        } catch(PDOException $error) {
            if ($error->getCode()==='23000') { return false; }
            throw $error;
        }
    }

    /** Revoke with a version guard; stale replays fail closed. */
    public function revoke(int $wpUserId,string $identityId,int $version,int $now): bool {
        if ($wpUserId<=0 || $version<0 || $now<=0
            || !preg_match('/^[A-Za-z0-9_-]{8,128}$/D',$identityId)) {
            return false;
        }
        try {
            $stmt=$this->db->prepare("UPDATE `{$this->table}`
                SET revoked_at=:now,version=version+1
                WHERE wp_user_id=:wp AND identity_id=:identity
                    AND version=:version AND verified_at IS NOT NULL
                    AND revoked_at IS NULL");
            $stmt->execute([':now'=>$now,':wp'=>$wpUserId,
                ':identity'=>$identityId,':version'=>$version]);
            return $stmt->rowCount()===1;
        } catch(Throwable $error) { return false; }
    }
}
