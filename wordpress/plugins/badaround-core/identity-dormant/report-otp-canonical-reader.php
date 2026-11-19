<?php
/** BA-REPORT-IDENTITY-05 — read-only canonical reconciliation adapter.
 * No hooks, DDL, writes or externally callable routes. Caller must hold the
 * native submission lock; never trust a browser-supplied canonical receipt.
 */
declare(strict_types=1);
final class BadAround_Report_Otp_Canonical_Reader {
    public function __construct(private PDO $db,private string $prefix) {
        if (!preg_match('/^[A-Za-z0-9_]{1,48}$/D',$prefix)) {
            throw new InvalidArgumentException('Invalid WordPress prefix');
        }
        $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    }
    public function completed(string $submissionId):?array {
        if (!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D',$submissionId)) return null;
        $hash=hash('sha256',$submissionId);
        $parts=[(int)hexdec(substr($hash,0,7)),(int)hexdec(substr($hash,7,15))];
        $reports=$this->prefix.'ba_reports';
        $posts=$this->prefix.'posts';
        $meta=$this->prefix.'postmeta';
        try {
            $stmt=$this->db->prepare("SELECT r.id,r.event_id,r.content_original,r.status,
                p.post_type,p.post_status FROM `{$reports}` r
                JOIN `{$posts}` p ON p.ID=r.event_id
                WHERE r.source_type='native' AND r.source_form_id=:form
                AND r.source_entry_id=:entry LIMIT 2");
            $stmt->execute([':form'=>$parts[0],':entry'=>$parts[1]]);
            $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
            if(count($rows)!==1) return null;
            $row=$rows[0];
            if($row['status']!=='validated'||$row['post_type']!=='ba_evento'
                || !in_array($row['post_status'],['pending','draft','private','publish'],true)
                || (int)$row['id']<=0 || (int)$row['event_id']<=0) return null;
            $canonical=json_decode((string)$row['content_original'],true,32);
            if(!is_array($canonical) || !hash_equals($submissionId,(string)($canonical['submission_id']??''))
                || !hash_equals($submissionId,(string)($canonical['canonical']['submission_id']??''))) return null;
            $q=$this->db->prepare("SELECT meta_value FROM `{$meta}`
                WHERE post_id=:event AND meta_key='_ba_primary_report_id' LIMIT 2");
            $q->execute([':event'=>(int)$row['event_id']]);
            $binding=$q->fetchAll(PDO::FETCH_COLUMN);
            if(count($binding)!==1 || (string)(int)$row['id']!==(string)$binding[0]) return null;
            return ['submission_id'=>$submissionId,'report_id'=>(int)$row['id'],
                'event_id'=>(int)$row['event_id'],'duplicate'=>false];
        }catch(Throwable $e){return null;}
    }
}
