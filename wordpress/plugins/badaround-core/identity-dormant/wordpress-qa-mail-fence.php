<?php
/**
 * BA-IDENTITY-19: private QA mail recipient fence.
 *
 * No sends on inclusion. Prevent passwordless QA from addressing arbitrary
 * real users: ONLY server-defined explicit recipients may receive OTP.
 * Fail closed for missing, empty, malformed, or duplicate configuration.
 */
declare(strict_types=1);

final class BadAround_Identity_QA_Mail_Fence {
    private array $allowed;

    public static function configured():bool {
        if (!defined('BADAROUND_IDENTITY_QA_EMAIL_ALLOWLIST')
            || !is_array(BADAROUND_IDENTITY_QA_EMAIL_ALLOWLIST))return false;
        $list=BADAROUND_IDENTITY_QA_EMAIL_ALLOWLIST;
        if (count($list)<1 || count($list)>8)return false;
        $distinct=[];
        foreach($list as $value) {
            if (!is_string($value) || $value!==trim($value)
                || strlen($value)>191 || !filter_var($value,FILTER_VALIDATE_EMAIL)
                || $value!==strtolower($value))return false;
            $distinct[$value]=true;
        }
        return count($distinct)===count($list);
    }

    public function __construct(private object $provider) {
        if (!self::configured() || !method_exists($provider,'send')) {
            throw new InvalidArgumentException('QA email allowlist or provider unavailable');
        }
        $this->allowed=BADAROUND_IDENTITY_QA_EMAIL_ALLOWLIST;
    }

    /** Same contract as BadAround_Transactional_Mailer::send(). */
    public function send(string $to,string $subject,string $body):bool {
        if (!self::configured() || !in_array($to,$this->allowed,true)
            || !in_array($to,BADAROUND_IDENTITY_QA_EMAIL_ALLOWLIST,true)
            || !filter_var($to,FILTER_VALIDATE_EMAIL))return false;
        try {return $this->provider->send($to,$subject,$body)===true;}
        catch(Throwable $error){return false;}
    }
}
