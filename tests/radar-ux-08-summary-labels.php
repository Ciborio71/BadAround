<?php
declare(strict_types=1);
define('ABSPATH',__DIR__);
function esc_html($s){return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
require dirname(__DIR__).'/wordpress/plugins/badaround-core/includes/class-badaround-radar-window-renderer.php';
$d=['contract'=>'badaround-radar-window-counts/v0.1','unit'=>'published_reports_not_unique_incidents',
'period_days'=>30,'archive_reports'=>22,'dated_archive_reports'=>5,'undated_archive_reports'=>17,
'dated_reports_in_period'=>1,'groups'=>[['territory_slug'=>'torvaianica','event_type_slug'=>'veicolo-rubato','published_reports'=>1]],
'date_coverage_sufficient'=>false,'temporal_comparison_visible'=>false,'risk_score_available'=>false,'public_alerts'=>[]];
$html=BadAround_Radar_Window_Renderer::render($d);
foreach(['<dl class="ba-radar-summary-stats">','<dt>Archivio</dt>','<dt>Senza data valida</dt>','Torvaianica — Veicolo rubato','Copertura delle date insufficiente'] as $needle)if(!str_contains($html,$needle))throw new RuntimeException('Missing '.$needle);
if(str_contains($html,'veicolo-rubato'))throw new RuntimeException('Raw slug leaked');
echo "RADAR-UX-08: PASS 6 checks\n";
