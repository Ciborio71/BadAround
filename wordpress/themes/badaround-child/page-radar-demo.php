<?php
/** Template Name: BadAround — Radar Demo */
if (!defined('ABSPATH')) exit;
add_filter('wp_robots',static function($r){$r['noindex']=true;$r['nofollow']=true;return $r;});
$base=get_stylesheet_directory_uri();
wp_enqueue_style('ba-radar-observatory',$base.'/assets/css/radar-observatory.css',array(),(string)filemtime(__DIR__.'/assets/css/radar-observatory.css'));
wp_enqueue_script('ba-radar-observatory',$base.'/assets/js/radar-observatory.js',array(),(string)filemtime(__DIR__.'/assets/js/radar-observatory.js'),true);
$settings=get_option('wpforms_settings',array());$key=is_array($settings)?trim((string)($settings['geolocation-google-places-api-key']??'')):'';
$maps=$key?add_query_arg(array('key'=>$key,'v'=>'weekly','loading'=>'async'),'https://maps.googleapis.com/maps/api/js'):'';
wp_localize_script('ba-radar-observatory','BadAroundRadar',array('nonce'=>is_user_logged_in()?wp_create_nonce('wp_rest'):'','endpoint'=>rest_url('badaround/v1/radar'),'search'=>rest_url('badaround/v1/radar-territories'),'boundaries'=>rest_url('badaround/v1/radar-boundaries'),'provinces'=>rest_url('badaround/v1/radar-boundaries?level=provinces'),'mapsUrl'=>$maps,'mapUrl'=>home_url('/mappa/'),'reportUrl'=>home_url('/segnala-un-evento/')));
get_header();
$categories=array('veicoli'=>'Veicoli','case-e-attivita'=>'Case e attività','pericoli'=>'Pericoli','spazi-pubblici'=>'Spazi pubblici','animali'=>'Animali','oggetti-e-documenti'=>'Oggetti');
?>
<main class="ba-radar-app" id="main-content">
 <header class="ra-heading"><div><h1>Radar BadAround</h1><span>Il territorio raccontato dalla community.</span></div><span class="ra-live">Anteprima · Dati reali</span></header>
 <details class="ra-method"><summary>ⓘ Come leggere questi dati <span>I colori indicano concentrazione delle segnalazioni, non pericolosità.</span></summary><div><p>Un’area senza segnalazioni non equivale a un’area sicura: potrebbe essere poco rappresentata nella community.</p><p>Un aumento delle segnalazioni non dimostra da solo un aumento degli episodi: potrebbe essere cresciuta la partecipazione.</p><p>Ogni segnalazione pesa 1. Le celle con meno di 3 segnalazioni non vengono rappresentate. Le aree approssimate mantengono la precisione pubblica autorizzata. I conteggi descrivono segnalazioni, non necessariamente episodi distinti.</p></div></details>
 <nav class="ra-breadcrumb" id="ra-breadcrumb" aria-label="Percorso territoriale"><button type="button" data-territory="0">Italia</button></nav>
 <section class="ra-filters" aria-label="Filtri dell’osservatorio">
  <label>Categoria<select id="ra-category"><option value="">Tutte le categorie</option><?php foreach($categories as $slug=>$label): ?><option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
  <label>Periodo<select id="ra-period"><option value="all">Tutti i periodi</option><option value="7">Ultimi 7 giorni</option><option value="30">Ultimi 30 giorni</option><option value="90">Ultimi 90 giorni</option><option value="365">Ultimi 12 mesi</option></select></label>
  <div class="ra-search"><label for="ra-place">Cerca un territorio</label><input id="ra-place" type="search" placeholder="Regione, città o quartiere…" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="ra-suggestions" autocomplete="off"><div id="ra-suggestions" class="ra-suggestions" role="listbox" hidden></div></div>
  <button type="button" class="ra-outline" id="ra-reset">↺ Reimposta</button>
 </section>
 <div class="ra-scope-row"><strong id="ra-scope">Area: Italia</strong><span class="ra-boundary-label">▱ Confini amministrativi</span><details class="ra-options"><summary>Altre opzioni</summary><div><button type="button" class="ra-outline" id="ra-nearby">⌖ Vicino a me</button><label>Data di riferimento<select id="ra-basis"><option value="date">Data dell’episodio</option><option value="published">Data di pubblicazione</option></select></label><label id="ra-radius-wrap" hidden>Raggio<select id="ra-radius"><option value="1000">1 km</option><option value="500">500 m</option><option value="2000">2 km</option><option value="5000">5 km</option></select></label></div></details></div>
 <div class="ra-category-row"><div class="ra-categories" role="group" aria-label="Categoria della segnalazione"><button type="button" data-category="" aria-pressed="true">Tutte</button><?php foreach($categories as $slug=>$label): ?><button type="button" data-category="<?php echo esc_attr($slug); ?>" aria-pressed="false"><?php echo badaround_pin_svg($slug); ?><span><?php echo esc_html($label); ?></span></button><?php endforeach; ?></div></div>
 <div class="ra-workspace">
  <aside class="ra-analysis" aria-label="Analisi delle segnalazioni">
   <div class="ra-kpis" id="ra-kpis"></div>
   <div class="ra-rankings"><section class="ra-card"><h2 id="ra-area-title">Regioni per segnalazioni</h2><div id="ra-areas"></div></section><section class="ra-card"><h2 id="ra-types-title">Tipologie di eventi</h2><div id="ra-types"></div></section></div>
   <section class="ra-card ra-timeline"><div class="ra-chart-head"><h2 id="ra-chart-title">Episodi con data disponibile</h2><span id="ra-date-quality"></span></div><p id="ra-chart-range"></p><div id="ra-chart" role="list" aria-label="Segnalazioni per intervallo temporale"></div></section>
   
  </aside>
  <section class="ra-map-card" aria-label="Distribuzione territoriale delle segnalazioni">
   <div id="ra-map" role="region" aria-label="Cartografia interattiva dell’osservatorio"></div>
   <div class="ra-map-actions"><button id="ra-frame" type="button">⌖ Inquadra area</button><button id="ra-analyze" type="button" hidden>Analizza quest’area →</button></div>
   <div id="ra-map-status" class="ra-map-status" role="status">Caricamento cartografia…</div>
   <div id="ra-map-tooltip" class="ra-map-tooltip" role="status" hidden></div>
   <div class="ra-legend"><strong>Segnalazioni nell’area</strong><span><i style="background:#aee5e3"></i>3–5</span><span><i style="background:#44b9b3"></i>6–10</span><span><i style="background:#007d86"></i>11+</span><span><i style="background:#dce4e8"></i>Dati insufficienti</span></div>
   <small class="ra-attribution">Confini ISTAT 2026 · geojson-italy · CC BY 4.0</small>
  </section>
 </div>
 <p class="ra-quality" id="ra-quality"></p>
 <span id="ra-status" class="ra-status" role="status" aria-live="polite">Caricamento dati…</span>
 <div class="ra-summary"><strong id="ra-summary">Il territorio raccontato dalla community</strong><a id="ra-open-map" href="<?php echo esc_url(home_url('/mappa/')); ?>">Apri gli eventi sulla Mappa →</a></div>
</main>
<?php get_footer(); ?>

