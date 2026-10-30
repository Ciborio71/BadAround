<?php
/**
 * RADAR-PUBLIC-02 — dormant, escaped HTML renderer for aggregated published report counts.
 * No hooks, endpoints, queries, map markers, or automatic frontend registration.
 */
if (!defined('ABSPATH')) exit;
final class BadAround_Radar_Public_Renderer {
    public static function render(array $data) {
        if (($data['contract'] ?? null)!=='badaround-radar-public-counts/v0.1'
            || ($data['unit'] ?? null)!=='published_reports_not_unique_incidents'
            || ($data['temporal_comparison_visible'] ?? null)!==false
            || ($data['risk_score_available'] ?? null)!==false
            || ($data['public_alerts'] ?? null)!==array()
            || !is_array($data['groups'] ?? null)
            || !is_int($data['published_report_count'] ?? null)
            || $data['published_report_count']<0) {
            throw new InvalidArgumentException('Invalid public counts contract');
        }
        $html='<section class="ba-radar-counts" aria-labelledby="ba-radar-counts-title">';
        $html.='<h2 id="ba-radar-counts-title">Segnalazioni pubblicate per territorio e tipologia</h2>';
        $html.='<p>Totale segnalazioni pubblicate: <strong>'.esc_html((string)$data['published_report_count']).'</strong></p>';
        if (($data['date_coverage_sufficient'] ?? null)===false) {
            $html.='<p role="status">Le date disponibili non consentono confronti temporali affidabili.</p>';
        }
        if (!$data['groups']) $html.='<p>Nessuna segnalazione disponibile.</p>';
        else {
            $html.='<table><caption>Conteggi descrittivi, non livelli di rischio</caption><thead><tr><th scope="col">Territorio</th><th scope="col">Tipologia</th><th scope="col">Segnalazioni</th></tr></thead><tbody>';
            foreach ($data['groups'] as $group) {
                $count=$group['published_reports']??null;
                $territory=$group['territory_slug']??null;
                $type=$group['event_type_slug']??null;
                if (!is_int($count)||$count<0||!is_string($territory)||!preg_match('/^[a-z0-9-]+$/D',$territory)
                    ||!is_string($type)||!preg_match('/^[a-z0-9-]+$/D',$type)) throw new InvalidArgumentException('Invalid count row');
                $html.='<tr><th scope="row">'.esc_html($territory).'</th><td>'.esc_html($type).'</td><td>'.esc_html((string)$count).'</td></tr>';
            }
            $html.='</tbody></table>';
        }
        $html.='<p>Le segnalazioni pubblicate non equivalgono a episodi distinti o a un indice di rischio.</p></section>';
        return $html;
    }
}
