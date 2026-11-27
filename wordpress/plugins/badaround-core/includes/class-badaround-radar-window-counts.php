<?php
/** RADAR-PUBLIC-05 — offline-only scoped counts. No registration or public exposure. */
if (!defined('ABSPATH')) exit;
final class BadAround_Radar_Window_Counts {
    public static function present(array $archive, array $trends, array $quality) {
        if (($archive['contract']??null)!=='badaround-radar-public-counts/v0.1'
            || ($trends['contract']??null)!=='badaround-intelligence-descriptive-trends/v0.1'
            || ($quality['contract']??null)!=='badaround-intelligence-quality-gate/v0.1'
            || ($archive['unit']??null)!=='published_reports_not_unique_incidents'
            || ($trends['unit']??null)!==$archive['unit']
            || ($quality['unit']??null)!==$archive['unit']
            || !in_array($trends['period_days']??null,array(7,30),true)
            || !is_array($trends['groups']??null)
            || ($trends['input_report_count']??null)!==($archive['published_report_count']??null)
            || ($quality['date_coverage_count']??null)!==($archive['dated_reports']??null)
            || ($quality['undated_or_invalid_count']??null)!==($archive['undated_reports']??null)
            || ($trends['undated_or_invalid_report_count']??null)!==($archive['undated_reports']??null)
            || ($trends['public_alerts']??null)!==array()
            || ($quality['public_alerts']??null)!==array()
            || ($archive['public_alerts']??null)!==array()) {
            throw new InvalidArgumentException('Mismatched canonical Radar contracts');
        }
        $groups=array();$total=0;
        foreach($trends['groups'] as $group) {
            $count=$group['current_reports']??null;
            $territory=$group['territory_slug']??null;
            $type=$group['event_type_slug']??null;
            if(!is_int($count)||$count<0||!is_string($territory)
                ||!preg_match('/^[a-z0-9-]+$/D',$territory)
                ||!is_string($type)||!preg_match('/^[a-z0-9-]+$/D',$type))
                throw new InvalidArgumentException('Invalid period group');
            $total+=$count;
            if($count===0)continue; // Historical-only group stays in trends, not current-period UI.
            $groups[]=array('territory_slug'=>$territory,'event_type_slug'=>$type,
                'published_reports'=>$count);
        }
        return array('contract'=>'badaround-radar-window-counts/v0.1',
            'unit'=>'published_reports_not_unique_incidents',
            'period_days'=>$trends['period_days'],
            'archive_reports'=>$archive['published_report_count'],
            'dated_archive_reports'=>$archive['dated_reports'],
            'undated_archive_reports'=>$archive['undated_reports'],
            'dated_reports_in_period'=>$total,
            'groups'=>$groups,
            'date_coverage_sufficient'=>$archive['date_coverage_sufficient'],
            'temporal_comparison_visible'=>false,'risk_score_available'=>false,
            'public_alerts'=>array());
    }
}
