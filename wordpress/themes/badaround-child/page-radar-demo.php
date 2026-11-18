<?php
/**
 * Template Name: BadAround — Radar Demo
 * Public-feed observatory presentation; no private report data.
 */
if (!defined('ABSPATH')) exit;
// This validation-only synthetic experience must not be indexed.
add_filter('wp_robots', static function ($robots) { $robots['noindex'] = true; $robots['nofollow'] = true; return $robots; });
// Load the same certified navigation-control component used by Home and Mappe.
$radar_navigation_file = get_stylesheet_directory() . '/assets/js/map-navigation.js';
wp_enqueue_script('badaround-map-navigation',get_stylesheet_directory_uri().'/assets/js/map-navigation.js',array(),(string)filemtime($radar_navigation_file),true);
get_header();
?>
<main class="ba-radar-demo" id="main-content">
<header class="rad-v2-head"><p>Osservatorio del territorio</p><h1>Radar BadAround</h1><div>Una panoramica delle segnalazioni condivise dalla community.</div><aside>ⓘ Segnalazioni della community, non statistiche ufficiali.</aside></header>
 
 <div class="ba-radar-controls" aria-label="Filtri Radar">
  <div class="ba-radar-search"><label for="rad-place">Cerca un luogo reale</label><div class="ba-radar-search-row"><input id="rad-place" type="search" value="" placeholder="Cerca un luogo" aria-label="Cerca un luogo" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="rad-suggestions" /><button id="rad-nearby" type="button" aria-label="Esplora la zona attorno alla mia posizione">⌖ Vicino a me</button></div><div id="rad-suggestions" class="ba-radar-suggestions" role="listbox" aria-label="Suggerimenti indirizzi" hidden></div></div>
  <label>Categoria<select id="rad-cat"><option>Tutte le categorie</option><option>Veicoli</option><option>Pericoli</option><option>Spazi pubblici</option><option>Animali</option><option>Case e attività</option><option>Oggetti e documenti</option></select></label>
  <label>Data episodio<select id="rad-period"><option value="all" selected>Tutti i periodi</option><option value="30">30 giorni</option><option value="7">7 giorni</option><option value="90">90 giorni</option><option value="365">12 mesi</option></select></label>
  <label>Raggio<select id="rad-radius"><option value="1">1 km</option><option value="0.5">500 m</option><option value="2">2 km</option><option value="5">5 km</option></select></label>
  <button id="rad-reset" class="ba-radar-reset-visible" type="button" title="Cancella la zona memorizzata e tutti i filtri">↺ Reimposta</button>
 </div>
 <p id="rad-scope" class="rad-v2-scope" aria-live="polite">Area: Italia · Seleziona un luogo per applicare un raggio di ricerca.</p><div class="ba-radar-workspace">
  <section class="ba-radar-map" aria-label="Cartografia Google Maps e segnalazioni pubbliche">
   <div id="rad-google-map" class="ba-radar-google-map" role="application" aria-label="Google Maps — geometrie delle segnalazioni pubblicate"></div>
   
   <div id="rad-map-message" class="ba-radar-map-message" role="status" aria-live="polite">Caricamento cartografia Google Maps…</div>
   
   <div class="ba-radar-visual-key"><strong>Distribuzione delle segnalazioni</strong><span>I conteggi raggruppano le posizioni pubbliche, non indicano il punto di un singolo episodio.</span><small>Punto esatto · Area approssimata</small></div>

  </section>
  <aside class="ba-radar-panel" id="rad-panel">
   <span class="ba-radar-tag">Radar · Problematiche territoriali</span>
   <h2 id="rad-title">Panoramica territoriale</h2>
   <p id="rad-copy" role="status" aria-live="polite">Caricamento delle segnalazioni pubbliche…</p>
   
   <div id="rad-reports" class="ba-radar-reports" aria-label="Problematiche segnalate nella zona"></div>

	  
<?php
// RADAR-PUBLIC-06 — Anteprima riservata agli amministratori.
if (
    defined('BADAROUND_RADAR_PUBLIC_COUNTS_PREVIEW_ENABLED')
    && BADAROUND_RADAR_PUBLIC_COUNTS_PREVIEW_ENABLED === true
    && is_user_logged_in()
    && current_user_can('manage_options')
) {
    $ba_radar_dir = WP_PLUGIN_DIR . '/badaround-core/includes/';

    $ba_radar_files = array(
        'class-badaround-intelligence-trends.php',
        'class-badaround-intelligence-peak-candidates.php',
        'class-badaround-intelligence-data-quality.php',
        'class-badaround-radar-view-model.php',
        'class-badaround-radar-canonical-adapter.php',
        'class-badaround-radar-offline-bridge.php',
        'class-badaround-radar-public-counts.php',
        'class-badaround-radar-public-renderer.php',
        'class-badaround-radar-window-counts.php',
        'class-badaround-radar-window-renderer.php',
    );

    $ba_radar_ready = class_exists('BadAround_Discovery_Query');

    foreach ($ba_radar_files as $ba_radar_file) {
        if (!is_readable($ba_radar_dir . $ba_radar_file)) {
            $ba_radar_ready = false;
            break;
        }
    }

    if ($ba_radar_ready) {
        foreach ($ba_radar_files as $ba_radar_file) {
            require_once $ba_radar_dir . $ba_radar_file;
        }

        try {
            $ba_radar_discovery = new BadAround_Discovery_Query();
            $ba_radar_today = new DateTimeImmutable(
                'today',
                wp_timezone()
            );

            $ba_radar_snapshot =
                BadAround_Radar_Offline_Bridge::snapshot(
                    $ba_radar_discovery,
                    $ba_radar_today,
                    30
                );

            $ba_radar_rows =
                BadAround_Radar_Canonical_Adapter::rows(
                    $ba_radar_discovery
                );

            $ba_radar_public =
                BadAround_Radar_Public_Counts::present(
                    $ba_radar_rows,
                    $ba_radar_snapshot['quality']
                );

            $ba_radar_trends =
                BadAround_Intelligence_Trends::summarize(
                    $ba_radar_rows,
                    $ba_radar_today,
                    30
                );

            $ba_radar_window =
                BadAround_Radar_Window_Counts::present(
                    $ba_radar_public,
                    $ba_radar_trends,
                    $ba_radar_snapshot['quality']
                );

            echo '<div class="ba-radar-public-preview" aria-label="Anteprima riservata dei conteggi Radar">';

            echo BadAround_Radar_Window_Renderer::render(
                $ba_radar_window
            );

            echo '</div>';

        } catch (Throwable $ba_radar_error) {
            echo '<p role="status">Anteprima Radar non disponibile: verifica dei dati necessaria.</p>';
        }
    }
}
?>

	  
	  <p class="ba-radar-note ba-radar-final-note">Statistiche delle segnalazioni pubbliche della community, non dati ufficiali su reati o sicurezza. Le segnalazioni senza data sono escluse dai confronti temporali e le aree approssimate non identificano un punto esatto.</p>
   <a class="ba-radar-back" href="<?php echo esc_url(home_url('/mappa/')); ?>">Apri la mappa delle segnalazioni reali →</a>
  </aside>
 </div>
 <button id="rad-drawer" type="button" aria-controls="rad-panel" aria-expanded="false">Mostra analisi zona</button>
 <section id="rad-summary" class="rad-v2-summary" aria-live="polite"></section><p class="ba-radar-foot">La distribuzione dipende dalla partecipazione della community e dalla copertura dei dati.</p>
