<?php
declare(strict_types=1);
define('ABSPATH',__DIR__);
function esc_html($v){return htmlspecialchars($v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
require dirname(__DIR__).'/wordpress/plugins/badaround-core/includes/class-badaround-radar-window-renderer.php';
$d=['contract'=>'badaround-radar-window-counts/v0.1','unit'=>'published_reports_not_unique_incidents',
'period_days'=>30,'archive_reports'=>22,'dated_archive_reports'=>5,'undated_archive_reports'=>17,
'dated_reports_in_period'=>4,'groups'=>[['territory_slug'=>'roma','event_type_slug'=>'veicolo-rubato','published_reports'=>4]],
'date_coverage_sufficient'=>false,'temporal_comparison_visible'=>false,'risk_score_available'=>false,'public_alerts'=>[]];
$s=BadAround_Radar_Window_Renderer::render($d);
foreach(['<dt>Archivio</dt>','<dt>Senza data valida</dt>','ultimi 30 giorni:','Roma','Copertura delle date insufficiente'] as $term)
 if(!str_contains($s,$term))throw new RuntimeException($term);
$d['period_days']=7;$d['dated_reports_in_period']=0;$d['groups']=[];
$s=BadAround_Radar_Window_Renderer::render($d);
if(!str_contains($s,'Nessuna segnalazione datata nel periodo selezionato.'))throw new RuntimeException('Empty state');
echo "RADAR-PUBLIC-06: PASS period summaries and empty state\n";
