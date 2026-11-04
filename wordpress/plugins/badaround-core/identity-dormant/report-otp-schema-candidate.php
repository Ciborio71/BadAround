<?php
/**
 * BA-REPORT-IDENTITY-02 — proposed account-free report OTP repository.
 * New table only in disposable CI. No WordPress hooks, live migrations,
 * email sending, account creation, or public intake changes.
 */
declare(strict_types=1);
final class BadAround_Report_Otp_Schema {
    public static function statement(string $prefix):string {
        if (!preg_match('/^[A-Za-z0-9_]{1,48}$/D',$prefix)) {
            throw new InvalidArgumentException('Invalid WordPress prefix');
        }
        $table=$prefix.'ba_report_otp';
        return "CREATE TABLE IF NOT EXISTS `{$table}` (
            challenge_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            submission_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            email_lookup CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            code_digest CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            purpose VARCHAR(24) NOT NULL,
            created_at BIGINT UNSIGNED NOT NULL,
            expires_at BIGINT UNSIGNED NOT NULL,
            attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
            verified_at BIGINT UNSIGNED NULL,
            consumed_at BIGINT UNSIGNED NULL,
            version INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY(challenge_id),
            UNIQUE KEY submission (submission_id),
            KEY expiry (expires_at),
            KEY email_lookup (email_lookup)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    }
}
