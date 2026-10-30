<?php
/** RADAR-INTELLIGENCE-01: offline verified-only territory/type counts. */
if (!defined('ABSPATH')) exit;
final class BadAround_Radar_Territory_Analysis {
    public static function summarize(array $classified) {
        if(($classified['contract']??null)!=='badaround-radar-quality/v0.1'
            ||!is_array($classified['items']??null))throw new InvalidArgumentException('Quality contract required');
        $counts=array();$excluded=array('confirmed_synthetic'=>0,'unverified'=>0);$verified=0;
        foreach($classified['items'] as $entry){
            $status=$entry['status']??null;
            if($status!=='editor_verified_real'){
                if(!array_key_exists($status,$excluded))throw new InvalidArgumentException('Unknown review status');
                $excluded[$status]++;continue;
            }
            $row=$entry['row']??array();
            $territory=$row['territory']['slug']??null;
            $type=$row['event_type']['slug']??null;
            if(!is_string($territory)||!preg_match('/^[a-z0-9-]+$/D',$territory)
              ||!is_string($type)||!preg_match('/^[a-z0-9-]+$/D',$type))
                throw new InvalidArgumentException('Verified report has invalid public taxonomy');
            $key=$territory.'/'.$type;
            if(!isset($counts[$key]))$counts[$key]=array('territory_slug'=>$territory,'event_type_slug'=>$type,'published_reports'=>0);
            $counts[$key]['published_reports']++;$verified++;
        }
        ksort($counts,SORT_STRING);
        return array('contract'=>'badaround-radar-territory-verified/v0.1',
           'unit'=>'verified_published_reports_not_unique_incidents','verified_count'=>$verified,
           'excluded'=>$excluded,'groups'=>array_values($counts),
           'not_a_risk_index'=>true,'public_alerts'=>array());
    }
}
