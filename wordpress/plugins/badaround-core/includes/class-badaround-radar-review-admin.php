<?php
/** RADAR-QUALITY-04: private read-only review queue. Disabled unless explicitly enabled. */
if (!defined('ABSPATH')) exit;
final class BadAround_Radar_Review_Admin {
    public static function register() {
        if (!defined('BADAROUND_RADAR_QUALITY_REVIEW_PREVIEW') ||
            BADAROUND_RADAR_QUALITY_REVIEW_PREVIEW !== true) return;
        add_action('admin_menu', array(__CLASS__,'menu'));
    }
    public static function menu() {
        if (!current_user_can('manage_options')) return;
        add_management_page('Radar — Qualità eventi','Radar — Qualità eventi',
            'manage_options','badaround-radar-quality',array(__CLASS__,'render'));
    }
    public static function render() {
        if (!current_user_can('manage_options')) wp_die('Accesso negato');
        echo '<div class="wrap"><h1>Radar — Revisione qualità eventi</h1>';
        echo '<p>Anteprima riservata in sola lettura. Nessuna classificazione viene salvata.</p>';
        if (!class_exists('BadAround_Radar_Canonical_Adapter') ||
            !class_exists('BadAround_Radar_Quality_Registry') ||
            !class_exists('BadAround_Discovery_Query')) {
            echo '<p>Moduli di qualità non disponibili.</p></div>'; return;
        }
        try {
            $rows=BadAround_Radar_Canonical_Adapter::rows(new BadAround_Discovery_Query());
            $result=BadAround_Radar_Quality_Registry::classify($rows);
            echo '<p>Test sintetici riconosciuti: '.esc_html((string)$result['synthetic_count'])
                 .' · Da verificare: '.esc_html((string)$result['unverified_count']).'</p>';
            echo '<table class="widefat striped"><thead><tr><th>ID evento</th><th>Stato</th><th>Data disponibile</th></tr></thead><tbody>';
            foreach ($result['items'] as $item) {
                $r=$item['row'];
                echo '<tr><td>'.esc_html((string)$item['id']).'</td><td>'
                    .esc_html($item['status']).'</td><td>'
                    .esc_html((string)($r['occurred_date']??'' )).'</td></tr>';
            }
            echo '</tbody></table>';
        } catch (Throwable $error) {
            echo '<p role="status">Anteprima non disponibile: verifica necessaria.</p>';
        }
        echo '</div>';
    }
}
