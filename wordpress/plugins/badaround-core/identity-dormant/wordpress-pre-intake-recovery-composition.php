<?php
/**
 * BA-IDENTITY-WIZARD-11: dormant WordPress/PDO recovery composition.
 * No routes, cron, hooks, DDL, mail, cookies or database writes on include.
 * No fallback to anonymous identity; fail closed if the QA table is missing.
 */
declare(strict_types=1);

require_once __DIR__.'/pre-intake-schema-candidate.php';
require_once __DIR__.'/pre-intake-recovery-store-bridge.php';
require_once __DIR__.'/recovery-owner-bridge.php';
require_once __DIR__.'/recovery-store-bridge.php';
require_once __DIR__.'/report-otp-wordpress-adapters.php';

final class BadAround_Identity_Canonical_Submission_Bridge
    implements BadAround_Identity_Pre_Intake_Canonical_Reader {
    public function __construct(private BadAround_Report_Otp_Canonical_Reader $reader) {}
    public function completed(string $submissionId): ?array {
        return $this->reader->completed($submissionId);
    }
}
final class BadAround_Identity_Creator_Grant_Bridge implements BadAround_Identity_Ownership_Grant {
    public function __construct(private BadAround_Identity_Mysql_Ownership_Store $store) {}
    public function grant_creator(string $identityId,string $resourceKind,int $resourceId,int $now):bool {
        return $this->store->grant_creator($identityId,$resourceKind,$resourceId,$now);
    }
}

final class BadAround_Identity_WordPress_Recovery_Composition {
    /**
     * Callable only after private QA authorization by the caller.
     * A valid pre-intake schema must already exist; never auto-create it.
     *
     * @return BadAround_Identity_Pre_Intake_Recovery_Worker|null
     */
    public static function assemble(
        PDO $db,string $prefix,object $nativeRepository,
        BadAround_Identity_Active_Status $status,
        BadAround_Identity_Intake_Receipt $receiptProof
    ): ?BadAround_Identity_Pre_Intake_Recovery_Worker {
        if (!defined('BADAROUND_IDENTITY_PRIVATE_QA_ENABLED')
            || BADAROUND_IDENTITY_PRIVATE_QA_ENABLED !== true
            || !$receiptProof instanceof BadAround_Identity_Pre_Intake_Creation_Order_Proof) return null;
        try {
            if (!BadAround_Identity_Pre_Intake_Schema::inspect($db,$prefix))return null;
            $pre=new BadAround_Identity_Mysql_Pre_Intake_Store(
                $db,BadAround_Identity_Pre_Intake_Schema::table($prefix)
            );
            $owner=new BadAround_Identity_Mysql_Ownership_Store($db,$prefix.'ba_identity_ownership');
            $journal=new BadAround_Identity_Mysql_Ownership_Recovery_Store(
                $db,$prefix.'ba_identity_recovery'
            );
            return new BadAround_Identity_Pre_Intake_Recovery_Worker(
                new BadAround_Report_Otp_WordPress_Native_Lock($nativeRepository),
                new BadAround_Identity_Pre_Intake_Recovery_Store_Bridge($pre),
                new BadAround_Identity_Canonical_Submission_Bridge(
                    new BadAround_Report_Otp_Canonical_Reader($db,$prefix)
                ),
                $status,
                new BadAround_Identity_Recovery_Owner_Bridge($owner),
                new BadAround_Identity_Recovery_Store_Bridge($journal),
                new BadAround_Identity_Creator_Grant_Bridge($owner),
                $receiptProof,
                $receiptProof
            );
        } catch (Throwable $error) {
            return null;
        }
    }
}
