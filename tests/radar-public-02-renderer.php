<?php
declare(strict_types=1);
define('ABSPATH',__DIR__);
function esc_html($s){return htmlspecialchars($s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
require_once dirname(__DIR__).'/wordpress/plugins/badaround-core/includes/class-badaround-radar-public-renderer.php';
$data=['contract'=>'badaround-radar-public-counts/v0.1','unit'=>'published_reports_not_unique_incidents',
 'temporal_comparison_visible'=>false,'risk_score_available'=>false,'public_alerts'=>[],
 'published_report_count'=>3,'date_coverage_sufficient'=>false,
 'groups'=>[['territory_slug'=>'roma-roma','event_type_slug'=>'veicolo-rubato','published_reports'=>3]]];
$html=BadAround_Radar_Public_Renderer::render($data);
foreach(['Segnalazioni pubblicate per territorio','roma-roma','veicolo-rubato',
 'non consentono confronti temporali affidabili','non equivalgono a episodi distinti'] as $word)
 if(!str_contains($html,$word)) throw new RuntimeException('Missing: '.$word);
if(str_contains($html,'heatmap')||str_contains($html,'rischio elevato'))throw new RuntimeException('Risk claim');
$data['groups'][0]['territory_slug']='<script>';
try{BadAround_Radar_Public_Renderer::render($data);throw new RuntimeException('Unsafe slug accepted');}
catch(InvalidArgumentException $e){}
echo "RADAR-PUBLIC-02: PASS 7 checks\n";
