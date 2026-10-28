<?php
/**
 * Template Name: BadAround — Radar Demo
 * RADAR-ONLINE-UX-01: DEMO ONLY — no real event or location data.
 */
if (!defined('ABSPATH')) exit;
// This validation-only synthetic experience must not be indexed.
add_filter('wp_robots', static function ($robots) { $robots['noindex'] = true; $robots['nofollow'] = true; return $robots; });
get_header();
?>
<main class="ba-radar-demo" id="main-content">
 
 <div class="ba-radar-controls" aria-label="Filtri dimostrativi Radar">
  <div class="ba-radar-search"><label for="rad-place">Cerca un luogo reale</label><div class="ba-radar-search-row"><input id="rad-place" type="search" value="" placeholder="Cerca evento o luogo" aria-label="Cerca evento o luogo" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="rad-suggestions" /><button id="rad-nearby" type="button" aria-label="Esplora la zona attorno alla mia posizione">⌖ Vicino a me</button></div><div id="rad-suggestions" class="ba-radar-suggestions" role="listbox" aria-label="Suggerimenti indirizzi" hidden></div></div>
  <label>Categoria<select id="rad-cat"><option>Tutte le categorie</option><option>Veicoli</option><option>Pericoli</option><option>Spazi pubblici</option><option>Animali</option><option>Case e attività</option><option>Oggetti e documenti</option></select></label>
  <label>Periodo<select id="rad-period"><option value="all" selected>Tutti i periodi</option><option value="30">30 giorni</option><option value="7">7 giorni</option><option value="90">90 giorni</option><option value="365">12 mesi</option></select></label>
  <label>Raggio<select id="rad-radius"><option value="1">1 km</option><option value="0.5">500 m</option><option value="2">2 km</option><option value="5">5 km</option></select></label>
  <button id="rad-reset" class="ba-radar-reset-visible" type="button" title="Cancella la zona memorizzata e tutti i filtri">↺ Reimposta</button>
 </div>
 <div class="ba-radar-workspace">
  <section class="ba-radar-map" aria-label="Cartografia Google Maps e segnalazioni pubbliche">
   <div id="rad-google-map" class="ba-radar-google-map" role="application" aria-label="Google Maps — geometrie delle segnalazioni pubblicate"></div>
   
   <div id="rad-map-message" class="ba-radar-map-message" role="status" aria-live="polite">Caricamento cartografia Google Maps…</div>
   <div class="ba-radar-map-disclaimer">PROBLEMATICHE SEGNALATE · NESSUN INDICE DI RISCHIO</div>
   <div class="ba-radar-visual-key" aria-label="Legenda informazioni cartografiche"><strong>Radar territoriale</strong><span>Esplora le problematiche documentate nella community</span><small>La mappa non mostra ancora concentrazioni certificate. Scegli una località per esplorare i fenomeni pubblicati.</small></div><div class="ba-radar-map-title" id="rad-map-title">Area dimostrativa · 1 km</div>

  </section>
  <aside class="ba-radar-panel" id="rad-panel">
   <span class="ba-radar-tag">Radar · Problematiche territoriali</span>
   <h2 id="rad-title">Panoramica territoriale</h2>
   <p id="rad-copy" role="status" aria-live="polite">Caricamento delle segnalazioni pubbliche…</p>
   <p class="ba-radar-note">I risultati non sono un indice di sicurezza. Le segnalazioni prive della data dell'episodio compaiono solo selezionando «Tutti i periodi». Le aree approssimate non identificano il luogo esatto dell'evento.</p>
   <div id="rad-reports" class="ba-radar-reports" aria-label="Problematiche segnalate nella zona"></div>
   <a class="ba-radar-back" href="<?php echo esc_url(home_url('/mappa/')); ?>">Apri la mappa delle segnalazioni reali →</a>
  </aside>
 </div>
 <button id="rad-drawer" type="button" aria-controls="rad-panel" aria-expanded="false">Mostra analisi zona</button>
 <p class="ba-radar-foot">RADAR-PHENOMENA-01 · Problematiche da dati pubblici · Nessuna heatmap o valutazione di rischio</p>
