<?php
/** BA-REPORT-IDENTITY-06: native UUID lock and outbox adapters, no hooks. */
declare(strict_types=1);
require_once __DIR__.'/report-otp-wordpress-reconciler.php';
require_once __DIR__.'/report-otp-outbox-coordinator.php';
require_once __DIR__.'/report-otp-canonical-reader.php';

final class BadAround_Report_Otp_WordPress_Native_Lock implements BadAround_Report_Otp_Native_Lock {
    public function __construct(private object $repository) {
        if(!method_exists($repository,'acquire_native_lock')
            || !method_exists($repository,'release_native_lock')){
            throw new InvalidArgumentException('Missing native lock');
        }
    }
    public function acquire(string $submissionId):bool {
        return $this->repository->acquire_native_lock($submissionId,0)===true;
    }
    public function release(string $submissionId):void {
        $this->repository->release_native_lock($submissionId);
    }
}
final class BadAround_Report_Otp_Outbox_Bridge implements BadAround_Report_Otp_Reconcile_Outbox {
    public function __construct(
        private BadAround_Report_Otp_Outbox_Store $store,
        private BadAround_Report_Otp_Outbox_Coordinator $coordinator
    ){}
    public function read(string $op):?array{return $this->store->read($op);}
    public function link(string $op,array $canonical,int $now):bool{
        return $this->coordinator->link($op,$canonical,$now);
    }
    public function confirmed(string $op):bool{return $this->coordinator->confirmed($op);}
}
final class BadAround_Report_Otp_Canonical_Bridge implements BadAround_Report_Otp_Reconcile_Reader {
    public function __construct(private BadAround_Report_Otp_Canonical_Reader $reader){}
    public function completed(string $submission):?array{
        return $this->reader->completed($submission);
    }
}
