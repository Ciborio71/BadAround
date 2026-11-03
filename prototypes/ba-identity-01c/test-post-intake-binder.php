<?php
declare(strict_types=1);
require __DIR__ . '/post-intake-binder.php';
final class ReceiptFixture implements BadAround_Identity_Intake_Receipt {
    public function is_new_canonical_report(int $reportId,int $eventId): bool {
        return $reportId === 701 && $eventId === 801;
    }
}
final class GrantFixture implements BadAround_Identity_Ownership_Grant {
    public array $granted=[];
    public function grant_creator(string $id,string $kind,int $resource,int $now): bool {
        if (isset($this->granted[$kind.':'.$resource])) { return false; }
        $this->granted[$kind.':'.$resource] = $id;
        return true;
    }
}
function bind_check(bool $pass,string $name): void {
    if (!$pass) { throw new RuntimeException('FAIL: '.$name); }
    echo "PASS: ".$name."\n";
}
$grant=new GrantFixture();
$binder=new BadAround_Identity_Post_Intake_Binder(new ReceiptFixture(),$grant);
$receipt=['report_id'=>701,'event_id'=>801,'duplicate'=>false];
$now=1762156800;
bind_check(!$binder->bind_created_report(null,$receipt,$now),'anonymous cannot claim report');
bind_check(!$binder->bind_created_report('identity_12345678',['report_id'=>701,'event_id'=>801,'duplicate'=>true],$now),'idempotent duplicate cannot claim');
bind_check(!$binder->bind_created_report('identity_12345678',['report_id'=>701,'event_id'=>802,'duplicate'=>false],$now),'untrusted report event pair rejected');
bind_check(!$binder->bind_created_report('identity_12345678',['report_id'=>701,'event_id'=>801],$now),'missing duplicate indicator denied');
bind_check($binder->bind_created_report('identity_12345678',$receipt,$now),'verified owner bound to fresh intake report');
bind_check(!$binder->bind_created_report('identity_87654321',$receipt,$now),'second identity cannot take ownership');
bind_check(count($grant->granted)===1,'exactly one ownership grant');
echo "ALL_POST_INTAKE_BINDER_TESTS_PASS\n";
