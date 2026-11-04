<?php
/** Dormant adapter. The existing PDO store implements durable recovery
 * persistence; this wrapper implements the wizard's narrow journal contract.
 * No hooks, table creation or writes occur on file inclusion.
 */
declare(strict_types=1);
require_once __DIR__ . '/durable-ownership-handoff.php';
require_once __DIR__ . '/mysql-ownership-recovery-store.php';

final class BadAround_Identity_Recovery_Store_Bridge implements BadAround_Identity_Recovery_Journal {
    public function __construct(private BadAround_Identity_Mysql_Ownership_Recovery_Store $store) {}
    public function create(array $intent):bool {
        return $this->store->create($intent);
    }
    public function load(string $operationId):?array {
        return $this->store->load($operationId);
    }
    public function transition(string $operationId,int $revision,string $outcome,int $now):bool {
        return $this->store->transition($operationId,$revision,$outcome,$now);
    }
}
