<?php
/**
 * BA-IDENTITY-WIZARD-16: a database-backed receipt proof for native reports.
 *
 * No routes, hooks or database writes. A persisted report alone does not
 * establish that it is newly created: the caller must independently require
 * a new nonduplicate Golden Path receipt and matching pre-intake reservation.
 */
declare(strict_types=1);
require_once __DIR__.'/post-intake-binder.php';
require_once __DIR__.'/report-otp-canonical-reader.php';
require_once __DIR__.'/pre-intake-recovery-worker.php';

final class BadAround_Identity_WordPress_Native_Receipt_Proof
    implements BadAround_Identity_Intake_Receipt, BadAround_Identity_Pre_Intake_Creation_Order_Proof {
    private string $reports;
    private BadAround_Report_Otp_Canonical_Reader $canonical;

    public function __construct(private PDO $db,string $prefix) {
        if (!preg_match('/^[A-Za-z0-9_]{1,48}$/D',$prefix))
            throw new InvalidArgumentException('Invalid WordPress prefix');
        $this->reports=$prefix.'ba_reports';
        $this->canonical=new BadAround_Report_Otp_Canonical_Reader($db,$prefix);
        $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    }

    public function is_new_canonical_report(int $reportId,int $eventId):bool {
        if ($reportId<=0 || $eventId<=0)return false;
        try {
            $q=$this->db->prepare("SELECT content_original FROM `{$this->reports}`
                WHERE id=:report AND event_id=:event AND status='validated'
                  AND source_type='native' LIMIT 2");
            $q->execute([':report'=>$reportId,':event'=>$eventId]);
            $rows=$q->fetchAll(PDO::FETCH_COLUMN);
            if(count($rows)!==1 || !is_string($rows[0]) || strlen($rows[0])>400000)return false;
            $payload=json_decode($rows[0],true,32);
            if(!is_array($payload))return false;
            $submission=$payload['submission_id']??null;
            if(!is_string($submission) || !preg_match(
                '/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D',
                $submission))return false;
            $canonical=$this->canonical->completed($submission);
            return is_array($canonical)
                && ($canonical['submission_id']??null)===$submission
                && ($canonical['report_id']??null)===$reportId
                && ($canonical['event_id']??null)===$eventId
                && ($canonical['duplicate']??null)===false;
        } catch(Throwable $error) {
            return false;
        }
    }
    /** Reject attempts to recover ownership for a pre-existing native report.
     *  The native repository stores created_at in UTC as a MySQL datetime.
     *  Compare only verified database metadata; never trust HTTP timestamps.
     */
    public function created_after_reservation(int $reportId,int $eventId,int $reservedAt):bool {
        if($reportId<=0 || $eventId<=0 || $reservedAt<=0)return false;
        try {
            $q=$this->db->prepare("SELECT created_at FROM `{$this->reports}`
                WHERE id=:report AND event_id=:event AND status='validated'
                  AND source_type='native' LIMIT 2");
            $q->execute([':report'=>$reportId,':event'=>$eventId]);
            $dates=$q->fetchAll(PDO::FETCH_COLUMN);
            if(count($dates)!==1 || !is_string($dates[0]))return false;
            $created=DateTimeImmutable::createFromFormat(
                '!Y-m-d H:i:s',$dates[0],new DateTimeZone('UTC')
            );
            if($created===false || $created->format('Y-m-d H:i:s')!==$dates[0])return false;
            return $created->getTimestamp()>=$reservedAt;
        }catch(Throwable $error){return false;}
    }

}
