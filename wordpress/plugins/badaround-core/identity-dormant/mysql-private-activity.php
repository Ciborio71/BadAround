<?php
/**
 * BA-IDENTITY-02E — private personal activity read model.
 * Deliberately excludes encrypted body, names, email, moderation actor,
 * report details and public content. Never trusts an identity from a client.
 */
declare(strict_types=1);
require_once __DIR__.'/session-policy.php';
require_once __DIR__.'/identity-active-status.php';
require_once __DIR__.'/csrf-policy.php';

final class BadAround_Identity_Private_Activity {
    public function __construct(
        private PDO $db,private string $table,
        private BadAround_Identity_Sessions $sessions,
        private BadAround_Identity_Active_Status $active,
        private string $csrfKey,private string $origin
    ) {
        if (!preg_match('/^[A-Za-z0-9_]+$/D',$table)
            || strlen($csrfKey)<32
            || !BadAround_Identity_Csrf_Policy::origin_allowed($origin,$origin)) {
            throw new InvalidArgumentException('Invalid private activity configuration');
        }
        $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    }
    /** Returns null for unauthorized access or DB errors, never a partial result. */
    public function mine(string $bearer,string $csrf,string $requestOrigin,int $now):?array {
        if (!BadAround_Identity_Csrf_Policy::origin_allowed($requestOrigin,$this->origin)
            || !BadAround_Identity_Csrf_Policy::verify($csrf,$bearer,$this->csrfKey)
            || $now<=0) {return null;}
        try {
            $identity=$this->sessions->identity_for($bearer,$now);
            if ($identity===null || !$this->active->is_active_verified($identity)) {
                return null;
            }
            $q=$this->db->prepare("SELECT intent_id, kind, resource_id, state, created_at,
                moderated_at FROM `{$this->table}`
                WHERE identity_id=:identity AND kind IN ('comment','revision')
                  AND state IN ('pending','approved','rejected')
                ORDER BY created_at DESC, intent_id DESC LIMIT 20");
            $q->execute([':identity'=>$identity]);
            $rows=$q->fetchAll(PDO::FETCH_ASSOC);
            if (!is_array($rows)) {return null;}
            $safe=[];
            foreach ($rows as $row) {
                if (!preg_match('/^[a-f0-9]{32}$/D',(string)($row['intent_id']??''))
                    || !in_array($row['kind']??null,['comment','revision'],true)
                    || !in_array($row['state']??null,['pending','approved','rejected'],true)) {
                    return null;
                }
                $safe[]=[
                    'id'=>(string)$row['intent_id'],
                    'kind'=>(string)$row['kind'],
                    'resource_id'=>(int)$row['resource_id'],
                    'status'=>(string)$row['state'],
                    'created_at'=>(int)$row['created_at'],
                    'moderated_at'=>$row['moderated_at']===null?null:(int)$row['moderated_at'],
                ];
            }
            return $safe;
        } catch(Throwable $error) {return null;}
    }
}
