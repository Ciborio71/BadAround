<?php
/**
 * BA-IDENTITY-WIZARD-14: dormant QA-only composition on badaround.it.
 *
 * No WordPress hooks, public route, SQL DDL or messages on inclusion.
 * Must be explicitly invoked by an authorized private QA bootstrap.
 * If any prerequisite is missing, return null. The live public report
 * controller and frozen Golden Path are never modified.
 */
declare(strict_types=1);

require_once __DIR__.'/wordpress-qa-report-rest.php';
require_once __DIR__.'/wordpress-pre-intake-recovery-composition.php';
require_once __DIR__.'/locked-canonical-reader.php';
require_once __DIR__.'/wordpress-native-receipt-proof.php';
require_once __DIR__.'/mysql-session-store.php';
require_once __DIR__.'/mysql-identity-active-status.php';

final class BadAround_Identity_WordPress_QA_Report_Composition {
    public static function assemble(
        PDO $db,string $prefix,object $nativeRepository,
        ?BadAround_Identity_Intake_Receipt $receiptProof=null
    ):?BadAround_Identity_QA_Report_REST {
        if (!BadAround_Identity_QA_Report_REST::enabled()
            || !defined('BADAROUND_IDENTITY_ROOT_SECRET')
            || !is_string(BADAROUND_IDENTITY_ROOT_SECRET)
            || strlen(BADAROUND_IDENTITY_ROOT_SECRET)<48
            || !preg_match('/^[A-Za-z0-9_]{1,48}$/D',$prefix)
            || !function_exists('home_url')
            || rtrim((string)home_url('/'),'/')!=='https://badaround.it'
            || !class_exists('BadAround_Report_Version_Registry')
            || !class_exists('BadAround_Native_Report_Golden_Path_Service')
            || !class_exists('BadAround_Native_Media_Report_Adapter')
            || !class_exists('BadAround_Report_Repository')) {
            return null;
        }
        try {
            if (!BadAround_Identity_Pre_Intake_Schema::inspect($db,$prefix))return null;
            $receiptProof ??= new BadAround_Identity_WordPress_Native_Receipt_Proof($db,$prefix);

            $key=hash_hmac('sha256','BA-ID-01H:sessions',BADAROUND_IDENTITY_ROOT_SECRET,true);
            $sessions=new BadAround_Identity_Sessions(
                new BadAround_Identity_Mysql_Session_Store($db,$prefix.'ba_identity_sessions'),$key
            );
            $active=new BadAround_Identity_Mysql_Active_Status($db,$prefix.'ba_identities');
            $preStore=new BadAround_Identity_Mysql_Pre_Intake_Store(
                $db,BadAround_Identity_Pre_Intake_Schema::table($prefix)
            );
            $journal=new BadAround_Identity_Mysql_Ownership_Recovery_Store(
                $db,$prefix.'ba_identity_recovery'
            );
            $intake=new BadAround_Identity_Pre_Reserved_Intake(
                new BadAround_Identity_Native_Intake_Gate($sessions,$active),
                new BadAround_Identity_Pre_Intake_Reservation($preStore),
                new BadAround_Identity_Locked_Canonical_Reader(
                    new BadAround_Report_Otp_WordPress_Native_Lock($nativeRepository),
                    new BadAround_Identity_Canonical_Submission_Bridge(
                        new BadAround_Report_Otp_Canonical_Reader($db,$prefix)
                    )
                ),
                new BadAround_Identity_Durable_Ownership_Handoff(
                    $sessions,$active,$receiptProof,
                    new BadAround_Identity_Creator_Grant_Bridge(
                        new BadAround_Identity_Mysql_Report_Creator_Grant(
                            $db,$prefix,new BadAround_Report_Otp_WordPress_Native_Lock($nativeRepository)
                        )
                    ),
                    new BadAround_Identity_Recovery_Store_Bridge($journal)
                )
            );

            $validate=static function(array $raw) {
                return BadAround_Report_Version_Registry::prepare($raw);
            };
            // Transport is selected by validated canonical media descriptors,
            // and both paths reuse the existing native Golden Path.
            $native=static function(array $raw,$request,bool $withMedia) {
                if ($withMedia) {
                    return (new BadAround_Native_Media_Report_Adapter())->submit(
                        $raw,$request->get_header('x-badaround-media-capability')
                    );
                }
                return (new BadAround_Native_Report_Golden_Path_Service())->submit($raw);
            };
            return new BadAround_Identity_QA_Report_REST(
                new BadAround_Identity_QA_Report_Transport($intake,static function(string $token,string $email,int $now) use($db,$prefix,$sessions,$active):bool {
                    $identity=$sessions->identity_for($token,$now);
                    if($identity===null||!$active->is_active_verified($identity))return false;
                    try{$email=BadAround_Identity_Challenge_Primitives::normalize_email($email);}catch(Throwable $e){return false;}
                    $lookup=BadAround_Identity_Challenge_Primitives::digest('email','lookup',$email,BADAROUND_IDENTITY_ROOT_SECRET);
                    $q=$db->prepare("SELECT identity_id FROM `{$prefix}ba_identities` WHERE identity_id=? AND email_lookup=? AND verified_at>0 AND revoked_at IS NULL");
                    $q->execute([$identity,$lookup]);return $q->fetchColumn()===$identity;
                }),$validate,$native
            );
        } catch(Throwable $error) {
            return null;
        }
    }
}
