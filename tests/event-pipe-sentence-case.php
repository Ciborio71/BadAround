<?php
define('ABSPATH',__DIR__);
$registered=[];
function add_filter($name,$callback,$priority=10,$args=1){$GLOBALS['registered'][$name]=$callback;}
function add_action($name,$callback){}
function get_post_type($id){return $id===7?'ba_evento':'post';}
require dirname(__DIR__).'/wordpress/plugins/badaround-core/includes/class-badaround-event-seo.php';
require dirname(__DIR__).'/wordpress/themes/badaround-child/inc/event-heading.php';
function same($actual,$expected){if($actual!==$expected)throw new RuntimeException(var_export([$actual,$expected],true));}
$cases=[
 ['Audi Q8 | furto di parti','Audi Q8 | Furto di parti'],
 ['Auto|veicolo rubato | risolto','Auto|Veicolo rubato | Risolto'],
 ['Auto | è stato ritrovato','Auto | È stato ritrovato'],
 ['Auto | BMW iX3','Auto | BMW iX3'],
 ['Auto | 2026 evento','Auto | 2026 Evento'],
 ['Auto | «furto»','Auto | «Furto»'],
 ['Auto |','Auto |'],
 ['Nessun separatore','Nessun separatore'],
];
foreach($cases as [$input,$expected]){
 same(BadAround_Event_SEO::after_pipe($input),$expected);
 same(BadAround_Event_SEO::after_pipe($expected),$expected);
}
BadAround_Event_SEO::register_hooks();
same($registered['the_title'],['BadAround_Event_SEO','public_title_case']);
same(BadAround_Event_SEO::public_title_case('Auto | furto',7),'Auto | Furto');
same(BadAround_Event_SEO::public_title_case('Articolo | testo',8),'Articolo | testo');
same(badaround_event_heading('Auto | di sera'),'Auto | Di Sera');
echo "PIPE_SENTENCE_CASE_PASS\n";
