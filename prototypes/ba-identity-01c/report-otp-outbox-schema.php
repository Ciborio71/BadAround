<?php
/**
 * BA-REPORT-IDENTITY-04 — durable reservation outbox schema candidate.
 * Isolated CI-only DDL. DO NOT install to live WordPress automatically.
 */
declare(strict_types=1);
final class BadAround_Report_Otp_Outbox_Schema {
    public static function statement(string $prefix):string {
        if (!preg_match('/^[A-Za-z0-9_]{1,48}$/D',$prefix)) {
            throw new InvalidArgumentException('Invalid table prefix');
        }
        $table=$prefix.'ba_report_otp_outbox';
        return "CREATE TABLE IF NOT EXISTS `{$table}` (
            operation_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            challenge_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            submission_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            email_lookup CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            state VARCHAR(24) NOT NULL,
            revision INT UNSIGNED NOT NULL DEFAULT 0,
            attempts INT UNSIGNED NOT NULL DEFAULT 0,
            created_at BIGINT UNSIGNED NOT NULL,
            reserved_at BIGINT UNSIGNED NULL,
            report_id BIGINT UNSIGNED NULL,
            event_id BIGINT UNSIGNED NULL,
            resolved_at BIGINT UNSIGNED NULL,
            PRIMARY KEY(operation_id),
            UNIQUE KEY challenge (challenge_id),
            UNIQUE KEY submission (submission_id),
            KEY pending (state,reserved_at),
            KEY report (report_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    }
}
