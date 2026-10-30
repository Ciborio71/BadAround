<?php
/** RADAR-PUBLIC-06 — dormant, escaped current-period counts. */
if (!defined('ABSPATH')) exit;
final class BadAround_Radar_Window_Renderer {
    public static function render(array $data) {
        if (($data['contract']??null)!=='badaround-radar-window-counts/v0.1'
            || ($data['unit']??null)!=='published_reports_not_unique_incidents'
            || !in_array($data['period_days']??null,array(7,30),true)
            || !is_array($data['groups']??null)
            || ($data['temporal_comparison_visible']??null)!==false
            || ($data['risk_score_available']??null)!==false
            || ($data['public_alerts']??null)!==array()) {
            throw new InvalidArgumentException('Invalid period count contract');
        }
        foreach (array('archive_reports','dated_archive_reports','undated_archive_reports','dated_reports_in_period') as $key) {
            if (!is_int($data[$key]??null) || $data[$key]<0) throw new InvalidArgumentException('Invalid counts');
        }
        if ($data['dated_archive_reports']+$data['undated_archive_reports']!==$data['archive_reports'])
            throw new InvalidArgumentException('Invalid archive split');
        $html='<section class="ba-radar-window-summary" aria-label="Riepilogo segnalazioni Radar">';
        $html.='<h3>Segnalazioni pubblicate</h3>';
        $html.='<p>Archivio: <strong>'.esc_html((string)$data['archive_reports']).'</strong> · Con data valida: <strong>'.esc_html((string)$data['dated_archive_reports']).'</strong> · Senza data valida: <strong>'.esc_html((string)$data['undated_archive_reports']).'</strong></p>';
        $html.='<p>Con data negli ultimi '.esc_html((string)$data['period_days']).' giorni: <strong>'.esc_html((string)$data['dated_reports_in_period']).'</strong></p>';
        if (!$data['date_coverage_sufficient']) {
            $html.='<p role="status">Copertura delle date insufficiente per confronti temporali attendibili.</p>';
        }
        if (!$data['groups']) {
            $html.='<p>Nessuna segnalazione datata nel periodo selezionato.</p>';
        } else {
            $html.='<ul class="ba-radar-window-groups">';
            $sum=0;
            foreach ($data['groups'] as $group) {
                $n=$group['published_reports']??null;
                $territory=$group['territory_slug']??null;
                $type=$group['event_type_slug']??null;
                if (!is_int($n)||$n<=0||!is_string($territory)||!preg_match('/^[a-z0-9-]+$/D',$territory)
                    ||!is_string($type)||!preg_match('/^[a-z0-9-]+$/D',$type))
                    throw new InvalidArgumentException('Invalid group');
                $sum+=$n;
                $html.='<li>'.esc_html($territory).' — '.esc_html($type).': <strong>'.esc_html((string)$n).'</strong></li>';
            }
            if ($sum!==$data['dated_reports_in_period']) throw new InvalidArgumentException('Mismatch between period and groups');
            $html.='</ul>';
        }
        $html.='<p>Conteggi di segnalazioni pubblicate, non episodi distinti o indici di rischio.</p></section>';
        return $html;
    }
}
