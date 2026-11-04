<?php
declare(strict_types=1);
require __DIR__ . '/otp-adapters.php';
class FakeExistingMailer {
    public array $messages = [];
    public bool $ok=true;
    public function send(string $recipient,string $subject,string $body): bool {
        $this->messages[]=[$recipient,$subject,$body];
        return $this->ok;
    }
}
$fake=new FakeExistingMailer();
$adapter=new BadAround_Identity_Transport_Adapter($fake);
if (!$adapter->send_code('test@example.com','12345678','enrollment')
    || count($fake->messages)!==1
    || !str_contains($fake->messages[0][2],'12345678')) {
    throw new RuntimeException('Existing transactional mailer adapter failed');
}
$fake->ok=false;
if ($adapter->send_code('test@example.com','12345678','login')
    || $adapter->send_code('test@example.com','invalid','login')
    || $adapter->send_code('test@example.com','12345678','evil')) {
    throw new RuntimeException('Mailer failure or invalid input not rejected');
}
echo "ALL_MAIL_TRANSPORT_ADAPTER_TESTS_PASS\n";
