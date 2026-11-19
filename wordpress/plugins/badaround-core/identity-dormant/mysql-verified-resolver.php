<?php
/**
 * BA-IDENTITY-01N: persistent verified-identity resolver for OTP login.
 * Dormant. No WordPress hooks. Never issue a session using a public alias,
 * caller-supplied identity ID or unverified email.
 */
declare(strict_types=1);
require_once __DIR__.'/verification-coordinator.php';

final class BadAround_Identity_Mysql_Verified_Resolver implements BadAround_Identity_Verified_Resolver {
    public function __construct(private PDO $db,private string $table) {
        if (!preg_match('/^[A-Za-z0-9_]+$/D',$table)) {
            throw new InvalidArgumentException('Invalid identity table name');
        }
        $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    }

    public function find_verified_identity(string $emailLookup): ?string {
        if (!preg_match('/^[a-f0-9]{64}$/D',$emailLookup)) { return null; }
        try {
            $stmt=$this->db->prepare("SELECT identity_id FROM `{$this->table}`
                WHERE email_lookup=:lookup AND verified_at > 0 AND revoked_at IS NULL
                LIMIT 1");
            $stmt->execute([':lookup'=>$emailLookup]);
            $id=$stmt->fetchColumn();
            return is_string($id) && preg_match('/^[A-Za-z0-9_-]{8,128}$/D',$id)
                ? $id : null;
        } catch(Throwable $error) {
            // Database failures must never authenticate or reveal identity.
            return null;
        }
    }
}