</main>
<style>
body.page-template-page-radar-demo{overflow:auto!important}
body.page-template-page-radar-demo .ba-site-footer{display:block!important}
body.page-template-page-radar-demo .ba-radar-demo{display:block!important;height:auto!important;min-height:80vh;overflow:visible!important;background:#f4f9fc;color:#071a38;padding:28px max(20px,3vw) 32px}
.ba-radar-demo *{box-sizing:border-box}
.rad-v2-head{max-width:1500px;margin:auto auto 22px}
.rad-v2-head>p{text-transform:uppercase;letter-spacing:.16em;color:#087f8c;font-weight:800;font-size:14px;margin:0 0 10px}
.rad-v2-head h1{font-size:clamp(32px,3.6vw,52px);letter-spacing:-.035em;line-height:1.1;margin:0 0 8px;color:#071a38}
.rad-v2-head>div{font-size:18px;color:#486478}
.rad-v2-head aside{background:#e6f2f7;padding:9px 14px;border-radius:10px;margin-top:20px;font-size:14px}
body.page-template-page-radar-demo .ba-radar-controls{max-width:1500px;margin:auto;display:flex!important;flex-wrap:wrap!important;gap:18px!important;align-items:end!important;padding:16px!important;background:#fff;border:1px solid #dce7ed;border-radius:18px;height:auto!important;max-height:none!important;box-shadow:0 5px 16px #0736470a}
.ba-radar-controls>label{display:grid;gap:5px;font-size:12px;color:#486478;flex:1 1 140px}
.ba-radar-controls input,.ba-radar-controls select,.ba-radar-controls button{height:42px;border:1px solid #cddfe7;border-radius:12px;background:#fff;color:#071a38;padding:0 12px;font:inherit;font-size:14px;max-width:100%}
body.page-template-page-radar-demo .ba-radar-controls .ba-radar-search{position:relative;flex:2 1 340px!important;width:auto!important;min-width:0;max-width:none!important}
.ba-radar-search>label{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0)}
.ba-radar-search-row{display:flex;gap:12px}
.ba-radar-search-row input{width:100%;min-width:0;flex:1}
.ba-radar-search-row button,.ba-radar-reset-visible{color:#087f8c!important;border-color:#087f8c!important;background:white!important;font-weight:750!important;cursor:pointer;white-space:nowrap}
.ba-radar-controls select:disabled{opacity:.5}
.ba-radar-suggestions{position:absolute;z-index:100;background:white;border:1px solid #dce7ed;border-radius:12px;box-shadow:0 10px 30px #071a3820;width:100%;max-height:300px;overflow:auto}
.ba-radar-suggestions button{display:block;height:auto;width:100%;padding:12px;border:0;text-align:left}
.ba-radar-suggestions strong,.ba-radar-suggestions small{display:block}
.rad-v2-scope{max-width:1500px;margin:14px auto;font-size:14px;color:#526b80}
body.page-template-page-radar-demo .ba-radar-workspace{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.05fr);gap:20px;max-width:1500px;margin:auto;padding:0}
body.page-template-page-radar-demo .ba-radar-panel{position:static;transform:none;max-height:none;overflow:visible;grid-column:1;grid-row:1;min-width:0;background:#fff;border:1px solid #dce7ed;border-radius:18px;padding:14px;display:block}
.ba-radar-panel>.ba-radar-tag,.ba-radar-panel>h2{display:none}
.ba-radar-panel>p{font-size:13px;color:#526b80;line-height:1.5}
body.page-template-page-radar-demo #rad-reports{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;max-height:none;overflow:visible;margin:0}
.ba-observatory-kpis{grid-column:1/-1;display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}
.ba-observatory-kpi{padding:14px 12px;background:#edf7fa;border:1px solid #deedf2;border-radius:14px}
.ba-observatory-kpi strong{display:block;font-size:30px;line-height:1.1;margin-bottom:8px}
.ba-observatory-kpi small{font-size:13px;line-height:1.3;display:block}
.ba-observatory-ranking,.rad-v2-quality{min-width:0;padding:14px;border:1px solid #dce7ed;border-radius:14px;background:#fff}
.ba-observatory-ranking h3,.rad-v2-quality h3{font-size:17px;line-height:1.2;margin:0 0 12px;letter-spacing:-.02em}
.ba-observatory-rank{display:grid;gap:5px;padding:5px 0;border:0;background:none;width:100%;text-align:left;color:#294b60;font-family:inherit}
button.ba-observatory-rank{cursor:pointer}
.ba-observatory-rank-top{display:flex;justify-content:space-between;gap:10px;font-size:13px;line-height:1.35}
.ba-observatory-track{height:7px;background:#e3edf1;border-radius:9px;overflow:hidden}
.ba-observatory-track>span{display:block;height:100%;background:#06939d;border-radius:9px}
.rad-v2-quality p{font-size:13px;border-bottom:1px solid #e3edf1;padding-bottom:10px;display:flex;justify-content:space-between;gap:12px}
.ba-radar-note,.ba-observatory-head,.ba-radar-back-drill,.ba-radar-quality,.ba-radar-guidance,.ba-radar-metrics-preview{grid-column:1/-1}
.ba-radar-note{font-size:12px;background:#eef7fa;padding:12px;border-radius:10px;color:#526b80;line-height:1.5}
.ba-observatory-head{display:none}
.ba-radar-back{display:inline-block;color:#087f8c;font-weight:700;font-size:14px;margin-top:8px}
.ba-radar-back-drill{border:1px solid #087f8c;background:#eff9fb;color:#087184;padding:10px;border-radius:10px;cursor:pointer}
body.page-template-page-radar-demo .ba-radar-map{grid-column:2;grid-row:1;position:relative;min-height:640px;border:10px solid white;border-radius:18px;overflow:hidden;background:#e7f2f4;box-shadow:0 0 0 1px #dce7ed}
.ba-radar-google-map{position:absolute;inset:0;width:100%;height:100%}
.ba-radar-map-message{position:absolute;z-index:3;top:70px;left:12px;background:white;border-radius:10px;padding:12px;font-size:13px;max-width:85%}
.ba-radar-visual-key{position:absolute;z-index:2;bottom:24px;left:14px;max-width:270px;display:grid;gap:7px;background:#fff;border-radius:14px;padding:14px;font-size:12px;box-shadow:0 5px 15px #071a381a}
.ba-radar-visual-key strong{font-size:15px}
.rad-v2-cluster{position:absolute;display:grid;place-items:center;transform:translate(-50%,-50%);width:42px;height:42px;border:3px solid white;border-radius:50%;background:#079798;color:white;font-size:17px;font-weight:800;box-shadow:0 3px 8px #071a3830;pointer-events:none}
.rad-v2-summary{max-width:1500px;margin:20px auto 0;padding:18px 24px;background:white;border:1px solid #dce7ed;border-radius:18px;display:flex;gap:24px;justify-content:space-between;flex-wrap:wrap;font-size:15px}
.rad-v2-summary>strong{font-size:22px}
.ba-radar-foot{max-width:1500px;margin:12px auto 0;font-size:13px;color:#526b80}
#rad-drawer{display:none!important}
.ba-radar-demo button:focus-visible,.ba-radar-demo a:focus-visible,.ba-radar-demo input:focus-visible,.ba-radar-demo select:focus-visible{outline:3px solid #0798a8;outline-offset:3px}
@media(max-width:1000px){body.page-template-page-radar-demo .ba-radar-workspace{grid-template-columns:1fr}body.page-template-page-radar-demo .ba-radar-map{grid-column:1;grid-row:2;min-height:480px}.ba-observatory-kpi strong{font-size:26px}}
@media(max-width:550px){body.page-template-page-radar-demo .ba-radar-demo{padding:22px 14px}.ba-observatory-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}body.page-template-page-radar-demo #rad-reports{grid-template-columns:1fr}.ba-radar-search-row{flex-wrap:wrap}.ba-radar-search-row input{flex-basis:100%}.ba-radar-visual-key{max-width:220px;font-size:11px}.rad-v2-summary{display:grid;gap:12px}}

/* Radar reference layout: balanced information/map blocks and a full-width summary. */
body.page-template-page-radar-demo .ba-radar-workspace{gap:14px;align-items:stretch;grid-template-columns:minmax(0,1fr) minmax(0,1.03fr)}
body.page-template-page-radar-demo .ba-radar-panel{padding:14px}
body.page-template-page-radar-demo .ba-radar-map{min-height:610px}
.ba-radar-panel:has(.ba-observatory-kpis)>#rad-copy{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0)}
.ba-radar-panel .ba-radar-final-note{display:none}
.ba-observatory-kpi{position:relative;display:grid;grid-template-columns:25px minmax(0,1fr);gap:5px 8px;align-content:start}
.ba-observatory-kpi svg{width:24px;height:26px;fill:none;stroke:#049893;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.ba-observatory-kpi strong{grid-column:2;margin:0;font-size:30px}
.ba-observatory-kpi small{grid-column:2;font-size:12px}
.rad-v3-timeline>p{font-size:12px;color:#526b80;margin:0 0 12px}
.rad-v3-bars{display:flex;gap:10px;align-items:end;min-height:136px;padding:0 3px}
.rad-v3-bar{flex:1;min-width:0;display:flex;flex-direction:column;align-items:center;justify-content:end;gap:5px}
.rad-v3-bar>span{display:block;width:min(100%,34px);background:linear-gradient(#36b6b0,#009591);border-radius:4px 4px 0 0}
.rad-v3-bar strong{font-size:11px;color:#526b80}
.rad-v3-bar small{font-size:10px;line-height:1.2;text-align:center;white-space:normal;color:#526b80}
.rad-v2-quality{display:grid;grid-template-columns:105px minmax(0,1fr);gap:10px;align-content:start;align-items:center}
.rad-v2-quality h3{grid-column:1/-1;margin-bottom:8px}
.rad-v2-quality p{grid-column:2;margin:0;font-size:12px;padding:8px 0}
.rad-v3-donut{grid-column:1;grid-row:2/4;width:100px;height:100px;border-radius:50%;background:conic-gradient(#009b9b var(--dated-share),#b4e3e7 0);position:relative;display:flex;flex-direction:column;align-items:center;justify-content:center;isolation:isolate}
.rad-v3-donut:before{content:"";position:absolute;inset:16px;background:white;border-radius:50%;z-index:-1}
.rad-v3-donut strong{font-size:26px;line-height:1.1}
.rad-v3-donut small{font-size:12px}
.rad-v3-context{margin:0}
body.page-template-page-radar-demo .ba-radar-map .ba-map-pan{display:none!important}
.rad-v2-summary{align-items:center;gap:18px}
.rad-v2-summary>strong{padding-left:18px;border-left:4px solid #f21f41}
.rad-v2-summary>span{flex:1;min-width:170px;padding-left:18px;border-left:1px solid #dce7ed;line-height:1.4}
@media(max-width:1100px){.ba-observatory-kpi{grid-template-columns:1fr}.ba-observatory-kpi strong,.ba-observatory-kpi small{grid-column:1}.rad-v2-quality{grid-template-columns:1fr}.rad-v2-quality p{grid-column:1}.rad-v3-donut{grid-row:auto;grid-column:1}}
@media(max-width:1000px){body.page-template-page-radar-demo .ba-radar-workspace{grid-template-columns:1fr}body.page-template-page-radar-demo .ba-radar-map{min-height:440px}.ba-observatory-kpi{grid-template-columns:25px minmax(0,1fr)}.ba-observatory-kpi strong,.ba-observatory-kpi small{grid-column:2}}
@media(max-width:550px){.rad-v2-summary>span{border-left:0;padding-left:0}.ba-observatory-kpi{grid-template-columns:1fr}.ba-observatory-kpi strong,.ba-observatory-kpi small{grid-column:1}.rad-v2-quality{grid-template-columns:105px minmax(0,1fr)}.rad-v2-quality p{grid-column:2}.rad-v3-donut{grid-column:1;grid-row:2/4}}
/* Compact paired observatory: content determines both blocks, never an inner scrollbar. */
@media(min-width:1001px){
 body.page-template-page-radar-demo .ba-radar-demo{padding-top:16px}
 .rad-v2-head{position:relative;margin-bottom:14px;padding-right:280px}
 .rad-v2-head>p{font-size:12px;margin-bottom:6px}
 .rad-v2-head h1{font-size:40px;margin-bottom:4px}
 .rad-v2-head>div{font-size:16px;line-height:1.35}
 .rad-v2-head aside{position:absolute;right:0;top:8px;width:255px;margin:0;padding:12px 16px;border-left:1px solid #b7dbe4;background:transparent;line-height:1.4}
 body.page-template-page-radar-demo .ba-radar-controls{padding:10px 14px!important;gap:14px!important;flex-wrap:nowrap!important}
 body.page-template-page-radar-demo .ba-radar-controls .ba-radar-search{flex:2 1 330px!important}
 .ba-radar-controls>label{flex:1 1 110px;min-width:0}
 .rad-v2-scope{margin:8px auto;font-size:13px;line-height:1.4}
 body.page-template-page-radar-demo .ba-radar-workspace{height:auto;min-height:0}
 body.page-template-page-radar-demo .ba-radar-map{height:auto;min-height:380px!important;max-height:none}
 body.page-template-page-radar-demo .ba-radar-panel{height:auto;min-height:0;overflow:visible;padding:10px}
 body.page-template-page-radar-demo #rad-reports{gap:8px}
 .ba-observatory-kpis{gap:8px}
 .ba-observatory-kpi{padding:9px 8px;grid-template-columns:23px minmax(0,1fr);gap:3px 5px}
 .ba-observatory-kpi strong,.ba-observatory-kpi small{grid-column:2}
 .ba-observatory-kpi strong{font-size:28px;line-height:1}
 .ba-observatory-kpi small{font-size:11px;line-height:1.2}
 .ba-observatory-kpi svg{width:22px;height:24px}
 .ba-observatory-ranking,.rad-v2-quality{padding:9px 10px}
 .ba-observatory-ranking h3,.rad-v2-quality h3{font-size:15px;margin-bottom:6px;line-height:1.2}
 .ba-observatory-rank{padding:2px 0;gap:2px}
 .ba-observatory-rank-top{font-size:12px;line-height:1.2}
 .ba-observatory-track{height:5px}
 .rad-v3-timeline>p{font-size:11px;margin-bottom:5px;line-height:1.2}
 .rad-v3-bars{min-height:105px;gap:6px}
 .rad-v3-bar{gap:3px}
 .rad-v3-bar>span{max-height:64px}
 .rad-v2-quality{grid-template-columns:86px minmax(0,1fr);gap:6px}
 .rad-v2-quality p{grid-column:2;font-size:11px;padding:5px 0;line-height:1.3}
 .rad-v3-donut{width:82px;height:82px;grid-column:1;grid-row:2/4}
 .rad-v3-donut:before{inset:13px}
 .rad-v3-donut strong{font-size:24px}
 .rad-v3-context{font-size:11px;padding:7px 10px;line-height:1.3}
 .ba-radar-back{font-size:12px;margin-top:5px;line-height:1.3}
 .rad-v2-summary{margin-top:14px;padding:12px 20px;font-size:13px}
 .rad-v2-summary>strong{font-size:20px}
 .ba-radar-visual-key{bottom:14px;padding:10px;gap:4px;font-size:11px}
}
@media(min-width:1001px) and (max-width:1190px){
 .ba-radar-search-row{gap:6px}
 .ba-radar-controls input,.ba-radar-controls select,.ba-radar-controls button{padding:0 8px;font-size:12px}
 .ba-observatory-kpi{grid-template-columns:1fr}
 .ba-observatory-kpi svg{display:none}
 .ba-observatory-kpi strong,.ba-observatory-kpi small{grid-column:1}
}
@media(max-width:1000px){body.page-template-page-radar-demo .ba-radar-map{height:400px;min-height:0!important}}
</style>
<?php
$radar_maps_settings = get_option('wpforms_settings',array());
$radar_maps_key = is_array($radar_maps_settings) && !empty($radar_maps_settings['geolocation-google-places-api-key'])
 ? trim((string)$radar_maps_settings['geolocation-google-places-api-key']) : '';
$radar_maps_url = $radar_maps_key ? add_query_arg(array('key'=>$radar_maps_key,'v'=>'weekly','loading'=>'async'),'https://maps.googleapis.com/maps/api/js') : '';
?>
<?php
/* Public taxonomy labels only. No raw report metadata or private coordinates. */
$radar_territory_tree = array();
$radar_terms = get_terms(array('taxonomy'=>'ba_territorio','hide_empty'=>true));
if (!is_wp_error($radar_terms)) {
 foreach ($radar_terms as $radar_term) {
  $ancestors = array_reverse(get_ancestors($radar_term->term_id,'ba_territorio','taxonomy'));
  $chain = array();
  foreach (array_merge($ancestors,array($radar_term->term_id)) as $term_id) {
   $term = get_term($term_id,'ba_territorio');
   if ($term instanceof WP_Term) $chain[] = $term->name;
  }
  $radar_territory_tree[(string)$radar_term->term_id] = $chain;
 }
}
?>
<script>window.BadAroundRadarTerritories=<?php echo wp_json_encode($radar_territory_tree); ?>;</script>
<script>window.BadAroundRadarMaps=<?php echo wp_json_encode(array('mapsUrl'=>$radar_maps_url)); ?>;</script>
<script>
(()=>{
'use strict';
const root=document.querySelector('.ba-radar-demo');if(!root)return;
const $=id=>root.querySelector('#'+id);
const ITALY_BOUNDS={north:47.2,south:35.4,west:6.5,east:18.7};
function frameItaly(){if(map)map.fitBounds(ITALY_BOUNDS,12);}
let map=null,geocoder=null,searchCircle=null,centerMarker=null,state='sample',layer=false;
const initial={place:'',cat:'Tutte le categorie',period:'all',radius:'1'};
const config=window.BadAroundRadarMaps||{};
const endpoint=<?php echo wp_json_encode(esc_url_raw(rest_url('badaround/v1/discovery'))); ?>;
let reports=[],geometryLayers=[],loadingReports=true,loadError=false,chosenCenter=null;
const status=message=>{$('rad-map-message').textContent=message;$('rad-map-message').hidden=!message;};
const radiusMeters=()=>Number($('rad-radius').value)*1000;
const LAST_AREA_KEY='badaround-radar-last-area-v1';
function rememberArea(center,label){
 if(!center||!Number.isFinite(center.lat)||!Number.isFinite(center.lng)||Math.abs(center.lat)>90||Math.abs(center.lng)>180)return;
 const data={version:1,lat:center.lat,lng:center.lng,label:String(label||'Zona consultata').slice(0,180)};
 try{window.localStorage.setItem(LAST_AREA_KEY,JSON.stringify(data));}catch(e){/* browsing remains functional if storage is blocked */}
}
function restoreArea(){
 let raw=null;try{raw=window.localStorage.getItem(LAST_AREA_KEY);}catch(e){return false;}
 if(!raw)return false;
 try{
  const data=JSON.parse(raw);
  if(data.version!==1||typeof data.label!=='string'||!data.label.trim()||data.label.length>180
   ||!Number.isFinite(data.lat)||!Number.isFinite(data.lng)||Math.abs(data.lat)>90||Math.abs(data.lng)>180)return false;
  focusLocation({lat:data.lat,lng:data.lng},data.label,false);
  status('Ripristinata l’ultima zona consultata su questo browser.');
  return true;
 }catch(e){return false;}
}

const validGeo=g=>g&&g.version==='public-location/v1'&&g.source==='event_public'&&
 ['exact_point','approximate_area'].includes(g.mode)&&Number.isFinite(g.lat)&&Number.isFinite(g.lng)&&
 Math.abs(g.lat)<=90&&Math.abs(g.lng)<=180&&(g.mode==='exact_point'||Number.isFinite(g.radius_meters)&&g.radius_meters>0);
const category=e=>{
 const t=(e.event_type?.name||'').toLowerCase();
 const x=(e.event_type?.slug||'').toLowerCase();
 if(/veicol|auto|moto|sosta|furto.*mezzo/.test(t+' '+x))return 'Veicoli';
 if(/rifiut|strada|pericol|fumo|incend|alber|ostacol/.test(t+' '+x))return /rifiut/.test(t+' '+x)?'Spazi pubblici':'Pericoli';
 if(/animal/.test(t+' '+x))return 'Animali';
 if(/cas|abitaz|attivit|negoz/.test(t+' '+x))return 'Case e attività';
 if(/oggett|document/.test(t+' '+x))return 'Oggetti e documenti';
 return null;
};
function eligible(e){
 if(!validGeo(e.public_geo))return false;
 if($('rad-cat').value!=='Tutte le categorie'&&category(e)!==$('rad-cat').value)return false;
 const period=$('rad-period').value;
 if(period!=='all'){
  const date=episodeDate(e.occurred_date);if(!date)return false;
  const days=(Date.now()-date.getTime())/86400000;
  if(days<0||days>Number(period))return false;
 }
 if(chosenCenter){
  const g=e.public_geo,c=chosenCenter;
  const rad=Math.PI/180,dl=(g.lng-c.lng)*rad,da=(g.lat-c.lat)*rad;
  const hav=Math.sin(da/2)**2+Math.cos(g.lat*rad)*Math.cos(c.lat*rad)*Math.sin(dl/2)**2;
  const d=12742000*Math.asin(Math.min(1,Math.sqrt(hav)));
  // Approximate event is included only when its entire PUBLIC uncertainty circle
  // lies inside the requested region; do not present its center as a precise position.
  if(!Number.isFinite(d)||d+(g.mode==='approximate_area'?g.radius_meters:0)>radiusMeters())return false;
 }
 return true;
}
function clearGeometry(){for(const g of geometryLayers)g.setMap(null);geometryLayers=[];}
function episodeDate(value){
 const raw=String(value||'').trim();let m=raw.match(/^(\d{4})-(\d{2})-(\d{2})$/);
 if(!m){const it=raw.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);if(it)m=[raw,it[3],it[2],it[1]];}
 if(!m)return null;const dt=new Date(Date.UTC(+m[1],+m[2]-1,+m[3]));
 return dt.getUTCFullYear()===+m[1]&&dt.getUTCMonth()===+m[2]-1&&dt.getUTCDate()===+m[3]?dt:null;
}
const numberIT=new Intl.NumberFormat('it-IT');
const sentenceCase=value=>{const text=String(value||'').trim().toLocaleLowerCase('it-IT');return text?text[0].toLocaleUpperCase('it-IT')+text.slice(1):'';};
const territoryTree=window.BadAroundRadarTerritories||{};
let drill={level:0,region:null,province:null,comune:null};
const depthLabels=['Regioni','Province','Comuni','Località'];
function territoryPath(event){
 const id=String(event.territory?.id||'');
 return Array.isArray(territoryTree[id])?territoryTree[id]:[];
}
function matchesDrill(e){
 const path=territoryPath(e);
 return (!drill.region||path[0]===drill.region)&&(!drill.province||path[1]===drill.province)&&(!drill.comune||path[2]===drill.comune);
}
function zoomToDrill(){
 if(!map)return;
 if(drill.level===0){frameItaly();return;}
 const parts=[drill.comune,drill.province,drill.region,'Italia'].filter(Boolean);
 if(!geocoder||!parts.length)return;
 geocoder.geocode({address:parts.join(', '),region:'IT'},(results,status)=>{
  if(status!=='OK'||!results?.length)return;
  const result=results[0];
  if(result.geometry?.viewport)map.fitBounds(result.geometry.viewport,30);
  else if(result.geometry?.location){map.setCenter(result.geometry.location);map.setZoom(drill.level===1?8:drill.level===2?10:12);}
 });
}
function backDrill(){
 if(drill.level===3){drill.comune=null;drill.level=2;}
 else if(drill.level===2){drill.province=null;drill.level=1;}
 else if(drill.level===1){drill.region=null;drill.level=0;}
 render();zoomToDrill();
}

function showObservatory(items,host){
 const types=new Map(),places=new Map(),months=new Map();
 let dated=0,undated=0;
 for(const e of items){
  const type=sentenceCase(e.event_type?.name||'Altro');
  types.set(type,(types.get(type)||0)+1);
  const chain=territoryPath(e);
  const name=chain[drill.level]||null;
  if(name)places.set(name,(places.get(name)||0)+1);
  const dt=episodeDate(e.occurred_date);
  if(dt){dated++;const month=dt.toISOString().slice(0,7);months.set(month,(months.get(month)||0)+1);}else undated++;
 }
 const metrics=document.createElement('div');metrics.className='ba-observatory-kpis';
 for(const [index,[value,label]] of [
  [items.length,'Segnalazioni pubbliche geolocalizzate'],
  [types.size,'Tipologie rappresentate'],
  [places.size,'Territori rappresentati'],
  [dated,'Con data dell’episodio']
 ].entries()){
  const card=document.createElement('div');card.className='ba-observatory-kpi';
  const icon=document.createElementNS('http://www.w3.org/2000/svg','svg');icon.setAttribute('viewBox','0 0 24 24');icon.setAttribute('aria-hidden','true');const path=document.createElementNS('http://www.w3.org/2000/svg','path');
  path.setAttribute('d',[
  'M5 5a10 10 0 0 0 0 14 M19 5a10 10 0 0 1 0 14 M8 8a6 6 0 0 0 0 8 M16 8a6 6 0 0 1 0 8 M12 10v4',
  'M4 3h16v18H4Z M8 7h8 M8 11h8 M8 15h5',
  'M8 10a3 3 0 1 0 0-6 3 3 0 0 0 0 6 M16 10a3 3 0 1 0 0-6 3 3 0 0 0 0 6 M2 21v-4a6 6 0 0 1 12 0v4 M14 12a6 6 0 0 1 8 5v4',
  'M4 5h16v16H4Z M4 10h16 M8 2v6 M16 2v6'
  ][index]);icon.append(path);card.append(icon);
  const large=document.createElement('strong');large.textContent=numberIT.format(value);
  const caption=document.createElement('small');caption.textContent=label;
  card.append(large,caption);metrics.append(card);
 }
 host.append(metrics);
 const createRanking=(title,records,cap,onPick)=>{
  const section=document.createElement('section');section.className='ba-observatory-ranking';
  const h=document.createElement('h3');h.textContent=title;section.append(h);
  const ordered=[...records.entries()].sort((a,b)=>b[1]-a[1]||a[0].localeCompare(b[0],'it')).slice(0,cap);
  const max=ordered[0]?.[1]||1;
  for(const [label,count] of ordered){
   const control=document.createElement(onPick?'button':'div');control.className='ba-observatory-rank';
   if(onPick){control.type='button';control.addEventListener('click',()=>onPick(label));}
   const top=document.createElement('span');top.className='ba-observatory-rank-top';
   const name=document.createElement('span');name.textContent=sentenceCase(label);
   const quantity=document.createElement('strong');quantity.textContent=numberIT.format(count);
   top.append(name,quantity);
   const track=document.createElement('span');track.className='ba-observatory-track';
   const bar=document.createElement('span');bar.style.width=(count/max*100).toFixed(2)+'%';track.append(bar);
   control.append(top,track);section.append(control);
  }
  host.append(section);
 };
 createRanking('Tipologie più segnalate',types,5,null);
 createRanking(depthLabels[drill.level]+' per segnalazioni',places,6,label=>{
  if(drill.level===0){drill.region=label;drill.level=1;}
  else if(drill.level===1){drill.province=label;drill.level=2;}
  else if(drill.level===2){drill.comune=label;drill.level=3;}
  else{input.value=label;locate();return;}
  render();zoomToDrill();
 });
 const timeline=document.createElement('section');timeline.className='ba-observatory-ranking rad-v3-timeline';
 const timelineTitle=document.createElement('h3');timelineTitle.textContent='Mesi degli episodi datati';timeline.append(timelineTitle);
 const timelineCaption=document.createElement('p');timelineCaption.textContent='Solo episodi con data disponibile.';timeline.append(timelineCaption);
 if(dated){const chart=document.createElement('div');chart.className='rad-v3-bars';chart.setAttribute('role','list');chart.setAttribute('aria-label','Segnalazioni per mese dell’episodio');
 const recent=[...months].sort((a,b)=>a[0].localeCompare(b[0])).slice(-6),max=Math.max(...recent.map(row=>row[1]),1);
 for(const [month,count] of recent){const column=document.createElement('div');column.className='rad-v3-bar';column.setAttribute('role','listitem');column.setAttribute('aria-label',month+': '+count+' segnalazioni');
 const value=document.createElement('strong');value.textContent=String(count);const bar=document.createElement('span');bar.style.height=Math.max(4,count/max*85)+'px';const label=document.createElement('small');const date=new Date(month+'-01T12:00:00Z');label.textContent=new Intl.DateTimeFormat('it-IT',{month:'short',year:'2-digit'}).format(date);column.append(value,bar,label);chart.append(column);}timeline.append(chart);
 }else{const empty=document.createElement('p');empty.textContent='Nessuna data disponibile per i filtri selezionati.';timeline.append(empty);}host.append(timeline);
 const qualityCard=document.createElement('section');qualityCard.className='rad-v2-quality';
 const qualityTitle=document.createElement('h3');qualityTitle.textContent='Completezza delle date';qualityCard.append(qualityTitle);
 for(const [label,count]of [['Data episodio disponibile',dated],['Data episodio non disponibile',undated]]){
 const row=document.createElement('p'),caption=document.createElement('span'),value=document.createElement('strong');caption.textContent=label;value.textContent=String(count);row.append(caption,value);qualityCard.append(row);}
 const donut=document.createElement('div');donut.className='rad-v3-donut';donut.style.setProperty('--dated-share',items.length?String(dated/items.length*100)+'%':'0%');donut.setAttribute('role','img');donut.setAttribute('aria-label',dated+' con data, '+undated+' senza data');
 const total=document.createElement('strong');total.textContent=String(items.length);const caption=document.createElement('small');caption.textContent='totali';donut.append(total,caption);qualityCard.append(donut);
 host.append(qualityCard);
 const summary=$('rad-summary');summary.replaceChildren();const heading=document.createElement('strong');heading.textContent='In sintesi';summary.append(heading);
 const topType=[...types].sort((a,b)=>b[1]-a[1])[0],topPlace=[...places].sort((a,b)=>b[1]-a[1])[0];
 for(const text of [items.length+' segnalazioni nel campione filtrato',topPlace?topPlace[0]+' · Territorio con più segnalazioni':'Territorio non disponibile',topType?topType[0]+' · Tipologia più segnalata':'Nessuna tipologia']){const span=document.createElement('span');span.textContent=text;summary.append(span);}
 const quality=document.createElement('p');quality.className='ba-radar-note rad-v3-context';
 quality.textContent='La distribuzione dipende dalla partecipazione della community e dalla copertura dei dati. Le segnalazioni senza data valida sono escluse dal grafico mensile.';
 host.append(quality);
}
let distributionOverlay=null,distributionItems=[];
function showPublishedDistribution(items){
 distributionItems=items.filter(e=>validGeo(e.public_geo));
 if(!map)return;
 if(!distributionOverlay){
 class Distribution extends google.maps.OverlayView{
 onAdd(){this.container=document.createElement('div');this.getPanes().overlayLayer.append(this.container);}
 draw(){if(!this.container||!this.getProjection())return;this.container.replaceChildren();const groups=new Map();
 for(const event of distributionItems){const path=territoryPath(event);const key=chosenCenter?'area':(path[drill.level]||'territorio');if(!groups.has(key))groups.set(key,[]);groups.get(key).push(event);}
 for(const [key,events]of groups){const lat=events.reduce((n,e)=>n+e.public_geo.lat,0)/events.length,lng=events.reduce((n,e)=>n+e.public_geo.lng,0)/events.length;
 const point=this.getProjection().fromLatLngToDivPixel(new google.maps.LatLng(lat,lng));if(!point)continue;
 const badge=document.createElement('div');badge.className='rad-v2-cluster';badge.style.left=point.x+'px';badge.style.top=point.y+'px';badge.textContent=String(events.length);badge.title=key+' · '+events.length+' segnalazioni aggregate';this.container.append(badge);}}
 onRemove(){this.container?.remove();}
 }
 distributionOverlay=new Distribution();distributionOverlay.setMap(map);
 }
 distributionOverlay.draw();
}
function render(){
 const filtered=reports.filter(eligible);
 $('rad-radius').disabled=!chosenCenter;
 $('rad-scope').textContent=chosenCenter?'Area: '+($('rad-place').value.trim()||'Zona selezionata')+' · Raggio '+$('rad-radius').value+' km':'Area: '+([drill.region,drill.province,drill.comune].filter(Boolean).join(' / ')||'Italia')+' · Seleziona un luogo per applicare un raggio di ricerca.';
 $('rad-summary').replaceChildren();
 $('rad-title').textContent=chosenCenter?($('rad-place').value.trim()||'Zona selezionata'):'Panoramica territoriale';
 const host=$('rad-reports');host.replaceChildren();
 clearGeometry();
 if(loadError){$('rad-copy').textContent='Impossibile consultare le segnalazioni pubbliche.';return;}
 if(loadingReports){$('rad-copy').textContent='Caricamento dei dati pubblici…';return;}
 showPublishedDistribution(filtered.filter(matchesDrill));
 if(!chosenCenter){
  $('rad-title').textContent='Osservatorio BadAround';
  if(drill.level>0){
   const back=document.createElement('button');back.type='button';back.className='ba-radar-back-drill';
   back.textContent='← Torna indietro';back.addEventListener('click',backDrill);host.appendChild(back);
  }
  $('rad-copy').textContent='Una panoramica delle segnalazioni pubblicate, non delle denunce o degli episodi complessivamente avvenuti. Seleziona un fenomeno o un territorio per approfondire.';
  const insight=document.createElement('div');insight.className='ba-observatory-head';
  const heading=document.createElement('h3');heading.textContent='Il territorio raccontato dalla community';
  const sub=document.createElement('p');sub.textContent='Indicatori delle segnalazioni disponibili nel feed pubblico BadAround.';
  insight.append(heading,sub);host.appendChild(insight);
  showObservatory(filtered.filter(matchesDrill),host);
  return;
 }
 if(!filtered.length){
  $('rad-copy').textContent='Non sono disponibili segnalazioni geograficamente compatibili con i filtri. Questo non significa che la zona sia sicura.';
  return;
 }
 const back=document.createElement('button');back.type='button';back.className='ba-radar-back-drill';
 back.textContent='← Torna all’Osservatorio';back.addEventListener('click',()=>{
  chosenCenter=null;searchCircle?.setMap(null);searchCircle=null;centerMarker?.setMap(null);centerMarker=null;
  input.value='';if(map){map.setCenter({lat:42,lng:12.5});map.setZoom(7);}render();
 });host.appendChild(back);
 showObservatory(filtered,host);
 const groups=new Map();
 for(const e of filtered){
  const key=category(e)||'Altre problematiche';
  if(!groups.has(key))groups.set(key,new Set());
  groups.get(key).add(e.event_type?.name||'Tipologia non specificata');
 }
 $('rad-copy').textContent='Fenomeni segnalati nella zona selezionata. Nessuna inferenza sulla pericolosità o sul numero effettivo degli episodi.';
 const priority=['Veicoli','Case e attività','Pericoli','Spazi pubblici','Animali','Oggetti e documenti','Altre problematiche'];
 const summary=document.createElement('div');summary.className='ba-radar-metrics-preview';
 const metric=document.createElement('div'),value=document.createElement('strong'),label=document.createElement('small');
 value.textContent=String(groups.size);label.textContent='Ambiti tematici presenti';metric.append(value,label);summary.appendChild(metric);
 const other=document.createElement('div'),otherValue=document.createElement('strong'),otherLabel=document.createElement('small');
 otherValue.textContent='—';otherLabel.textContent='Ricorrenze: non verificabili';other.append(otherValue,otherLabel);summary.appendChild(other);
 host.appendChild(summary);
 for(const key of priority){
  if(!groups.has(key))continue;
  const card=document.createElement('section');card.className='ba-radar-phenomenon';
  const heading=document.createElement('h3');heading.textContent=key;
  const caption=document.createElement('p');caption.textContent='Tipologie segnalate (senza valutazione di frequenza):';
  const ul=document.createElement('ul');
  for(const name of [...groups.get(key)].sort((a,b)=>a.localeCompare(b,'it'))){
   const li=document.createElement('li');li.textContent=sentenceCase(name);ul.appendChild(li);
  }
  card.append(heading,caption,ul);host.appendChild(card);
 }
 const guidance={
  'Veicoli':'Precauzione generale: non lasciare oggetti di valore visibili o nell’abitacolo del veicolo.',
  'Case e attività':'Precauzione generale: verifica la chiusura di porte e accessi e segnala tempestivamente eventuali effrazioni alle autorità.',
  'Pericoli':'Precauzione generale: presta attenzione alla viabilità e segui le indicazioni delle autorità competenti.',
  'Spazi pubblici':'Precauzione generale: documenta eventuali criticità degli spazi pubblici e segnala ai servizi territoriali competenti.',
  'Animali':'Informazione generale: in caso di animale smarrito o in difficoltà, contatta i servizi locali competenti.'
 };
 const guidanceKeys=priority.filter(key=>groups.has(key)&&guidance[key]);
 if(guidanceKeys.length){
  const section=document.createElement('section');section.className='ba-radar-guidance';
  const heading=document.createElement('h3');heading.textContent='Indicazioni preventive generali';
  const intro=document.createElement('p');intro.textContent='Suggerimenti associati ai temi segnalati, non a un rischio misurato nella zona.';
  const list=document.createElement('ul');
  for(const key of guidanceKeys){
   const item=document.createElement('li');const strong=document.createElement('strong');
   strong.textContent=key+': ';item.append(strong,document.createTextNode(guidance[key]));list.appendChild(item);
  }
  section.append(heading,intro,list);host.appendChild(section);
 }
 const undated=filtered.some(event=>!episodeDate(event.occurred_date));
 const quality=document.createElement('section');quality.className='ba-radar-quality';
 const qualityTitle=document.createElement('h3');qualityTitle.textContent='Affidabilità della lettura';
 const qualityBody=document.createElement('p');
 qualityBody.textContent=undated?'Alcune segnalazioni dell’area non indicano la data dell’episodio. Non è possibile stabilire ricorrenze o tendenze temporali affidabili.':'La disponibilità delle date non certifica da sola la completezza dei dati, l’assenza di duplicati o le ricorrenze.';
 quality.append(qualityTitle,qualityBody);host.appendChild(quality);
 const notice=document.createElement('p');notice.className='ba-radar-note';
 notice.textContent='La presenza di segnalazioni non misura la pericolosità. Duplicati, date mancanti e copertura della community non sono ancora certificati.';
 host.appendChild(notice);
}
async function fetchReports(){
 loadingReports=true;render();
 try{
  const response=await fetch(endpoint+'?per_page=100&page=1',{credentials:'omit',headers:{Accept:'application/json'}});
  if(!response.ok)throw Error('Discovery HTTP '+response.status);
  const payload=await response.json();
  if(!Array.isArray(payload?.items))throw Error('Discovery response invalid');
  let allItems=payload.items;
  const pages=Math.max(1,Math.min(20,Number(payload.pages)||1));
  for(let page=2;page<=pages;page++){const next=await fetch(endpoint+'?per_page=100&page='+page,{credentials:'omit',headers:{Accept:'application/json'}});if(!next.ok)throw Error('Discovery pagination');const batch=await next.json();if(!Array.isArray(batch.items))throw Error('Discovery page invalid');allItems=allItems.concat(batch.items);}
  reports=[...new Map(allItems.map(e=>[e.id,e])).values()].filter(e=>e&&typeof e.id==='number'&&typeof e.permalink==='string'&&!/^\s*\[?QA\b/i.test(e.title||'')&&!/nessun evento reale|collaudo tecnico sintetico/i.test(e.title||''));
  loadError=false;
 }catch(err){loadError=true;reports=[];console.warn('Radar Discovery:',err);}
 loadingReports=false;render();
}
function updateCircle(){if(searchCircle)searchCircle.setRadius(radiusMeters());}
let placesApi=null,sessionToken=null,debounce=null,requestId=0,selectedPrediction=null;
const input=$('rad-place'),suggestions=$('rad-suggestions');
function clearSuggestions(){
 suggestions.replaceChildren();suggestions.hidden=true;input.setAttribute('aria-expanded','false');
}
async function readyMaps(){
 if(window.google?.maps?.Map)return;
 if(!config.mapsUrl)throw new Error('Google Maps API key not configured');
 await new Promise((resolve,reject)=>{
  const script=document.createElement('script');
  script.src=config.mapsUrl;script.async=true;script.defer=true;
  script.onerror=()=>reject(new Error('Google Maps script failed to load'));
  script.onload=resolve;document.head.appendChild(script);
 });
 for(let i=0;i<60;i++){
  if(window.google?.maps?.Map)return;
  await new Promise(resolve=>setTimeout(resolve,100));
 }
 throw new Error('Google Maps not ready after script load');
}
async function loadMap(){
 try{
  await readyMaps();
  map=new google.maps.Map($('rad-google-map'),{
   center:{lat:41.8,lng:12.6},zoom:6,restriction:{latLngBounds:ITALY_BOUNDS,strictBounds:false},minZoom:5,mapTypeControl:false,streetViewControl:false,
   styles:[{featureType:'poi',elementType:'all',stylers:[{visibility:'off'}]},{featureType:'transit',elementType:'all',stylers:[{visibility:'off'}]},{featureType:'road',elementType:'labels.icon',stylers:[{visibility:'off'}]}],
   ...(window.BadAroundMapNavigation?.options?.()||{
     zoomControl:true,zoomControlOptions:{position:google.maps.ControlPosition.RIGHT_TOP},
     fullscreenControl:true,fullscreenControlOptions:{position:google.maps.ControlPosition.RIGHT_TOP},
     gestureHandling:'cooperative'
   })
  });
  // Radar uses native zoom/fullscreen only; dragging and keyboard panning remain available.
  map.setOptions({cameraControl:false,mapTypeControl:false,streetViewControl:false,rotateControl:false,zoomControl:true,fullscreenControl:true});
  frameItaly();
  geocoder=new google.maps.Geocoder();
  try{placesApi=await google.maps.importLibrary('places');}
  catch(error){console.warn('Radar autocomplete unavailable; manual address search remains enabled.',error);}
  status('');render();
 }catch(error){status('Google Maps non disponibile: verifica configurazione, autorizzazione del dominio o console del browser.');console.error('Radar map:',error);}
}
function focusLocation(position,address,save=true){
 if(!map)return;
 const pos=position;
 if(!searchCircle)searchCircle=new google.maps.Circle({map,center:pos,radius:radiusMeters(),strokeColor:'#078e99',strokeWeight:2,strokeOpacity:.9,fillColor:'#0798a8',fillOpacity:.05});
 else searchCircle.setCenter(pos);
 if(!centerMarker)centerMarker=new google.maps.Marker({map,position:pos,title:'Centro della ricerca Radar (nessun evento)',icon:{url:'data:image/svg+xml;charset=UTF-8,'+encodeURIComponent(<?php echo wp_json_encode(badaround_pin_svg('neutral')); ?>),scaledSize:new google.maps.Size(40,50),anchor:new google.maps.Point(20,48.333)}});
 else centerMarker.setPosition(pos);
 searchCircle.setRadius(radiusMeters());searchCircle.setVisible(true);
 chosenCenter={lat:pos.lat instanceof Function?pos.lat():pos.lat,lng:pos.lng instanceof Function?pos.lng():pos.lng};
 map.fitBounds(searchCircle.getBounds(),60);
 input.value=address;

 status('');
 render();
}
async function choosePrediction(prediction){
 clearSuggestions();selectedPrediction=null;
 try{
  const place=prediction.toPlace();
  await place.fetchFields({fields:['formattedAddress','location','viewport']});
  if(!place.location)throw new Error('Coordinates missing');
  focusLocation(place.location,place.formattedAddress||input.value);
  sessionToken=null;
 }catch(error){console.warn('Radar place selection failed:',error);status('Impossibile selezionare il luogo. Prova con il pulsante Cerca.');}
}
async function autocomplete(query){
 if(!placesApi?.AutocompleteSuggestion || query.length<2){clearSuggestions();return;}
 const id=++requestId;
 try{
  if(!sessionToken)sessionToken=new placesApi.AutocompleteSessionToken();
  const {suggestions:results}=await placesApi.AutocompleteSuggestion.fetchAutocompleteSuggestions({input:query,sessionToken,includedRegionCodes:['it']});
  if(id!==requestId)return;
  clearSuggestions();
  for(const item of (results||[]).slice(0,6)){
   if(!item.placePrediction)continue;
   const prediction=item.placePrediction;
   const b=document.createElement('button');b.type='button';b.setAttribute('role','option');
   b.className='ba-map-place-suggestion';
   const strong=document.createElement('strong');strong.textContent=prediction.mainText?.text||prediction.text?.text||query;
   const small=document.createElement('small');small.textContent=prediction.secondaryText?.text||'';
   b.append(strong,small);
   b.addEventListener('mousedown',event=>event.preventDefault());
   b.addEventListener('click',()=>choosePrediction(prediction));
   suggestions.appendChild(b);
  }
  suggestions.hidden=!suggestions.children.length;
  input.setAttribute('aria-expanded',String(!suggestions.hidden));
 }catch(error){if(id===requestId)clearSuggestions();console.warn('Radar autocomplete:',error);}
}
function locate(){
 requestId++;clearSuggestions();
 if(!geocoder||!map){status('La cartografia non è disponibile.');return;}
 const address=input.value.trim();if(!address){status('Inserisci un indirizzo o località.');return;}
 status('Ricerca indirizzo in corso…');
 geocoder.geocode({address,region:'IT'},(results,code)=>{
  if(code!=='OK'||!results?.length){status('Località non trovata. Verifica l’indirizzo.');return;}
  focusLocation(results[0].geometry.location,results[0].formatted_address);
 });
}
input.addEventListener('input',()=>{
 selectedPrediction=null;requestId++;clearTimeout(debounce);
 const query=input.value.trim();render();
 if(query.length<2){clearSuggestions();return;}
 debounce=setTimeout(()=>autocomplete(query),180);
});
input.addEventListener('keydown',e=>{
 if(e.key==='Escape'){requestId++;clearSuggestions();}
 if(e.key==='Enter'){
  e.preventDefault();
  const first=suggestions.querySelector('button');
  if(first&&!suggestions.hidden)first.click();else locate();
 }
});
document.addEventListener('click',e=>{if(!e.target.closest('.ba-radar-search')){requestId++;clearSuggestions();}});

$('rad-nearby').addEventListener('click',()=>{
 if(!navigator.geolocation){status('Il browser non supporta la geolocalizzazione.');return;}
 if(!map){status('La mappa non è ancora disponibile.');return;}
 const button=$('rad-nearby');button.disabled=true;
 status('Richiesta autorizzazione alla posizione in corso…');
 navigator.geolocation.getCurrentPosition(
  position=>{
   button.disabled=false;
   const coords={lat:position.coords.latitude,lng:position.coords.longitude};
   focusLocation(coords,'Vicino alla mia posizione');
   status('Zona centrata sulla tua posizione. BadAround Radar non la salva.');
  },
  error=>{
   button.disabled=false;
   const message=error.code===1?'Permesso di localizzazione non concesso.':error.code===2?'Posizione temporaneamente non disponibile.':'Posizione non rilevata entro il tempo previsto.';
   status(message);
  },
  {enableHighAccuracy:false,timeout:12000,maximumAge:60000}
 );
});

['rad-cat','rad-period','rad-radius'].forEach(id=>$(id).addEventListener('change',render));
$('rad-radius').addEventListener('change',()=>{updateCircle();if(searchCircle&&map)map.fitBounds(searchCircle.getBounds(),60)});


$('rad-reset').addEventListener('click',()=>{
 for(const [key,value] of Object.entries(initial))$('rad-'+key).value=value;
 chosenCenter=null;drill={level:0,region:null,province:null,comune:null};
 try{window.localStorage.removeItem(LAST_AREA_KEY);}catch(e){}
 searchCircle?.setMap(null);searchCircle=null;centerMarker?.setMap(null);centerMarker=null;
 if(map){map.setCenter({lat:41.9028,lng:12.4964});map.setZoom(7);}
 status('');render();
});
$('rad-drawer').addEventListener('click',()=>{const open=$('rad-panel').classList.toggle('is-open');$('rad-drawer').textContent=open?'Chiudi analisi':'Mostra analisi zona';$('rad-drawer').setAttribute('aria-expanded',String(open))});
try{window.localStorage.removeItem(LAST_AREA_KEY);}catch(e){}
render();loadMap();fetchReports();
})();
</script>
<?php get_footer(); ?>
