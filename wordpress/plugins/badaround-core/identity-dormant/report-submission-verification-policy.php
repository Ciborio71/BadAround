<?php
/**
 * BA-REPORT-IDENTITY-01: isolated future-report verification policy.
 *
 * No WordPress hooks, no DB writes, no email sends or public behavior.
 * Cannot mint verification state: only checks a trusted, previously persisted
 * single-use receipt delivered by a future report-specific backend.
 */
declare(strict_types=1);

final class BadAround_Report_Verification_Policy {
    public const OTP_PURPOSE='report_submission';

    /**
     * Report registration NEVER requires a WordPress or Identity account.
     * All three private fields must be valid; public display is independent.
     */
    public static function private_profile(string $firstName,string $lastName,string $email):array {
        $firstName=trim($firstName);
        $lastName=trim($lastName);
        $email=strtolower(trim($email));
        foreach([$firstName,$lastName] as $part) {
            if ($part==='' || mb_strlen($part,'UTF-8')>191
                || preg_match('/[<>\\x00-\\x1F\\x7F]/u',$part)) {
                throw new InvalidArgumentException('Required private profile invalid');
            }
        }
        if (!filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>191) {
            throw new InvalidArgumentException('Required private email invalid');
        }
        return ['first_name'=>$firstName,'last_name'=>$lastName,'email'=>$email];
    }

    /**
     * A server-sourced proof only. Client-supplied fields MUST NOT be treated
     * as trusted receipts by the eventual controller or intake adapter.
     * Proof binds the normalized email digest to one submission ID, purpose,
     * expiry and a successful verified/atomic consume state.
     */
    public static function verified_receipt(
        array $trusted, string $expectedEmailLookup,string $submissionId,int $now
    ):bool {
        if (!preg_match('/^[a-f0-9]{64}$/D',$expectedEmailLookup)
            || !preg_match('/^[a-f0-9-]{36}$/D',$submissionId)
            || $now<=0) {return false;}
        $purpose=$trusted['purpose']??null;
        $digest=$trusted['email_lookup']??null;
        $bound=$trusted['submission_id']??null;
        $expiry=$trusted['expires_at']??null;
        $consumed=$trusted['consumed_at']??null;
        $verified=$trusted['verified_at']??null;
        $duplicate=$trusted['duplicate']??null;
        return $purpose===self::OTP_PURPOSE
            && is_string($digest)
            && strlen($digest)===64
            && hash_equals($expectedEmailLookup,$digest)
            && is_string($bound)
            && hash_equals($submissionId,$bound)
            && is_int($expiry) && $now<$expiry
            && is_int($consumed) && $consumed>0 && $consumed<=$now
            && is_int($verified) && $verified>0 && $verified<=$now
            && $duplicate===false;
    }

    /** No inferred grants from email or name; ownership is separate. */
    public static function can_claim_existing_report_by_email_only():bool {
        return false;
    }
}
