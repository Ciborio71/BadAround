<?php
/**
 * BA-IDENTITY-01K: guarded additive schema recovery on disposable databases.
 * This component is NOT a live rollback controller. It has no WP hooks.
 *
 * Never drop non-empty Identity tables. Even empty-table rollback requires a
 * pre-migration inventory and a separately authorized, recoverable backup.
 */
declare(strict_types=1);
require_once __DIR__.'/schema-candidate.php';

final class BadAround_Identity_Schema_Recovery_Policy {
    public static function table_names(string $prefix): array {
        // Reuse the exact allowlisted list from the migration candidate.
        BadAround_Identity_Schema_Candidate::statements($prefix);
        return array_map(
            static fn(string $suffix): string => $prefix . $suffix,
            BadAround_Identity_Schema_Candidate::SUFFIXES
        );
    }

    /** Inspect only. No removal and no DDL side effects. */
    public static function inspect(PDO $db,string $prefix): array {
        $tables=self::table_names($prefix);
        $status=[];
        $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
        foreach($tables as $table) {
            $exists=$db->prepare("SELECT COUNT(*) FROM information_schema.TABLES
                WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:name");
            $exists->execute([':name'=>$table]);
            if ((int)$exists->fetchColumn()!==1) {
                $status[$table]=['exists'=>false,'rows'=>null]; continue;
            }
            // Table identifiers cannot be parametrized; prefix is strictly validated.
            $count=$db->query("SELECT COUNT(*) FROM `{$table}`");
            $status[$table]=['exists'=>true,'rows'=>(int)$count->fetchColumn()];
        }
        return $status;
    }

    /**
     * CI-only rollback rehearsal. Never invoke from live WordPress.
     * To prevent accidental reuse requires exact disposable database and
     * an explicit second safety switch. Refuses nonempty tables.
     */
    public static function drop_empty_disposable(PDO $db,string $prefix,bool $allow): void {
        $dsn=(string)$db->query("SELECT DATABASE()")->fetchColumn();
        if (!$allow || $dsn!=='ba_identity_ci' || $prefix!=='ba_recovery_ci_') {
            throw new RuntimeException('Recovery denied outside disposable CI fixture');
        }
        $inventory=self::inspect($db,$prefix);
        foreach($inventory as $table=>$state) {
            if ($state['exists'] && $state['rows']!==0) {
                throw new RuntimeException('Nonempty identity table: preserve data');
            }
        }
        foreach(array_reverse(array_keys($inventory)) as $table) {
            if ($inventory[$table]['exists']) {
                $db->exec("DROP TABLE `{$table}`");
            }
        }
    }
}
