<?php
/** BA-REPORT-IDENTITY-02B pure outbox state transitions and crash safety. */
declare(strict_types=1);
require __DIR__.'/report-otp-reservation-policy.php';
function assertion(bool $ok,string $label):void {
    if (!$ok)throw new RuntimeException('FAIL '.$label);
    echo 'PASS '.$label.PHP_EOL;
}
$uuid='12345678-1234-4234-8234-123456789abc';
$email=str_repeat('a',64);$now=1762308000;
$record=BadAround_Report_Otp_Reservation_Policy::verified(str_repeat('b',32),$uuid,$email,$now);
assertion($record['state']==='verified' && $record['version']===0,'verified intent initialized');
foreach([[$uuid,str_repeat('c',64)],['87654321-4321-4234-8234-123456789abc',$email]] as [$id,$digest]){
    $blocked=false;
    try{BadAround_Report_Otp_Reservation_Policy::reserve($record,$id,$digest,$now+1);}
    catch(DomainException $e){$blocked=true;}
    assertion($blocked,'cannot reserve with mismatching identity or submission');
}
$reserved=BadAround_Report_Otp_Reservation_Policy::reserve($record,$uuid,$email,$now+1);
assertion($reserved['state']==='reserved' && $reserved['version']===1,
    'single guarded reservation');
$replay=false;
try{BadAround_Report_Otp_Reservation_Policy::reserve($reserved,$uuid,$email,$now+2);}
catch(DomainException $e){$replay=true;}
assertion($replay,'crash or duplicate request cannot reserve twice without reconciliation');
$receipt=['submission_id'=>$uuid,'report_id'=>401,'event_id'=>402,'duplicate'=>false];
foreach([['duplicate'=>true],['submission_id'=>'87654321-4321-4234-8234-123456789abc'],['event_id'=>0]] as $wrong){
    $blocked=false;
    try{BadAround_Report_Otp_Reservation_Policy::link($reserved,array_replace($receipt,$wrong),$now+3);}
    catch(DomainException $e){$blocked=true;}
    assertion($blocked,'untrusted or incomplete canonical receipt denied');
}
$linked=BadAround_Report_Otp_Reservation_Policy::link($reserved,$receipt,$now+3);
assertion(BadAround_Report_Otp_Reservation_Policy::can_confirm_received($linked)
    && $linked['version']===2,'canonical report and event verified before success');
$repeat=false;
try{BadAround_Report_Otp_Reservation_Policy::link($linked,$receipt,$now+4);}
catch(DomainException $e){$repeat=true;}
assertion($repeat,'linked receipt not processed twice');
$review=BadAround_Report_Otp_Reservation_Policy::flag_for_review($reserved,$now+5);
assertion($review['state']==='needs_review'
    && !BadAround_Report_Otp_Reservation_Policy::can_confirm_received($review),
    'ambiguous interrupted report never confirmed or automatically retried');
echo "ALL_REPORT_RESERVATION_POLICY_TESTS_PASS\n";
