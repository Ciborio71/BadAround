<?php
declare(strict_types=1);
require __DIR__.'/ownership-recovery-policy.php';
function rec_assert(bool $value,string $message): void {
    if (!$value) { throw new RuntimeException('FAIL: '.$message); }
    echo 'PASS: '.$message.PHP_EOL;
}
function rec_reject(callable $fn,string $message): void {
    try { $fn(); } catch (InvalidArgumentException|DomainException $e) {
        echo 'PASS: '.$message.PHP_EOL; return;
    }
    throw new RuntimeException('FAIL: '.$message);
}
$receipt=['report_id'=>701,'event_id'=>801,'duplicate'=>false];
$now=1762156800;
$id=str_repeat('a',32);
$intent=BadAround_Identity_Ownership_Recovery_Policy::new_intent('identity_12345678',$receipt,$id,$now);
rec_assert($intent['state']==='pending' && !BadAround_Identity_Ownership_Recovery_Policy::can_show_as_owned($intent),
    'pending ownership is never publicly claimed');
rec_reject(fn()=>BadAround_Identity_Ownership_Recovery_Policy::new_intent('identity_12345678',['report_id'=>701,'event_id'=>801,'duplicate'=>true],$id,$now),
    'duplicate intake denied');
rec_reject(fn()=>BadAround_Identity_Ownership_Recovery_Policy::new_intent('identity_12345678',['report_id'=>701,'event_id'=>801],$id,$now),
    'receipt without duplicate flag denied');
rec_reject(fn()=>BadAround_Identity_Ownership_Recovery_Policy::new_intent('x',$receipt,$id,$now),
    'unverified identity syntax denied');
$retry=BadAround_Identity_Ownership_Recovery_Policy::transition($intent,'retry',$now+1);
rec_assert($retry['attempts']===1 && $retry['state']==='pending','retry stays private pending');
$bound=BadAround_Identity_Ownership_Recovery_Policy::transition($retry,'granted',$now+2);
rec_assert(BadAround_Identity_Ownership_Recovery_Policy::can_show_as_owned($bound),'grant authorizes ownership display');
rec_reject(fn()=>BadAround_Identity_Ownership_Recovery_Policy::transition($bound,'granted',$now+3),
    'resolved ownership may not transition again');
$conflict=BadAround_Identity_Ownership_Recovery_Policy::transition($intent,'conflict',$now+1);
rec_assert($conflict['state']==='needs_review' && !BadAround_Identity_Ownership_Recovery_Policy::can_show_as_owned($conflict),
    'ownership conflict requires manual review');
$pending=$intent;
for($i=1;$i<=5;$i++) { $pending=BadAround_Identity_Ownership_Recovery_Policy::transition($pending,'retry',$now+$i); }
rec_assert($pending['state']==='needs_review' && $pending['attempts']===5,
    'retry budget exhausted to manual review');
echo "ALL_OWNERSHIP_RECOVERY_POLICY_TESTS_PASS\n";
