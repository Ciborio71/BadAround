<?php
/**
 * BA-REPORT-IDENTITY-02 — report-scoped OTP workflow contract.
 * Dormant; no WordPress hooks, routes, SQL DDL, or email transports.
 * Future production implementation MUST provide durable atomic transaction
 * across challenge verification, proof-claim and canonical intake handoff.
 */
declare(strict_types=1);
require_once __DIR__.'/report-submission-verification-policy.php';
require_once __DIR__.'/challenge-primitives.php';

interface BadAround_Report_Otp_Proof_Store {
    /** Store a report-specific challenge keyed by opaque ID, with an HMAC email lookup. */
    public function stage(array $record):bool;
    /** Disable a staged code when delivery is refused or throws. */
    public function invalidate(string $id,int $now):void;
    /** Atomically increment failed attempts OR consume the correct code once.
     *  A trusted implementation must lock and check purpose, email, report
     *  submission ID and version in a single serializable transaction.
     */
    public function verify_once(string $id,string $otpDigest,int $now):bool;
    /** Atomic claim of a consumed proof by precisely one canonical intake.
     *  Must be transaction-bound to successful report creation in production.
     */
    public function claim_once(string $id,string $lookup,string $submissionId,int $now):bool;
}

interface BadAround_Report_Otp_Transport {
    public function send_report_code(string $email,string $code):bool;
}
interface BadAround_Report_Otp_Quota {
    public function consume(string $lookup,string $trustedIp,int $now):bool;
}

final class BadAround_Report_Otp_Service {
    private string $hmacKey;
    public function __construct(
        private BadAround_Report_Otp_Proof_Store $store,
        private BadAround_Report_Otp_Transport $mailer,
        private BadAround_Report_Otp_Quota $quota,
        string $rootKey
    ) {
        if(strlen($rootKey)<48)throw new InvalidArgumentException('Invalid root secret');
        // Purpose-isolated from existing account login/enrollment keys.
        $this->hmacKey=hash_hmac('sha256','BA-REPORT-OTP-V1',$rootKey,true);
    }
    public function request(
        string $first,string $last,string $email,string $submissionId,
        string $trustedIp,int $now
    ):?string {
        if (!preg_match('/^[a-f0-9-]{36}$/D',$submissionId)
            || !filter_var($trustedIp,FILTER_VALIDATE_IP) || $now<=0) return null;
        try {
            $profile=BadAround_Report_Verification_Policy::private_profile($first,$last,$email);
            $emailLookup=$this->lookup($profile['email']);
            if(!$this->quota->consume($emailLookup,$trustedIp,$now))return null;
            $id=bin2hex(random_bytes(16));
            $code=BadAround_Identity_Challenge_Primitives::generate_code();
            $record=[
                'challenge_id'=>$id,'purpose'=>BadAround_Report_Verification_Policy::OTP_PURPOSE,
                'submission_id'=>$submissionId,'email_lookup'=>$emailLookup,
                'code_digest'=>hash_hmac('sha256',$id."\0".$code,$this->hmacKey),
                'created_at'=>$now,'expires_at'=>$now+600,'attempts'=>0,
                'verified_at'=>null,'claimed_at'=>null
            ];
            if(!$this->store->stage($record))return null;
            // Only the transport sees plaintext recipient/code.
            try {
                if(!$this->mailer->send_report_code($profile['email'],$code)) {
                    $this->store->invalidate($id,$now);
                    return null;
                }
            } catch(Throwable $deliveryError) {
                $this->store->invalidate($id,$now);
                return null;
            }
            return $id;
        }catch(Throwable $error){return null;}
    }
    public function confirm(string $challengeId,string $code,int $now):bool {
        if(!preg_match('/^[a-f0-9]{32}$/D',$challengeId)
            || !preg_match('/^(?:[0-9]{6}|[0-9]{8})$/D',$code)||$now<=0)return false;
        try {
            return $this->store->verify_once(
                $challengeId,
                hash_hmac('sha256',$challengeId."\0".$code,$this->hmacKey),
                $now
            );
        }catch(Throwable $error){return false;}
    }
    /**
     * Server must derive expectedEmail from validated canonical intake,
     * NEVER from a standalone browser claim. This method cannot publish.
     */
    public function claim_for_canonical_intake(
        string $challengeId,string $expectedEmail,string $trustedSubmissionId,int $now
    ):bool {
        if(!preg_match('/^[a-f0-9]{32}$/D',$challengeId)
            || !preg_match('/^[a-f0-9-]{36}$/D',$trustedSubmissionId)||$now<=0)return false;
        try {
            $email=BadAround_Identity_Challenge_Primitives::normalize_email($expectedEmail);
            return $this->store->claim_once(
                $challengeId,$this->lookup($email),$trustedSubmissionId,$now
            );
        }catch(Throwable $error){return false;}
    }
    private function lookup(string $email):string {
        return hash_hmac('sha256','report-email\0'.$email,$this->hmacKey);
    }
}
