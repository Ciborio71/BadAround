<?php
/**
 * BA-IDENTITY-01P — dormant consent-required first-time registration.
 * No WordPress route, cookie, migration or email is registered on include.
 * The privacy acknowledgement is mandatory, NOT marketing consent.
 */
declare(strict_types=1);
require_once __DIR__.'/enrollment-policy.php';
require_once __DIR__.'/otp-issuer.php';

final class BadAround_Identity_Registration_Service {
    public function __construct(
        private BadAround_Identity_Enrollment $enrollment,
        private BadAround_Identity_Issue_Quota $quota,
        private BadAround_Identity_Challenge_Writer $writer,
        private BadAround_Identity_Otp_Transport $transport,
        private string $rootKey,
        private string $privacyNoticeVersion
    ) {
        if (strlen($rootKey)<32 ||
            !preg_match('/^[A-Za-z0-9._-]{1,64}$/D',$privacyNoticeVersion)) {
            throw new InvalidArgumentException('Invalid registration configuration');
        }
    }

    /** Returns an opaque challenge only after staging and provider acceptance. */
    public function start(
        string $firstName,string $lastName,string $email,
        bool $privacyAcknowledged,string $trustedIp,int $now
    ): ?string {
        if ($privacyAcknowledged !== true ||
            $now<=0 || !filter_var($trustedIp,FILTER_VALIDATE_IP)) {
            return null;
        }
        $id=null;
        try {
            $profile=BadAround_Identity_Enrollment::validate_profile(
                $firstName,$lastName,$email
            );
            $lookup=BadAround_Identity_Challenge_Primitives::digest(
                'email','lookup',$profile['email'],$this->rootKey
            );
            if (!$this->quota->allow('enrollment',$lookup,$trustedIp,$now)) {
                return null;
            }
            $issued=BadAround_Identity_Purpose_Keys::new_challenge_for_purpose(
                $profile['email'],'enrollment',$this->rootKey,$now
            );
            $id=$issued['record']['challenge_id'];
            $this->writer->insert($issued['record']);
            $this->enrollment->stage($profile,$issued['record'],$now,[
                'accepted'=>true,
                'notice_version'=>$this->privacyNoticeVersion,
                'accepted_at'=>$now,
                'method'=>'explicit_checkbox'
            ]);
            if (!$this->transport->send_code(
                $profile['email'],$issued['delivery_code'],'enrollment'
            )) {
                $this->writer->invalidate($id,$now);
                return null;
            }
            return $id;
        } catch(Throwable $error) {
            // Failed staging/delivery must not leave a usable challenge.
            if (is_string($id)) {
                try {$this->writer->invalidate($id,$now);}catch(Throwable $ignored){}
            }
            return null;
        }
    }

    /** Atomic OTP consumption + encrypted verified identity. Never auto-own old reports. */
    public function verify(string $challengeId,string $code,int $now):?string {
        try { return $this->enrollment->verify_and_activate($challengeId,$code,$now); }
        catch(Throwable $error) { return null; }
    }
}
