<?php
/** Dormant REST gate: including the file does not register anything. */
declare(strict_types=1);
require __DIR__.'/wordpress-community-rest-adapter.php';
final class RestOffAuthority implements BadAround_Identity_Community_Authority {
    public function active_identity(?string $token,int $now):?string { return null; }
    public function canonical_event_is_published(int $id):bool { return false; }
    public function verified_owner_record(int $id):?array { return null; }
    public function may_moderate(string $id):bool { return false; }
}
final class RestOffInbox implements BadAround_Identity_Community_Inbox_Writer {
    public function submit(string $id,string $kind,string $actor,int $resource,array $intent,int $now):bool { return false; }
    public function decide(string $id,int $version,string $decision,string $actor,int $now):bool { return false; }
}
$key=str_repeat('R',32);
$sessions=new BadAround_Identity_Sessions(new BadAround_Identity_Session_Memory_Store(),$key);
$status=new BadAround_Identity_Active_Memory_Status();
$guard=new BadAround_Identity_Edit_Guard($sessions,$key,'https://badaround.it',$status);
$gateway=new BadAround_Identity_Community_Gateway(new RestOffAuthority(),
    new RestOffInbox(),$guard,$key,'https://badaround.it');
$adapter=new BadAround_Identity_Community_Rest_Adapter($gateway);
if ($adapter->register_hooks() !== false) {
    throw new RuntimeException('Feature must be OFF by default');
}
$denied=$adapter->submit(new stdClass(),'comment');
if (($denied['status']??null)!==403) {
    throw new RuntimeException('Disabled route accepted a request');
}
echo "ALL_DORMANT_REST_GATE_TESTS_PASS\n";
