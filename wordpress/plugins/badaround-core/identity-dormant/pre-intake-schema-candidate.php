<?php
/**
 * BA-IDENTITY-WIZARD-10 — explicit QA schema candidate, NOT an installer.
 *
 * No DDL execution, hooks, WordPress registration or connection on include.
 * A release operator must verify backups, target prefix, exact database,
 * migration window and rollback before executing this statement in QA.
 */
declare(strict_types=1);

final class BadAround_Identity_Pre_Intake_Schema {
    public const SUFFIX = 'ba_identity_pre_intake';

    public static function table(string $prefix):string {
        if (preg_match('/^[A-Za-z0-9_]{1,48}$/D',$prefix)!==1)
            throw new InvalidArgumentException('Invalid WordPress table prefix');
        return $prefix.self::SUFFIX;
    }

    public static function create_sql(string $prefix):string {
        $table=self::table($prefix);
        return "CREATE TABLE `{$table}` (
            operation_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            submission_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            identity_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            state VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            report_id BIGINT UNSIGNED NULL,
            event_id BIGINT UNSIGNED NULL,
            created_at BIGINT UNSIGNED NOT NULL,
            linked_at BIGINT UNSIGNED NULL,
            revision BIGINT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (operation_id),
            UNIQUE KEY uq_ba_intake_submission (submission_id),
            UNIQUE KEY uq_ba_intake_report (report_id),
            KEY idx_ba_intake_state_created (state,created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    }

    /**
     * Fail closed when a pre-existing table differs from the approved
     * schema. Never alter or drop it automatically.
     */
    public static function inspect(PDO $db,string $prefix):bool {
        $table=self::table($prefix);
        $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
        $name=$db->prepare("SELECT ENGINE FROM information_schema.TABLES
            WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table");
        $name->execute([':table'=>$table]);
        if ($name->fetchColumn()!=='InnoDB')return false;
        $columns=$db->prepare("SELECT COLUMN_NAME,DATA_TYPE,IS_NULLABLE
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table
            ORDER BY ORDINAL_POSITION");
        $columns->execute([':table'=>$table]);
        $actual=[];
        foreach($columns->fetchAll(PDO::FETCH_ASSOC) as $row)
            $actual[$row['COLUMN_NAME']]=[$row['DATA_TYPE'],$row['IS_NULLABLE']];
        $expected=[
            'operation_id'=>['char','NO'],
            'submission_id'=>['char','NO'],
            'identity_id'=>['varchar','NO'],
            'state'=>['varchar','NO'],
            'report_id'=>['bigint','YES'],
            'event_id'=>['bigint','YES'],
            'created_at'=>['bigint','NO'],
            'linked_at'=>['bigint','YES'],
            'revision'=>['bigint','NO'],
        ];
        if ($actual!==$expected)return false;
        $keys=$db->prepare("SELECT INDEX_NAME,NON_UNIQUE,COLUMN_NAME,SEQ_IN_INDEX
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table
            ORDER BY INDEX_NAME,SEQ_IN_INDEX");
        $keys->execute([':table'=>$table]);
        $idx=[];
        foreach($keys->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $idx[$row['INDEX_NAME']]['unique']=(int)$row['NON_UNIQUE']===0;
            $idx[$row['INDEX_NAME']]['columns'][]=$row['COLUMN_NAME'];
        }
        return ($idx['PRIMARY']??null)===['unique'=>true,'columns'=>['operation_id']]
            && ($idx['uq_ba_intake_submission']??null)===['unique'=>true,'columns'=>['submission_id']]
            && ($idx['uq_ba_intake_report']??null)===['unique'=>true,'columns'=>['report_id']]
            && ($idx['idx_ba_intake_state_created']??null)===['unique'=>false,'columns'=>['state','created_at']];
    }
}
