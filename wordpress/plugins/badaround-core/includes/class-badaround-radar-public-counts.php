<?php
/** RADAR-PUBLIC-01 — conservative display-only summary. Not registered into WordPress. */
if (!defined('ABSPATH')) exit;
final class BadAround_Radar_Public_Counts {
    public static function present(array $rows, array $quality) {
        if (($quality['contract']??null)!=='badaround-intelligence-quality-gate/v0.1'
            || ($quality['unit']??null)!=='published_reports_not_unique_incidents'
            || ($quality['public_alerts']??null)!==array()
            || !is_array($quality['groups']??null)) throw new InvalidArgumentException('QA required');
        $dated=$quality['date_coverage_count']??null;
        $missing=$quality['undated_or_invalid_count']??null;
        if(!is_int($dated)||!is_int($missing)||$dated<0||$missing<0||$dated+$missing!==count($rows))
            throw new InvalidArgumentException('Incomplete reporting coverage');
        $seen=array();$counts=array();
        foreach($rows as $row) {
            if(!is_array($row)||!isset($row['id'])||!is_int($row['id'])||$row['id']<1||isset($seen[$row['id']]))
                throw new InvalidArgumentException('Duplicate public report');
            $seen[$row['id']]=true;
            $territory=$row['territory']['slug']??null;
            $type=$row['event_type']['slug']??null;
            if(!is_string($territory)||!preg_match('/^[a-z0-9-]+$/D',$territory)
                ||!is_string($type)||!preg_match('/^[a-z0-9-]+$/D',$type))continue;
            $key=$territory.'/'.$type;
            if(!isset($counts[$key]))$counts[$key]=array('territory_slug'=>$territory,
                'event_type_slug'=>$type,'published_reports'=>0);
            $counts[$key]['published_reports']++;
        }
        ksort($counts,SORT_STRING);
        return array('contract'=>'badaround-radar-public-counts/v0.1',
            'unit'=>'published_reports_not_unique_incidents','published_report_count'=>count($rows),
            'dated_reports'=>$dated,'undated_reports'=>$missing,
            'date_coverage_sufficient'=>count($rows)>0 && $missing*5<=count($rows),
            'groups'=>array_values($counts),
            'temporal_comparison_visible'=>false,
            'risk_score_available'=>false,'public_alerts'=>array(),
            'disclaimer'=>'Conteggi di segnalazioni pubblicate, non episodi distinti o livelli di rischio.');
    }
}
