<?php
/**
 * RADAR-RC-01: admin-only diagnostic, opt-in via server-side constant.
 * NEVER registers public endpoints, front-end assets, cron or automatic alerts.
 */
if (!defined('ABSPATH')) exit;
final class BadAround_Radar_Private_QA {
    public static function enabled() {
        return defined('BADAROUND_RADAR_PRIVATE_QA_ENABLED')
            && BADAROUND_RADAR_PRIVATE_QA_ENABLED === true;
    }
    public static function register_hooks() {
        if (!self::enabled()) return;
        add_action('admin_menu', array(__CLASS__, 'register_page'));
    }
    public static function register_page() {
        if (!self::enabled() || !current_user_can('manage_options')) return;
        add_management_page('Radar QA', 'Radar QA', 'manage_options',
            'badaround-radar-private-qa', array(__CLASS__, 'render'));
    }
    public static function render() {
        if (!self::enabled() || !current_user_can('manage_options')) {
            wp_die(esc_html__('Accesso negato', 'badaround-core'));
        }
        echo '<div class="wrap"><h1>BadAround Radar — QA riservato</h1>';
        echo '<p>Solo dati aggregati da segnalazioni pubblicate. Non sono episodi distinti o indicatori di rischio.</p>';
        try {
            $today=new DateTimeImmutable('today', wp_timezone());
            $days=isset($_GET['ba_radar_days']) && (string)$_GET['ba_radar_days']==='30' ? 30 : 7;
            $result=BadAround_Radar_Offline_Bridge::snapshot(new BadAround_Discovery_Query(),$today,$days);
            $view=$result['view'];
            $quality=$result['quality'];
            $dated=(int)($quality['date_coverage_count'] ?? 0);
            $undated=(int)($quality['undated_or_invalid_count'] ?? 0);
            $total=$dated+$undated;
            echo '<p>Periodo: '.esc_html((string)$days).' giorni. Stato: anteprima interna.</p>';
            echo '<p><strong>Qualità dei dati temporali:</strong> '.esc_html((string)$dated).' segnalazioni con data, '.esc_html((string)$undated).' senza data valida, su '.esc_html((string)$total).' segnalazioni pubblicate.</p>';
            if ($total===0 || $undated*5>$total) {
                echo '<p role="status"><strong>Copertura temporale insufficiente:</strong> le variazioni non sono utilizzabili come indicatori di andamento. Le date mancanti non vengono sostituite con la data di pubblicazione.</p>';
            }
            if (!$view['cards']) {
                echo '<p>Nessun gruppo con dati temporali sufficienti per il periodo selezionato.</p>';
            }
            echo '<table class="widefat striped"><thead><tr><th>Territorio</th><th>Tipo</th><th>Segnalazioni nel periodo</th><th>Confronto</th></tr></thead><tbody>';
            foreach($view['cards'] as $card) {
                echo '<tr><td>'.esc_html($card['territory_slug']).'</td><td>'.esc_html($card['event_type_slug']).'</td><td>'.esc_html((string)$card['current_reports']).'</td><td>';
                echo $card['comparison_displayable']
                    ? esc_html((string)$card['delta_reports']).' rispetto al periodo precedente'
                    : 'Dati insufficienti';
                echo '</td></tr>';
            }
            echo '</tbody></table>';
        } catch (Throwable $error) {
            // Never leak internal diagnostics into public endpoints or the admin HTML.
            echo '<p role="status">Anteprima non disponibile: verifica necessaria.</p>';
        }
        echo '</div>';
    }
}