</main>
<style>
.ba-radar-demo{--navy:#071a38;--teal:#0798a8;--muted:#61778b;color:var(--navy);background:#f3f7fa;min-height:80vh}.ba-radar-demo *{box-sizing:border-box}
.ba-radar-heading{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:22px clamp(15px,3vw,44px);background:#fff}.ba-radar-heading h1{font-size:clamp(26px,3vw,38px);margin:0 0 3px}.ba-radar-heading p{margin:0;color:var(--muted)}.ba-radar-kicker{font-weight:850;font-size:11px;letter-spacing:.08em;color:var(--teal)!important}.ba-radar-demo-pill{font-size:10px;color:#865014;background:#fff2d8;border:1px solid #eed8b2;padding:10px 13px;border-radius:20px}
.ba-radar-controls{display:flex;gap:9px;flex-wrap:wrap;align-items:end;background:#fff;padding:10px clamp(15px,3vw,44px);border-block:1px solid #e0e8ed}.ba-radar-controls label{display:grid;gap:4px;font-size:11px;font-weight:800;color:#50677c}.ba-radar-controls input,.ba-radar-controls select,.ba-radar-controls button{height:40px;min-width:110px;border:1px solid #cfdee5;border-radius:9px;background:#fff;color:var(--navy);padding:0 11px;font:inherit;font-size:13px}.ba-radar-controls .ba-radar-search{flex:1;min-width:210px}.ba-radar-search input{width:100%}.ba-radar-controls button{font-weight:800;cursor:pointer}
.ba-radar-workspace{display:grid;grid-template-columns:minmax(0,1fr) 340px;gap:12px;max-width:1800px;margin:auto;padding:12px;min-height:590px}.ba-radar-map{position:relative;min-height:575px;border:1px solid #d5e2e9;border-radius:15px;overflow:hidden;background:#e7efe9}.ba-radar-map svg{position:absolute;inset:0;width:100%;height:100%}.ba-radar-map-title,.ba-radar-legend{position:absolute;background:#fff;border-radius:11px;padding:11px 13px;box-shadow:0 6px 18px #071a3820;font-size:12px}.ba-radar-map-title{top:15px;left:15px;font-weight:850}.ba-radar-legend{bottom:14px;right:14px}.ba-radar-gradient{width:170px;height:9px;border-radius:10px;margin:8px 0;background:linear-gradient(90deg,#d3e8dc,#f9d66e,#f48b56,#ca4050)}.ba-radar-legend small{color:var(--muted)}
.ba-radar-panel{border:1px solid #dfebef;border-radius:15px;padding:21px;background:#fff}.ba-radar-panel h2{font-size:22px;margin:10px 0}.ba-radar-panel>p{color:var(--muted);font-size:13px;line-height:1.6}.ba-radar-tag{font-size:11px;font-weight:850;background:#e6f5f5;color:#077480;border-radius:24px;padding:7px 11px}.ba-radar-metrics{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin:18px 0}.ba-radar-metrics>div{padding:15px;background:#f3f8fa;border-radius:10px}.ba-radar-metrics strong{font-size:28px;display:block}.ba-radar-metrics small{font-size:11px;color:var(--muted)}.ba-radar-advice{border-left:4px solid #d4a042;background:#fff8e8;padding:12px;border-radius:8px;font-size:12px}.ba-radar-advice p{margin:7px 0 0;line-height:1.55}.ba-radar-note{background:#f4f8fa;padding:12px;border-radius:10px}.ba-radar-states{display:flex;flex-wrap:wrap;gap:6px}.ba-radar-states button{border:1px solid #d5e0e6;background:#fff;border-radius:8px;padding:8px;font-size:11px;cursor:pointer}.ba-radar-states button.is-active{background:#071a38;color:#fff}.ba-radar-back{display:block;background:#078895;color:#fff;text-decoration:none;text-align:center;padding:12px;margin-top:19px;border-radius:10px;font-size:13px;font-weight:800}.ba-radar-foot{text-align:center;color:#708698;font-size:11px;padding:10px}.ba-radar-demo button:focus-visible,.ba-radar-demo input:focus-visible,.ba-radar-demo select:focus-visible{outline:3px solid #0798a8;outline-offset:2px}#rad-drawer{display:none}
@media(max-width:850px){.ba-radar-workspace{grid-template-columns:minmax(0,1fr) 300px}.ba-radar-heading{flex-wrap:wrap}}
@media(max-width:700px){.ba-radar-controls .ba-radar-search{flex-basis:100%}.ba-radar-workspace{display:block;padding:8px}.ba-radar-map{min-height:calc(100dvh - 320px)}.ba-radar-panel{position:fixed;z-index:999;left:0;right:0;bottom:0;max-height:78dvh;overflow:auto;border-radius:20px 20px 0 0;transform:translateY(110%);transition:transform .25s;box-shadow:0 -10px 40px #071a3833}.ba-radar-panel.is-open{transform:translateY(0)}#rad-drawer{display:block;position:fixed;z-index:1000;bottom:15px;left:50%;transform:translateX(-50%);border:0;border-radius:50px;background:#071a38;color:#fff;padding:13px 20px;font-weight:850;white-space:nowrap}.ba-radar-foot{padding-bottom:70px}.ba-radar-demo-pill{font-size:9px}}
@media(prefers-reduced-motion:reduce){.ba-radar-panel{transition:none}}
.ba-radar-layer-toggle{position:absolute;left:15px;bottom:15px;border:1px solid #c6dce3;background:#fff;color:#071a38;border-radius:9px;padding:10px 12px;box-shadow:0 4px 12px #071a3820;cursor:pointer;font-size:12px;font-weight:800;z-index:2}.ba-radar-metrics[hidden]{display:none!important}.ba-radar-layer-toggle:focus-visible{outline:3px solid #0798a8;outline-offset:2px}
@media(max-width:700px){.ba-radar-layer-toggle{bottom:72px}}

/* RADAR-UX-03: align desktop analysis sidebar to Mappe's left-hand rail. */
@media(min-width:701px){
 .ba-radar-workspace{grid-template-columns:340px minmax(0,1fr)}
 .ba-radar-panel{grid-column:1;grid-row:1;min-width:0}
 .ba-radar-map{grid-column:2;grid-row:1;min-width:0}
}
@media(min-width:701px) and (max-width:850px){
 .ba-radar-workspace{grid-template-columns:300px minmax(0,1fr)}
}

/* RADAR-UX-04 — full-screen dashboard shell parity with Mappe; wp_footer remains active. */
body.page-template-page-radar-demo .ba-site-footer{display:none}

/* RADAR-UX-05 — dashboard controls, synthetic-only interactive layers. */
.ba-radar-layers-toggle{position:absolute;right:16px;top:16px;z-index:3;border:0;border-radius:10px;background:#fff;color:#071a38;box-shadow:0 5px 18px #071a3825;padding:11px 15px;font-weight:850;cursor:pointer}
.ba-radar-layers{position:absolute;right:16px;top:64px;z-index:4;width:min(265px,calc(100% - 32px));padding:16px;border:1px solid #d6e3e9;border-radius:12px;background:#fff;box-shadow:0 10px 35px #071a3830;display:none}
.ba-radar-layers.is-open{display:grid;gap:12px}
.ba-radar-layers__head{display:flex;justify-content:space-between;align-items:center}
.ba-radar-layers__head button{background:transparent;border:0;font-size:23px;cursor:pointer;color:#071a38}
.ba-radar-layers p,.ba-radar-layers small{margin:0;font-size:11px;color:#60768a}
.ba-radar-layers label{display:flex;align-items:center;gap:10px;font-weight:700;font-size:12px;color:#071a38}
.ba-radar-layers input{width:16px;height:16px;accent-color:#0798a8}
.ba-radar-layers-toggle:focus-visible,.ba-radar-layers button:focus-visible{outline:3px solid #0798a8;outline-offset:2px}
.ba-radar-demo .ba-radar-panel .ba-radar-brief{margin:12px 0;padding:12px;border:1px solid #cde5e9;border-radius:10px;background:#f0fbfc}
.ba-radar-brief strong{font-size:12px}.ba-radar-brief p{margin:5px 0 0;font-size:12px;line-height:1.5;color:#486578}
@media(max-width:700px){.ba-radar-layers-toggle{right:10px;top:10px}.ba-radar-layers{right:10px;top:60px}}

/* RADAR-GMAP-01 — genuine Google basemap; demo analytics never sourced from map content. */
.ba-radar-google-map{position:absolute;inset:0;width:100%;height:100%;background:#e8f1f4}
.ba-radar-map-message{position:absolute;top:64px;left:15px;max-width:calc(100% - 30px);z-index:3;background:#fff;color:#4c657b;border-radius:9px;padding:9px 12px;font-size:12px;box-shadow:0 3px 14px #071a3820}
.ba-radar-map-message[hidden],.ba-radar-demo-heat[hidden]{display:none!important}
.ba-radar-demo-heat{position:absolute;z-index:1;pointer-events:none;inset:17% 22% 18% 16%;border-radius:50%;background:radial-gradient(ellipse at 48% 45%,rgba(203,56,74,.45) 0%,rgba(245,135,72,.31) 29%,rgba(249,212,101,.19) 57%,transparent 75%);filter:blur(22px)}
.ba-radar-map-disclaimer{position:absolute;top:60px;right:15px;z-index:2;max-width:210px;padding:8px 10px;background:#fff9e6;border:1px solid #e8d9b3;border-radius:8px;color:#7a561b;font-size:10px;font-weight:900;line-height:1.35}
.ba-radar-search{display:flex!important;flex-wrap:wrap}
.ba-radar-search input{flex:1 1 230px;min-width:150px}
.ba-radar-search button{flex:0 0 auto;min-width:70px;background:#078e99;color:#fff;border:0}
.ba-radar-search button:hover{background:#067780;color:#fff}
@media(max-width:700px){.ba-radar-map-disclaimer{top:58px;right:10px;max-width:175px;font-size:9px}.ba-radar-map-message{top:55px}}

/* RADAR-GMAP-02: compact Mappe-like toolbar and accessible Places suggestions. */
.ba-radar-heading{display:none!important}
.ba-radar-controls{position:relative;z-index:12}
.ba-radar-search{position:relative;flex:1 1 280px;min-width:220px;display:grid!important;gap:4px}
.ba-radar-search-row{display:flex;gap:5px}
.ba-radar-search-row input{flex:1 1 auto;min-width:0;width:100%}
.ba-radar-search-row button{min-width:65px;color:#fff;background:#078e99;border-color:#078e99;cursor:pointer}
.ba-radar-suggestions{position:absolute;left:0;right:0;top:100%;z-index:60;max-height:320px;overflow:auto;background:#fff;border:1px solid #d9e4eb;border-radius:12px;box-shadow:0 15px 30px #071a3830}
.ba-radar-suggestions[hidden]{display:none!important}
.ba-radar-suggestions button{display:block;width:100%;height:auto;min-height:42px;padding:11px 13px;border:0;border-bottom:1px solid #edf1f5;border-radius:0;text-align:left;background:#fff;color:#071a38;font-size:12px;cursor:pointer}
.ba-radar-suggestions button:hover,.ba-radar-suggestions button:focus-visible{background:#eaf7fa;color:#071a38}

/* RADAR-SEARCH-WIDTH-01 — match the compact Mappe search footprint. */
@media(min-width:701px){.ba-radar-controls .ba-radar-search{flex:0 1 355px;width:355px;max-width:355px;min-width:190px}}
@media(max-width:700px){.ba-radar-controls .ba-radar-search{flex:1 1 100%;width:100%;max-width:100%;min-width:0}}

/* RADAR-GEO-01 — compact location control, explicitly permission-gated. */
.ba-radar-search-row #rad-nearby{min-width:111px;white-space:nowrap;background:#eaf7f8!important;border:1px solid #a9d7dc!important;color:#076a76!important;font-size:12px;font-weight:800}
.ba-radar-search-row #rad-nearby:hover,.ba-radar-search-row #rad-nearby:focus-visible{background:#d4eff1!important;color:#064f59!important}
.ba-radar-search-row #rad-nearby:disabled{opacity:.6;cursor:wait}
@media(max-width:930px){.ba-radar-search-row{flex-wrap:wrap}.ba-radar-search-row input{flex-basis:100%}}
/* RADAR-MAP-PARITY-01: match Mappe edges; keep compact mobile gutters. */
body.page-template-page-radar-demo .ba-radar-workspace{max-width:none;margin:0;padding:0;gap:0}
@media(min-width:701px){body.page-template-page-radar-demo .ba-radar-workspace{grid-template-columns:340px minmax(0,1fr)}body.page-template-page-radar-demo .ba-radar-map{border-radius:0;border-left:0;border-right:0}}
@media(min-width:701px) and (max-width:850px){body.page-template-page-radar-demo .ba-radar-workspace{grid-template-columns:300px minmax(0,1fr)}}
@media(max-width:700px){body.page-template-page-radar-demo .ba-radar-workspace{padding:0}}
.ba-radar-layers,.ba-radar-layers-toggle{display:none!important}

/* RADAR-SEARCH-PARITY-02: Mappe-sized text field, Mappe left inset and one-row desktop controls. */
@media(min-width:931px){
 body.page-template-page-radar-demo .ba-radar-controls{padding:8px 14px;gap:10px}
 body.page-template-page-radar-demo .ba-radar-controls .ba-radar-search{flex:0 0 555px;width:555px;max-width:555px;min-width:0;gap:0}
 body.page-template-page-radar-demo .ba-radar-search-row{flex-wrap:nowrap;align-items:center;gap:7px}
 body.page-template-page-radar-demo .ba-radar-search-row input{flex:0 1 355px;width:355px;min-width:180px;flex-basis:355px;height:40px}
 body.page-template-page-radar-demo .ba-radar-search-row #rad-search{flex:0 0 65px}
 body.page-template-page-radar-demo .ba-radar-search-row #rad-nearby{flex:0 0 120px}
}
body.page-template-page-radar-demo .ba-radar-search>label{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
@media(max-width:930px){body.page-template-page-radar-demo .ba-radar-controls{padding:8px 9px}}

/* RADAR-DISCOVERY-01: genuine public report browsing (not analytics). */
.ba-radar-reports{display:grid;gap:9px;max-height:46vh;overflow:auto;margin-top:14px}
.ba-radar-report{border:1px solid #dce7ed;border-radius:11px;padding:11px 12px;background:#f8fbfd;display:grid;gap:5px}
.ba-radar-report a{font-weight:800;color:#073d64;text-decoration:none;font-size:13px;line-height:1.35}
.ba-radar-report a:hover{text-decoration:underline}
.ba-radar-report small{color:#637a8a;font-size:11px;line-height:1.45}
.ba-radar-report--selected{outline:2px solid #078e99}

/* RADAR-PHENOMENA-01 — thematic sidebar, not a duplicate of Mappe event list. */
.ba-radar-phenomenon{border:1px solid #d8e6eb;border-radius:12px;padding:14px;background:#fff}
.ba-radar-phenomenon h3{font-size:15px;line-height:1.3;margin:0 0 5px;color:#071a38}
.ba-radar-phenomenon p{font-size:11px;line-height:1.45;color:#657b8d;margin:0 0 8px}
.ba-radar-phenomenon ul{margin:0;padding-left:18px;color:#28455c;font-size:12px;line-height:1.55}
.ba-radar-phenomenon li+li{margin-top:4px}

/* RADAR-CONTEXT-01: contextual general guidance, transparently not risk predictions. */
.ba-radar-guidance,.ba-radar-quality{padding:13px;border-radius:11px;border:1px solid #d5e6ea;background:#f0f9fa}
.ba-radar-guidance h3,.ba-radar-quality h3{margin:0 0 8px;font-size:13px;color:#08394b}
.ba-radar-guidance p,.ba-radar-quality p{margin:0;color:#4e6a7a;font-size:12px;line-height:1.5}
.ba-radar-guidance ul{margin:9px 0 0;padding-left:17px;font-size:12px;color:#24465c;line-height:1.6}
.ba-radar-guidance li+li{margin-top:8px}

/* RADAR-LAYOUT-PARITY-01 — use the exact Mappe viewport/rail proportions.
   Header inherited unchanged; Radar interactions remain independent. */
@media(min-width:861px){
 body.page-template-page-radar-demo{overflow:hidden}
 body.page-template-page-radar-demo .ba-radar-demo{
  display:grid;grid-template-rows:56px minmax(0,1fr);
  height:calc(100dvh - 76px - var(--wp-admin--admin-bar--height, 0px));
  min-height:0;max-height:none;overflow:hidden;
 }
 body.page-template-page-radar-demo .ba-radar-controls{
  height:56px;min-height:56px;max-height:56px;
  padding:8px 14px;align-items:center;flex-wrap:nowrap;
  overflow:visible;gap:10px;
 }
 body.page-template-page-radar-demo .ba-radar-controls>label{gap:0}
 body.page-template-page-radar-demo .ba-radar-controls>label:not(.ba-radar-search)>select{height:40px}
 body.page-template-page-radar-demo .ba-radar-controls>label:not(.ba-radar-search){
  display:flex;align-items:center;gap:5px;white-space:nowrap;
 }
 body.page-template-page-radar-demo .ba-radar-workspace{
  display:grid;grid-template-columns:minmax(360px,420px) minmax(0,1fr);
  grid-template-rows:minmax(0,1fr);height:100%;max-height:100%;
  min-height:0;overflow:hidden;margin:0;padding:0;gap:0;
 }
 body.page-template-page-radar-demo .ba-radar-panel{
  grid-column:1;grid-row:1;height:100%;min-height:0;
  max-height:100%;overflow-y:auto;overflow-x:hidden;
  border:0;border-right:1px solid #dde7ed;border-radius:0;
  background:#f7fafc;overscroll-behavior:contain;
 }
 body.page-template-page-radar-demo .ba-radar-map{
  grid-column:2;grid-row:1;height:100%;min-height:0;
  max-height:100%;border:0;border-radius:0;
 }
 body.page-template-page-radar-demo .ba-radar-foot{display:none}
}
@media(min-width:1280px){
 body.page-template-page-radar-demo .ba-radar-workspace{grid-template-columns:420px minmax(0,1fr)}
}
@media(min-width:861px) and (max-width:1040px){
 body.page-template-page-radar-demo .ba-radar-controls{gap:6px;padding:8px 9px}
 body.page-template-page-radar-demo .ba-radar-controls .ba-radar-search{
  flex:1 1 290px;min-width:180px;max-width:355px;width:auto;
 }
 body.page-template-page-radar-demo .ba-radar-search-row{flex-wrap:nowrap}
 body.page-template-page-radar-demo .ba-radar-search-row input{flex:1 1 auto;min-width:60px}
 body.page-template-page-radar-demo .ba-radar-controls>label:not(.ba-radar-search){font-size:0}
 body.page-template-page-radar-demo .ba-radar-controls>label:not(.ba-radar-search)>select{font-size:12px;min-width:85px}
}
@media(min-width:861px) and (max-height:650px){
 body.page-template-page-radar-demo .ba-radar-demo{height:calc(100dvh - 68px - var(--wp-admin--admin-bar--height, 0px))}
}
@media(max-width:860px){
 body.page-template-page-radar-demo{overflow:hidden}
 body.page-template-page-radar-demo .ba-radar-demo{
  display:flex;flex-direction:column;
  height:calc(100dvh - 68px - var(--wp-admin--admin-bar--height, 0px));
  min-height:0;overflow:hidden;
 }
 body.page-template-page-radar-demo .ba-radar-controls{
  flex:0 0 auto;max-height:138px;overflow-y:auto;overflow-x:hidden;
  padding:7px 9px;gap:6px;
 }
 body.page-template-page-radar-demo .ba-radar-controls .ba-radar-search{
  flex:1 1 290px;width:auto;max-width:none;min-width:0;
 }
 body.page-template-page-radar-demo .ba-radar-workspace{
  display:block;flex:1 1 auto;min-height:0;height:auto;overflow:hidden;
  padding:0;margin:0;
 }
 body.page-template-page-radar-demo .ba-radar-map{
  width:100%;height:100%;min-height:0;max-height:none;
  border:0;border-radius:0;
 }
 body.page-template-page-radar-demo .ba-radar-panel{
  position:fixed;z-index:999;left:0;right:0;bottom:0;
  height:auto;max-height:78dvh;overflow-y:auto;
  border-radius:22px 22px 0 0;
  transform:translateY(110%);
 }
 body.page-template-page-radar-demo .ba-radar-panel.is-open{transform:translateY(0)}
 body.page-template-page-radar-demo #rad-drawer{display:block}
 body.page-template-page-radar-demo .ba-radar-foot{display:none}
}

/* RADAR-EXPERIENCE-01 — meaningful first-open view without unverified heat or regional ranking. */
.ba-radar-welcome{padding:16px;border-radius:13px;background:linear-gradient(125deg,#e5f5f7,#f6fafc);border:1px solid #cde5e9}
.ba-radar-welcome h3{margin:0 0 7px;font-size:16px;color:#082b43}
.ba-radar-welcome p{margin:0;color:#496a7c;font-size:12px;line-height:1.6}
.ba-radar-topic{display:flex;align-items:center;gap:12px;width:100%;text-align:left;padding:13px;border:1px solid #dce7ee;border-radius:11px;background:#fff;cursor:pointer}
.ba-radar-topic:hover,.ba-radar-topic:focus-visible{border-color:#0798a8;background:#f1fbfc;outline-offset:2px}
.ba-radar-topic-icon{font-size:23px;line-height:1;flex:none}
.ba-radar-topic-body{display:grid;gap:3px;min-width:0}.ba-radar-topic-body strong{font-size:13px;color:#071a38}.ba-radar-topic-body small{font-size:11px;color:#627d8d;line-height:1.4}

/* RADAR-INSIGHT-VISUAL-01 — visible reset and clear analytical hierarchy. */
body.page-template-page-radar-demo .ba-radar-controls #rad-reset{flex:none;display:inline-flex!important;align-items:center;justify-content:center;visibility:visible!important;min-width:104px;height:40px;padding:0 12px;border:1px solid #0b7c8a!important;border-radius:9px;background:#e6f7f8!important;color:#075c6a!important;font-weight:850;font-size:12px;white-space:nowrap;cursor:pointer}
body.page-template-page-radar-demo .ba-radar-controls #rad-reset:hover{background:#bce8ed!important}
.ba-radar-visual-key{position:absolute;z-index:2;left:15px;bottom:22px;display:grid;gap:5px;max-width:min(310px,calc(100% - 30px));background:#fff;padding:13px;border-radius:12px;box-shadow:0 8px 26px #071a3825}
.ba-radar-visual-key strong{color:#072d43;font-size:13px}.ba-radar-visual-key span{color:#33576c;font-size:12px}.ba-radar-visual-key small{color:#a36c23;font-size:10px;line-height:1.5}
.ba-radar-metrics-preview{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:10px}
.ba-radar-metrics-preview>div{padding:12px 10px;border-radius:10px;background:#eaf5f8;border:1px solid #d4e8ed;display:grid;align-content:center;gap:4px;min-height:80px}
.ba-radar-metrics-preview strong{font-size:24px;line-height:1;color:#075d77}
.ba-radar-metrics-preview small{font-size:10px;line-height:1.35;color:#486c7b}
@media(max-width:1040px){body.page-template-page-radar-demo .ba-radar-controls{flex-wrap:wrap;max-height:none;height:auto!important;min-height:56px}body.page-template-page-radar-demo .ba-radar-demo{grid-template-rows:auto minmax(0,1fr)}}

/* RADAR-SEARCH-PARITY-03 — compact Mappe search and two-line Places prediction display. */
body.page-template-page-radar-demo .ba-radar-search-row{display:flex!important;align-items:center!important;flex-wrap:nowrap!important;gap:7px!important}
body.page-template-page-radar-demo .ba-radar-search-row #rad-place{height:40px;flex:1 1 auto!important;min-width:90px!important;border:1px solid #d4e3eb;border-radius:10px;background:#f8fbfd;padding:0 14px;font-size:14px}
body.page-template-page-radar-demo .ba-radar-search-row #rad-nearby{flex:none!important;min-width:0!important;min-height:38px;padding:7px 10px;background:#fff!important;border:1px solid #078e99!important;color:#087c89!important;font-size:12px}
@media(min-width:931px){body.page-template-page-radar-demo .ba-radar-controls .ba-radar-search{flex:0 0 460px!important;width:460px!important;max-width:460px!important}}
@media(max-width:930px){body.page-template-page-radar-demo .ba-radar-search-row #rad-place{flex-basis:auto!important}}
body.page-template-page-radar-demo .ba-radar-suggestions{top:calc(100% + 6px);left:0;right:0;z-index:120;border-radius:14px;border:1px solid #d9e4eb;box-shadow:0 14px 32px rgba(7,26,56,.16)}
body.page-template-page-radar-demo .ba-radar-suggestions .ba-map-place-suggestion{display:grid;gap:2px;height:auto;width:100%;padding:10px 13px;border:0;border-bottom:1px solid #edf2f5;border-radius:0;background:#fff;text-align:left}
body.page-template-page-radar-demo .ba-radar-suggestions .ba-map-place-suggestion:hover{background:#f2f9fa}
body.page-template-page-radar-demo .ba-radar-suggestions .ba-map-place-suggestion strong{font-size:12px;line-height:1.25;color:#071a38}
body.page-template-page-radar-demo .ba-radar-suggestions .ba-map-place-suggestion small{font-size:11px;color:#74889a;line-height:1.25}
.ba-radar-area-shortcuts{padding:13px;border:1px solid #d6e6ed;border-radius:11px;background:#fff}
.ba-radar-area-shortcuts h3{font-size:14px;margin:0 0 6px}
.ba-radar-area-shortcuts p{font-size:11px;color:#61778b;line-height:1.4}
.ba-radar-area-buttons{display:flex;gap:7px;flex-wrap:wrap}
.ba-radar-area-buttons button{border:1px solid #bcdde3;border-radius:999px;background:#eff9fb;padding:8px 11px;color:#085e6b;font-weight:750;font-size:12px;cursor:pointer}

/* RADAR-TYPO-PARITY-01 — match Mappe controls, avoid inherited over-bold weights. */
body.page-template-page-radar-demo .ba-radar-controls{font-size:12px;font-weight:400}
body.page-template-page-radar-demo .ba-radar-controls>label{font-size:11px;font-weight:600;color:#65798c}
body.page-template-page-radar-demo .ba-radar-controls select{font-family:inherit;font-size:12px!important;font-weight:500!important;color:#071a38}
body.page-template-page-radar-demo .ba-radar-search-row #rad-place{font-family:inherit;font-weight:400!important}
body.page-template-page-radar-demo .ba-radar-suggestions .ba-map-place-suggestion{font-family:inherit;font-weight:400!important;color:#071a38!important}
body.page-template-page-radar-demo .ba-radar-suggestions .ba-map-place-suggestion strong{font-size:12px;font-weight:650!important;color:#071a38}
body.page-template-page-radar-demo .ba-radar-suggestions .ba-map-place-suggestion small{font-size:11px;font-weight:400!important;color:#526d83}

/* RADAR-OSSERVATORIO-01 — actual public feed indicators and accessible rankings. */
.ba-observatory-head{padding:14px;border-radius:12px;background:#eaf7f8;border:1px solid #cde7ed}
.ba-observatory-head h3{margin:0 0 6px;font-size:15px;color:#082b43}
.ba-observatory-head p{margin:0;color:#516f80;font-size:11px;line-height:1.5}
.ba-observatory-kpis{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:9px}
.ba-observatory-kpi{display:grid;gap:7px;align-content:space-between;min-height:92px;padding:13px;border-radius:12px;border:1px solid #d3e5eb;background:#fff}
.ba-observatory-kpi strong{font-size:28px;line-height:1;color:#077787;font-variant-numeric:tabular-nums}
.ba-observatory-kpi small{font-size:11px;line-height:1.35;color:#577286}
.ba-observatory-ranking{display:grid;gap:9px;padding:14px 13px;background:#fff;border:1px solid #dce6ec;border-radius:12px}
.ba-observatory-ranking h3{font-size:14px;margin:0 0 3px;color:#071a38}
.ba-observatory-rank{display:grid;gap:5px;border:0;border-radius:8px;background:transparent;width:100%;padding:4px;text-align:left;color:#244c62;font-family:inherit}
button.ba-observatory-rank{cursor:pointer}
button.ba-observatory-rank:hover,button.ba-observatory-rank:focus-visible{background:#eaf7f8;outline-offset:2px}
.ba-observatory-rank-top{display:flex;justify-content:space-between;gap:10px;font-size:12px;line-height:1.3}
.ba-observatory-rank-top strong{font-variant-numeric:tabular-nums;color:#075d6d}
.ba-observatory-track{display:block;width:100%;height:7px;border-radius:999px;background:#e6edf1;overflow:hidden}
.ba-observatory-track>span{display:block;height:100%;background:#078f9c;border-radius:999px}

/* RADAR-REGION-01: territory drill and readable hover states. */
body.page-template-page-radar-demo .ba-observatory-rank:hover,
body.page-template-page-radar-demo .ba-observatory-rank:focus-visible,
body.page-template-page-radar-demo .ba-observatory-rank:hover *,
body.page-template-page-radar-demo .ba-observatory-rank:focus-visible *{color:#071a38!important}
body.page-template-page-radar-demo .ba-observatory-rank:hover{background:#eaf7fa!important}
.ba-radar-back-drill{display:inline-flex;align-items:center;justify-content:center;width:100%;padding:10px;border:1px solid #a8d8df;border-radius:9px;background:#eaf7f9;color:#075e6d!important;font-weight:700;cursor:pointer}
.ba-radar-back-drill:hover{background:#d8f0f3!important;color:#075e6d!important}
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
$radar_terms = get_terms(array('taxonomy'=>'ba_territorio','hide_empty'=>false));
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
  if(!/^\d{4}-\d{2}-\d{2}$/.test(e.occurred_date||''))return false;
  const date=new Date(e.occurred_date+'T12:00:00');
  if(!Number.isFinite(date.getTime()))return false;
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
function backDrill(){
 if(drill.level===3){drill.comune=null;drill.level=2;}
 else if(drill.level===2){drill.province=null;drill.level=1;}
 else if(drill.level===1){drill.region=null;drill.level=0;}
 render();
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
  if(/^\\d{4}-\\d{2}-\\d{2}$/.test(e.occurred_date||'')){
   const dt=new Date(e.occurred_date+'T12:00:00');
   if(Number.isFinite(dt.getTime())){dated++;const month=e.occurred_date.slice(0,7);months.set(month,(months.get(month)||0)+1);}
   else undated++;
  }else undated++;
 }
 const metrics=document.createElement('div');metrics.className='ba-observatory-kpis';
 for(const [value,label] of [
  [items.length,'Segnalazioni pubbliche geolocalizzate nel campione'],
  [types.size,'Tipologie documentate'],
  [places.size,'Territori rappresentati'],
  [dated,'Episodi con data disponibile']
 ]){
  const card=document.createElement('div');card.className='ba-observatory-kpi';
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
 createRanking('Fenomeni più segnalati',types,5,null);
 createRanking(depthLabels[drill.level]+' per segnalazioni',places,6,label=>{
  if(drill.level===0){drill.region=label;drill.level=1;}
  else if(drill.level===1){drill.province=label;drill.level=2;}
  else if(drill.level===2){drill.comune=label;drill.level=3;}
  else{input.value=label;locate();return;}
  render();
 });
 if(dated){
  createRanking('Mesi con episodi datati',months,5,null);
 }
 const quality=document.createElement('p');quality.className='ba-radar-note';
 quality.textContent=numberIT.format(undated)+' segnalazioni senza data valida, escluse dal grafico mensile. Le graduatorie riguardano solo il campione pubblico disponibile: non misurano criminalità, rischio o copertura totale del territorio. Eventuali duplicati non certificati possono incidere sui conteggi.';
 host.append(quality);
}
function render(){
 const filtered=reports.filter(eligible);
 $('rad-title').textContent=chosenCenter?($('rad-place').value.trim()||'Zona selezionata'):'Panoramica territoriale';
 $('rad-map-title').textContent=chosenCenter?'Area di ricerca · '+$('rad-radius').value+' km':'Radar · seleziona una zona';
 const host=$('rad-reports');host.replaceChildren();
 clearGeometry();
 if(loadError){$('rad-copy').textContent='Impossibile consultare le segnalazioni pubbliche.';return;}
 if(loadingReports){$('rad-copy').textContent='Caricamento dei dati pubblici…';return;}
 if(!chosenCenter){
  $('rad-title').textContent='Osservatorio BadAround';
  if(drill.level>0){
   const back=document.createElement('button');back.type='button';back.className='ba-radar-back-drill';
   back.textContent='← Torna indietro';back.addEventListener('click',backDrill);host.appendChild(back);
  }
  $('rad-map-title').textContent='Segnalazioni della community · Italia';
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
   const li=document.createElement('li');li.textContent=name;ul.appendChild(li);
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
 const undated=filtered.some(event=>!/^\d{4}-\d{2}-\d{2}$/.test(event.occurred_date||''));
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
  const response=await fetch(endpoint+'?per_page=100',{credentials:'omit',headers:{Accept:'application/json'}});
  if(!response.ok)throw Error('Discovery HTTP '+response.status);
  const payload=await response.json();
  if(!Array.isArray(payload?.items))throw Error('Discovery response invalid');
  reports=payload.items.filter(e=>e&&typeof e.id==='number'&&typeof e.permalink==='string'&&!/^\s*\[?QA\b/i.test(e.title||'')&&!/nessun evento reale|collaudo tecnico sintetico/i.test(e.title||''));
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
   center:{lat:42.0,lng:12.5},zoom:7,mapTypeControl:false,streetViewControl:false,
   styles:[{featureType:'poi',elementType:'all',stylers:[{visibility:'off'}]},{featureType:'transit',elementType:'all',stylers:[{visibility:'off'}]},{featureType:'road',elementType:'labels.icon',stylers:[{visibility:'off'}]}],
   fullscreenControl:true,zoomControl:true,gestureHandling:'cooperative'
  });
  geocoder=new google.maps.Geocoder();
  try{placesApi=await google.maps.importLibrary('places');}
  catch(error){console.warn('Radar autocomplete unavailable; manual address search remains enabled.',error);}
  if(!restoreArea()){status('');render();}
 }catch(error){status('Google Maps non disponibile: verifica configurazione, autorizzazione del dominio o console del browser.');console.error('Radar map:',error);}
}
function focusLocation(position,address,save=true){
 if(!map)return;
 const pos=position;
 if(!searchCircle)searchCircle=new google.maps.Circle({map,center:pos,radius:radiusMeters(),strokeColor:'#078e99',strokeWeight:2,strokeOpacity:.9,fillColor:'#0798a8',fillOpacity:.05});
 else searchCircle.setCenter(pos);
 if(!centerMarker)centerMarker=new google.maps.Marker({map,position:pos,title:'Centro della ricerca Radar (nessun evento)'});
 else centerMarker.setPosition(pos);
 searchCircle.setRadius(radiusMeters());searchCircle.setVisible(true);
 chosenCenter={lat:pos.lat instanceof Function?pos.lat():pos.lat,lng:pos.lng instanceof Function?pos.lng():pos.lng};
 map.fitBounds(searchCircle.getBounds(),60);
 input.value=address;
 if(save)rememberArea(chosenCenter,address);
 status('Area individuata. Eventi pubblici filtrati senza analisi di rischio.');
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
$('rad-radius').addEventListener('change',()=>{if(searchCircle&&map)map.fitBounds(searchCircle.getBounds(),60)});


$('rad-reset').addEventListener('click',()=>{
 for(const [key,value] of Object.entries(initial))$('rad-'+key).value=value;
 chosenCenter=null;
 try{window.localStorage.removeItem(LAST_AREA_KEY);}catch(e){}
 searchCircle?.setMap(null);searchCircle=null;centerMarker?.setMap(null);centerMarker=null;
 if(map){map.setCenter({lat:42.0,lng:12.5});map.setZoom(7);}
 status('');render();
});
$('rad-drawer').addEventListener('click',()=>{const open=$('rad-panel').classList.toggle('is-open');$('rad-drawer').textContent=open?'Chiudi analisi':'Mostra analisi zona';$('rad-drawer').setAttribute('aria-expanded',String(open))});
render();loadMap();fetchReports();
})();
</script>
<?php get_footer(); ?>
