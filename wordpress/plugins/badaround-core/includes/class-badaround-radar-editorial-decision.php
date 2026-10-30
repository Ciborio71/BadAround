<?php
/** RADAR-QUALITY-02 — inert validation-only editorial decision contract. No hooks or persistence. */
if (!defined('ABSPATH')) exit;
final class BadAround_Radar_Editorial_Decision {
    public static function validate(array $decision) {
        $required=array('event_id','status','reviewer_id','reviewed_at','evidence_ref','reason','expected_revision');
        if(array_keys($decision)!==$required)throw new InvalidArgumentException('Unexpected decision fields/order');
        if(!is_int($decision['event_id'])||$decision['event_id']<1
            ||!in_array($decision['status'],array('confirmed_synthetic','editor_verified_real'),true)
            ||!is_int($decision['reviewer_id'])||$decision['reviewer_id']<1
            ||!is_string($decision['reviewed_at'])
            ||!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/D',$decision['reviewed_at'])
            ||!is_string($decision['evidence_ref'])||!preg_match('/^[a-z0-9][a-z0-9:-]{5,190}$/D',$decision['evidence_ref'])
            ||!is_string($decision['reason'])||trim($decision['reason'])===''||strlen($decision['reason'])>500
            ||!is_string($decision['expected_revision'])||!preg_match('/^[a-f0-9]{64}$/D',$decision['expected_revision']))
            throw new InvalidArgumentException('Invalid editorial review contract');
        if(in_array($decision['event_id'],array(233,352,354),true)
            && $decision['status']!=='confirmed_synthetic')
            throw new InvalidArgumentException('Protected QA event');
        return array('event_id'=>$decision['event_id'],'status'=>$decision['status'],
            'reviewer_id'=>$decision['reviewer_id'],'reviewed_at'=>$decision['reviewed_at'],
            'evidence_ref'=>$decision['evidence_ref'],
            'reason'=>$decision['reason'],'expected_revision'=>$decision['expected_revision'],
            'must_verify_source_and_revision'=>true,'may_write'=>false,
            'public_exposure'=>false);
    }
}
