<?php
/**
 * BA-IDENTITY-01D — dormant adapters for existing BadAround mail transport
 * and persistent per-email/per-IP request budgets.
 * No WordPress hooks or REST routes registered.
 */
declare(strict_types=1);
require_once __DIR__ . '/otp-issuer.php';
require_once __DIR__ . '/mysql-rate-limiter.php';

final class BadAround_Identity_Transport_Adapter implements BadAround_Identity_Otp_Transport {
    public function __construct(private object $mailer) {
        if (!method_exists($mailer,'send')) {
            throw new InvalidArgumentException('Mailer must implement send()');
        }
    }
    public function send_code(string $email,string $code,string $purpose): bool {
        if (!in_array($purpose,['enrollment','login','verification','access'],true)
            || !preg_match('/^(?:[0-9]{6}|[0-9]{8})$/D',$code)
            || !filter_var($email,FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $subject = 'Codice di verifica BadAround';
        $message = "Il tuo codice BadAround è: ".$code.
            "\nScade entro 10 minuti. Se non hai richiesto l'accesso, ignora questa email.";
        try {
            // Existing BadAround_Transactional_Mailer::send($to,$subject,$body)
            // returns exactly true on successful provider acceptance.
            return $this->mailer->send($email,$subject,$message) === true;
        } catch (Throwable $e) {
            return false;
        }
    }
}

final class BadAround_Identity_Issuance_Quota_Adapter implements BadAround_Identity_Issue_Quota {
    public function __construct(
        private BadAround_Identity_Mysql_Rate_Limiter $limiter,
        private string $serverKey
    ) {
        if (strlen($serverKey)<32) { throw new InvalidArgumentException('Server key too short'); }
    }
    public function allow(string $purpose,string $emailLookup,string $trustedIp,int $now): bool {
        if (!preg_match('/^[a-f0-9]{64}$/D',$emailLookup)
            || !filter_var($trustedIp,FILTER_VALIDATE_IP)) {
            return false;
        }
        $emailBucket = BadAround_Identity_Mysql_Rate_Limiter::bucket(
            $purpose,'email',$emailLookup,$this->serverKey
        );
        $ipBucket = BadAround_Identity_Mysql_Rate_Limiter::bucket(
            $purpose,'ip',$trustedIp,$this->serverKey
        );
        // Conservative fail-closed throttling: even a denied IP request may
        // count against the email budget, reducing abuse at the cost of quota.
        try {
            return $this->limiter->consume($purpose,$emailBucket,$now,3,900)
                && $this->limiter->consume($purpose,$ipBucket,$now,8,900);
        } catch (Throwable $e) {
            return false;
        }
    }
}
