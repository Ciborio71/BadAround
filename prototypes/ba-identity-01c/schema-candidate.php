<?php
/**
 * BA-IDENTITY-01G: additive schema candidate, not production installer.
 * No automatic hooks, no live DB writes, no schema version changes.
 * Apply ONLY to disposable CI database pending live backup/recovery approval.
 */
declare(strict_types=1);
final class BadAround_Identity_Schema_Candidate {
    public const SUFFIXES = [
        'ba_identity_challenges','ba_identity_pending','ba_identities',
        'ba_identity_sessions','ba_identity_rate_limits','ba_identity_ownership',
        'ba_identity_recovery','ba_identity_moderation','ba_identity_wp_actors'
    ];
    public static function statements(string $prefix): array {
        if (!preg_match('/^[A-Za-z0-9_]{1,48}$/D',$prefix)) {
            throw new InvalidArgumentException('Invalid WordPress table prefix');
        }
        $specs=[
            'ba_identity_challenges' => 'challenge_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
              email_lookup CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
              code_digest CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
              created_at BIGINT UNSIGNED NOT NULL, expires_at BIGINT UNSIGNED NOT NULL,
              attempts TINYINT UNSIGNED NOT NULL DEFAULT 0, consumed_at BIGINT UNSIGNED NULL,
              version BIGINT UNSIGNED NOT NULL DEFAULT 0, KEY expiry (expires_at)',
            'ba_identity_pending' => 'challenge_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
              email_lookup CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
              protected_profile TEXT NOT NULL, created_at BIGINT UNSIGNED NOT NULL,
              verified_at BIGINT UNSIGNED NULL, KEY pending_created (created_at)',
            'ba_identities' => 'identity_id VARCHAR(128) NOT NULL PRIMARY KEY,
              email_lookup CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
              protected_profile TEXT NOT NULL, verified_at BIGINT UNSIGNED NOT NULL,
              revoked_at BIGINT UNSIGNED NULL, UNIQUE KEY email_lookup (email_lookup)',
            'ba_identity_sessions' => 'session_digest CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
              identity_id VARCHAR(128) NOT NULL, created_at BIGINT UNSIGNED NOT NULL,
              last_seen_at BIGINT UNSIGNED NOT NULL, expires_at BIGINT UNSIGNED NOT NULL,
              revoked_at BIGINT UNSIGNED NULL, KEY identity (identity_id), KEY expiry (expires_at)',
            'ba_identity_rate_limits' => 'purpose VARCHAR(24) NOT NULL,
              bucket CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
              window_start BIGINT UNSIGNED NOT NULL, hits INT UNSIGNED NOT NULL DEFAULT 0,
              expires_at BIGINT UNSIGNED NOT NULL,
              PRIMARY KEY (purpose,bucket,window_start), KEY expiry (expires_at)',
            'ba_identity_ownership' => 'identity_id VARCHAR(128) NOT NULL,
              resource_kind VARCHAR(32) NOT NULL, resource_id BIGINT UNSIGNED NOT NULL,
              relation VARCHAR(32) NOT NULL, verified_binding_at BIGINT UNSIGNED NOT NULL,
              revoked_at BIGINT UNSIGNED NULL,
              UNIQUE KEY resource (resource_kind,resource_id), KEY identity (identity_id)',
            'ba_identity_recovery' => 'operation_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
              identity_id VARCHAR(128) NOT NULL, report_id BIGINT UNSIGNED NOT NULL,
              event_id BIGINT UNSIGNED NOT NULL, state VARCHAR(24) NOT NULL,
              attempts INT UNSIGNED NOT NULL DEFAULT 0, created_at BIGINT UNSIGNED NOT NULL,
              resolved_at BIGINT UNSIGNED NULL, revision BIGINT UNSIGNED NOT NULL DEFAULT 0,
              UNIQUE KEY report (report_id), KEY state (state)',
            'ba_identity_moderation' => 'intent_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
              kind VARCHAR(16) NOT NULL, identity_id VARCHAR(128) NOT NULL,
              resource_id BIGINT UNSIGNED NOT NULL, payload LONGBLOB NOT NULL,
              nonce VARBINARY(12) NOT NULL, tag VARBINARY(16) NOT NULL,
              state VARCHAR(16) NOT NULL, version INT UNSIGNED NOT NULL DEFAULT 0,
              created_at BIGINT UNSIGNED NOT NULL, moderator_id VARCHAR(128) NULL,
              moderated_at BIGINT UNSIGNED NULL, KEY resource (kind,resource_id), KEY pending (state,created_at)',
            'ba_identity_wp_actors' => 'wp_user_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
              identity_id VARCHAR(128) NOT NULL, verified_at BIGINT UNSIGNED NOT NULL,
              revoked_at BIGINT UNSIGNED NULL, version INT UNSIGNED NOT NULL DEFAULT 0,
              UNIQUE KEY identity (identity_id)'
        ];
        $sql=[];
        foreach (self::SUFFIXES as $suffix) {
            $table=$prefix.$suffix;
            $sql[$suffix]="CREATE TABLE IF NOT EXISTS `{$table}` (".$specs[$suffix].
                ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        }
        return $sql;
    }
}
