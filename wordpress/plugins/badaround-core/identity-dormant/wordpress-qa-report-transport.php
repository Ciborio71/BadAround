<?php
/**
 * BA-IDENTITY-WIZARD-12: private QA REST transport composition boundary.
 *
 * No routes/hooks registered on include. An authorized bootstrap must
 * supply a validated callable for the existing native Golden Path (regular
 * or media). Request identity is taken exclusively from the secure host-only
 * cookie; never from JSON, headers, or the submitted email.
 *
 * IMPORTANT: Public/native controller remains unchanged. This class may be
 * wired ONLY after staging pre-intake schema, private QA and SMTP sandbox
 * readiness. Does not expose an endpoint by itself.
 */
declare(strict_types=1);
require_once __DIR__.'/pre-reserved-native-intake.php';
require_once __DIR__.'/wordpress-private-access.php';
require_once __DIR__.'/wordpress-session-cookie.php';

final class BadAround_Identity_QA_Report_Transport {
    public function __construct(private BadAround_Identity_Pre_Reserved_Intake $intake) {}

    /** Return opaque state only; never private IDs, identity, email or token. */
    public function process(
        array $validatedCanonical, callable $trustedNativeSubmit,
        array $serverCookies, int $now
    ):array {
        if (!defined('BADAROUND_IDENTITY_PRIVATE_QA_ENABLED')
            || BADAROUND_IDENTITY_PRIVATE_QA_ENABLED!==true
            || !defined('BADAROUND_IDENTITY_QA_REPORTS_ENABLED')
            || BADAROUND_IDENTITY_QA_REPORTS_ENABLED!==true
            || !BadAround_Identity_Private_Access::authorized()) {
            return ['state'=>'unavailable'];
        }
        // Submission UUID must come from the same server-validated canonical
        // object passed into the trusted native adapter.
        $submission=$validatedCanonical['submission_id']??null;
        if (!is_string($submission)
            || !BadAround_Identity_Pre_Intake_Reservation::valid_submission($submission)
            || $now<=0) return ['state'=>'denied'];
        $name=BadAround_Identity_WordPress_Session_Cookie::NAME;
        $token=$serverCookies[$name]??null;
        if (!is_string($token))return ['state'=>'denied'];

        $outcome=$this->intake->execute($token,$submission,$trustedNativeSubmit,$now);
        $state=$outcome['state']??'pending';
        if (!in_array($state,['denied','conflict','pending','owner_confirmed'],true)) {
            return ['state'=>'pending'];
        }
        // Never return event_id or other mutable identifiers from the
        // orchestration layer in this QA boundary.
        return ['state'=>$state];
    }
}
