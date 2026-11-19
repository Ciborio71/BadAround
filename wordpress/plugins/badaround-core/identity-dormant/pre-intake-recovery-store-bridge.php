<?php
/** Dormant adapter; no hooks, SQL or side effects on file inclusion. */
declare(strict_types=1);
require_once __DIR__.'/pre-intake-recovery-worker.php';
require_once __DIR__.'/mysql-pre-intake-store.php';

final class BadAround_Identity_Pre_Intake_Recovery_Store_Bridge
    implements BadAround_Identity_Pre_Intake_Recovery_Reader {
    public function __construct(private BadAround_Identity_Mysql_Pre_Intake_Store $store) {}
    public function find_by_submission(string $submissionId):?array {
        return $this->store->find_by_submission($submissionId);
    }
    public function link(string $operationId,string $submissionId,int $reportId,int $eventId,int $now):bool {
        return $this->store->link($operationId,$submissionId,$reportId,$eventId,$now);
    }
}
