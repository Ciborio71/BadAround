<?php
/**
 * BA-IDENTITY-02G — schema proposal for approved public comments.
 * No WordPress hooks or automatic migrations. DO NOT run against production
 * without a separate backup, review and explicit WPVibe DDL approval.
 */
declare(strict_types=1);
final class BadAround_Identity_Public_Comment_Schema {
    public static function statement(string $prefix):string {
        if (!preg_match('/^[A-Za-z0-9_]{1,48}$/D',$prefix)) {
            throw new InvalidArgumentException('Invalid WP prefix');
        }
        $table=$prefix.'ba_identity_public_comments';
        return "CREATE TABLE IF NOT EXISTS `{$table}` (
            comment_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            intent_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            event_id BIGINT UNSIGNED NOT NULL,
            parent_id BIGINT UNSIGNED NULL,
            display_name VARCHAR(80) NOT NULL,
            body TEXT NOT NULL,
            published_at BIGINT UNSIGNED NOT NULL,
            status VARCHAR(16) NOT NULL DEFAULT 'visible',
            PRIMARY KEY (comment_id),
            UNIQUE KEY intent (intent_id),
            KEY event_order (event_id,published_at),
            KEY parent (parent_id),
            KEY visibility (status,event_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    }
}
