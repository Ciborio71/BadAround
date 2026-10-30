<?php
/** RADAR-QUALITY-01: offline source-quality classification; no WordPress hooks or writes. */
if (!defined('ABSPATH')) exit;
final class BadAround_Radar_Quality_Registry {
    const KNOWN_SYNTHETIC_IDS = array(233,352,354);
    public static function classify(array $rows, array $reviewed = array()) {
        $seen=array();$out=array();
        foreach ($rows as $row) {
            $id=$row['id']??null;
            if (!is_int($id)||$id<1||isset($seen[$id])) throw new InvalidArgumentException('Invalid/duplicate report');
            $seen[$id]=true;
            $state=in_array($id,self::KNOWN_SYNTHETIC_IDS,true) ? 'confirmed_synthetic' : 'unverified';
            if(array_key_exists($id,$reviewed)){
                $review=$reviewed[$id];
                if(!is_array($review)||!in_array($review['status']??null,array('confirmed_synthetic','editor_verified_real'),true)
                    ||!is_int($review['reviewer_id']??null)||$review['reviewer_id']<1
                    ||!is_string($review['reviewed_at']??null)
                    ||!preg_match('/^\\d{4}-\\d{2}-\\d{2}T\\d{2}:\\d{2}:\\d{2}Z$/D',$review['reviewed_at'])
                    ||!is_string($review['evidence_ref']??null)||$review['evidence_ref']==='') {
                    throw new InvalidArgumentException('Invalid editorial review');
                }
                if($state==='confirmed_synthetic' && $review['status']!=='confirmed_synthetic')
                    throw new InvalidArgumentException('Synthetic event requires separate remediation');
                $state=$review['status'];
            }
            $out[]=array('id'=>$id,'status'=>$state,'row'=>$row);
        }
        return array('contract'=>'badaround-radar-quality/v0.1','items'=>$out,
            'verified_real_count'=>count(array_filter($out,static function($x){return $x['status']==='editor_verified_real';})),
            'synthetic_count'=>count(array_filter($out,static function($x){return $x['status']==='confirmed_synthetic';})),
            'unverified_count'=>count(array_filter($out,static function($x){return $x['status']==='unverified';})),
            'public_risk_inference_enabled'=>false);
    }
}
