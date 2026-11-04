<?php
/**
 * Read-only canonical owner adapter for dormant BA-IDENTITY recovery.
 * Does not register hooks, open DB connections or grant ownership on include.
 */
declare(strict_types=1);
require_once __DIR__ . '/ownership-recovery-reconciler.php';
require_once __DIR__ . '/mysql-ownership-store.php';

final class BadAround_Identity_Recovery_Owner_Bridge implements BadAround_Identity_Recovery_Owner_Reader {
    public function __construct(private BadAround_Identity_Mysql_Ownership_Store $store) {}
    public function find_for_report(int $reportId): ?array {
        if ($reportId <= 0) return null;
        return $this->store->find_for_resource('report', $reportId);
    }
}
