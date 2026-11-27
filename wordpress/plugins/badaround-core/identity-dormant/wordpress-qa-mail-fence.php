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
        return self::recipients()!==null;
    }

    private static function recipients():?array {
        // Explicit server constants take precedence, including invalid ones.
        // The fallback is a private, administrator-managed WordPress option;
        // never read a request field, public profile, or arbitrary email input.
        try {
            $list=defined('BADAROUND_IDENTITY_QA_EMAIL_ALLOWLIST')
                ? BADAROUND_IDENTITY_QA_EMAIL_ALLOWLIST
                : (function_exists('get_option')
                    ? get_option('badaround_identity_qa_email_allowlist',null):null);
        } catch(Throwable $error) {return null;}
        if (!is_array($list) || !array_is_list($list)
            || count($list)<1 || count($list)>8)return null;
        $distinct=[];
        foreach($list as $value) {
            if (!is_string($value) || $value!==trim($value)
                || strlen($value)>191 || !filter_var($value,FILTER_VALIDATE_EMAIL)
                || $value!==strtolower($value))return null;
            $distinct[$value]=true;
        }
        return count($distinct)===count($list)?$list:null;
    }

    public function __construct(private object $provider) {
        if (!self::configured() || !method_exists($provider,'send')) {
            throw new InvalidArgumentException('QA email allowlist or provider unavailable');
        }
        $this->allowed=self::recipients()??[];
    }

    /** Same contract as BadAround_Transactional_Mailer::send(). */
    public function send(string $to,string $subject,string $body):bool {
        $current=self::recipients();
        if ($current===null || !in_array($to,$this->allowed,true)
            || !in_array($to,$current,true)
            || !filter_var($to,FILTER_VALIDATE_EMAIL))return false;
        try {return $this->provider->send($to,$subject,$body)===true;}
        catch(Throwable $error){return false;}
    }
}
