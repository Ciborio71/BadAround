<?php
/**
 * INTELLIGENCE-01 — offline aggregation core, synthetic fixtures only.
 * Input is an authorized normalized public event snapshot, never raw reports.
 * This is not a public endpoint and must not be used for alerts or risk scores.
 */
declare(strict_types=1);

final class BadAround_Intelligence_Aggregator {
    public const CONTRACT='badaround-intelligence/v0.1';
    public static function summarize(array $events, DateTimeImmutable $asOf): array {
        $seen=[];$accepted=[];$excluded=['unpublished'=>0,'unverified'=>0,'duplicate'=>0,'invalid'=>0];
        foreach($events as $e) {
            if(!is_array($e)){ $excluded['invalid']++;continue; }
            if(($e['publication_status']??null)!=='published'){$excluded['unpublished']++;continue;}
            if(($e['source_quality']??null)!=='verified_real'){$excluded['unverified']++;continue;}
            $id=$e['canonical_episode_id']??null;
            $region=$e['region']??null;$province=$e['province']??null;$municipality=$e['municipality']??null;
            $category=$e['category']??null;$phenomenon=$e['phenomenon']??null;
            if(!is_string($id)||$id===''||!is_string($region)||$region===''||
               !is_string($province)||$province===''||!is_string($municipality)||$municipality===''||
               !is_string($category)||$category===''||!is_string($phenomenon)||$phenomenon==='') {
                $excluded['invalid']++;continue;
            }
            if(isset($seen[$id])){$excluded['duplicate']++;continue;}
            $seen[$id]=true;
            $date=$e['occurred_date']??null;$dateValid=false;
            if(is_string($date)&&preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)) {
                $parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date,$asOf->getTimezone());
                $dateValid=$parsed!==false&&$parsed->format('Y-m-d')===$date&&$parsed<=$asOf->setTime(23,59,59);
            }
            $accepted[]=['region'=>$region,'province'=>$province,'municipality'=>$municipality,
              'category'=>$category,'phenomenon'=>$phenomenon,'month'=>$dateValid?substr($date,0,7):null];
        }
        $result=['contract'=>self::CONTRACT,'snapshot_date'=>$asOf->format('Y-m-d'),
          'scope'=>'synthetic_authorized_projection_only','total_episodes'=>count($accepted),
          'dated_episodes'=>0,'undated_episodes'=>0,'by_category'=>[],
          'by_phenomenon'=>[],'by_region'=>[],'by_province'=>[],'by_municipality'=>[],
          'by_month'=>[],'excluded'=>$excluded,'heatmap'=>null,'alerts'=>[]];
        foreach($accepted as $e){
            foreach(['category','phenomenon','region','province','municipality'] as $field) {
                $key='by_'.$field;
                $result[$key][$e[$field]]=($result[$key][$e[$field]]??0)+1;
            }
            if($e['month']===null)$result['undated_episodes']++;
            else {$result['dated_episodes']++;$result['by_month'][$e['month']]=($result['by_month'][$e['month']]??0)+1;}
        }
        foreach(['by_category','by_phenomenon','by_region','by_province','by_municipality','by_month'] as $key)ksort($result[$key],SORT_STRING);
        return $result;
    }
}
