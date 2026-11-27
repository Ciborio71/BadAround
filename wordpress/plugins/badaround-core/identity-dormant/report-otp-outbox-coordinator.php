<?php
/** BA-REPORT-IDENTITY-04: server-owned outbox state transitions. */
declare(strict_types=1);
require_once __DIR__.'/report-otp-outbox-store.php';
final class BadAround_Report_Otp_Outbox_Coordinator {
    public function __construct(private BadAround_Report_Otp_Outbox_Store $store){}
    public function begin_verified(string $op,string $challenge,string $submission,string $lookup,int $now):bool {
        try {
            $row=BadAround_Report_Otp_Reservation_Policy::verified($challenge,$submission,$lookup,$now);
            $row['operation_id']=$op;
            return $this->store->create($row);
        }catch(Throwable $e){return false;}
    }
    public function reserve(string $op,string $submission,string $lookup,int $now):bool {
        try {
            $row=$this->store->read($op);
            if(!$row)return false;
            $next=BadAround_Report_Otp_Reservation_Policy::reserve($row,$submission,$lookup,$now);
            return $this->store->transition($op,$row['version'],$next);
        }catch(Throwable $e){return false;}
    }
    public function link(string $op,array $trustedCanonical,int $now):bool {
        try {
            $row=$this->store->read($op);
            if(!$row)return false;
            $next=BadAround_Report_Otp_Reservation_Policy::link($row,$trustedCanonical,$now);
            return $this->store->transition($op,$row['version'],$next);
        }catch(Throwable $e){return false;}
    }
    public function quarantine(string $op,int $now):bool {
        try {
            $row=$this->store->read($op);
            if(!$row)return false;
            $next=BadAround_Report_Otp_Reservation_Policy::flag_for_review($row,$now);
            return $this->store->transition($op,$row['version'],$next);
        }catch(Throwable $e){return false;}
    }
    public function confirmed(string $op):bool {
        try {
            $row=$this->store->read($op);
            return $row!==null && BadAround_Report_Otp_Reservation_Policy::can_confirm_received($row);
        }catch(Throwable $e){return false;}
    }
}
