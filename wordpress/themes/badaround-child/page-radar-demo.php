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
  <div class="ba-radar-search"><label for="rad-place">Cerca un luogo reale</label><div class="ba-radar-search-row"><input id="rad-place" value="" placeholder="Indirizzo, quartiere o località" aria-label="Cerca un luogo reale" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="rad-suggestions" /><button id="rad-search" type="button">Cerca</button><button id="rad-nearby" type="button" aria-label="Esplora la zona attorno alla mia posizione">⌖ Vicino a me</button></div><div id="rad-suggestions" class="ba-radar-suggestions" role="listbox" aria-label="Suggerimenti indirizzi" hidden></div></div>
  <label>Categoria<select id="rad-cat"><option>Tutte le categorie</option><option>Veicoli</option><option>Pericoli</option><option>Spazi pubblici</option><option>Animali</option><option>Case e attività</option><option>Oggetti e documenti</option></select></label>
  <label>Periodo<select id="rad-period"><option value="all" selected>Tutti i periodi</option><option value="30">30 giorni</option><option value="7">7 giorni</option><option value="90">90 giorni</option><option value="365">12 mesi</option></select></label>
  <label>Raggio<select id="rad-radius"><option value="1">1 km</option><option value="0.5">500 m</option><option value="2">2 km</option><option value="5">5 km</option></select></label>
  <button id="rad-reset" type="button">↺ Reset</button>
 </div>
 <div class="ba-radar-workspace">
  <section class="ba-radar-map" aria-label="Cartografia Google Maps e segnalazioni pubbliche">
   <div id="rad-google-map" class="ba-radar-google-map" role="application" aria-label="Google Maps — geometrie delle segnalazioni pubblicate"></div>
   
   <div id="rad-map-message" class="ba-radar-map-message" role="status" aria-live="polite">Caricamento cartografia Google Maps…</div>
   <div class="ba-radar-map-disclaimer">EVENTI PUBBLICI · NESSUNA ANALISI DI RISCHIO</div>
   <div class="ba-radar-map-title" id="rad-map-title">Area dimostrativa · 1 km</div>

  </section>
  <aside class="ba-radar-panel" id="rad-panel">
   <span class="ba-radar-tag">Segnalazioni pubbliche · Consultazione</span>
   <h2 id="rad-title">Eventi pubblicati</h2>
   <p id="rad-copy" role="status" aria-live="polite">Caricamento delle segnalazioni pubbliche…</p>
   <p class="ba-radar-note">I risultati non sono un indice di sicurezza. Le segnalazioni prive della data dell'episodio compaiono solo selezionando «Tutti i periodi». Le aree approssimate non identificano il luogo esatto dell'evento.</p>
   <div id="rad-reports" class="ba-radar-reports" aria-label="Elenco segnalazioni pubbliche"></div>
   <a class="ba-radar-back" href="<?php echo esc_url(home_url('/mappa/')); ?>">Apri la mappa delle segnalazioni reali →</a>
  </aside>
 </div>
 <button id="rad-drawer" type="button" aria-controls="rad-panel" aria-expanded="false">Mostra analisi zona</button>
 <p class="ba-radar-foot">RADAR-DISCOVERY-01 · Eventi pubblici in sola lettura · Nessuna heatmap di rischio attiva</p>
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
</style>
<?php
$radar_maps_settings = get_option('wpforms_settings',array());
$radar_maps_key = is_array($radar_maps_settings) && !empty($radar_maps_settings['geolocation-google-places-api-key'])
 ? trim((string)$radar_maps_settings['geolocation-google-places-api-key']) : '';
$radar_maps_url = $radar_maps_key ? add_query_arg(array('key'=>$radar_maps_key,'v'=>'weekly','loading'=>'async'),'https://maps.googleapis.com/maps/api/js') : '';
?>
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
  if(!/^\\d{4}-\\d{2}-\\d{2}$/.test(e.occurred_date||''))return false;
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
function render(){
 const filtered=reports.filter(eligible);
 $('rad-title').textContent=chosenCenter?($('rad-place').value.trim()||'Zona selezionata'):'Eventi pubblicati in Italia';
 const label=loadError?'Impossibile caricare le segnalazioni pubbliche.':loadingReports?'Caricamento delle segnalazioni pubbliche…':!filtered.length?'Nessuna segnalazione con geometria pubblica compatibile con i filtri. Non significa che la zona sia sicura.':filtered.length+' segnalazioni pubbliche visualizzabili (non corrette per copertura, duplicati o rischio).';
 $('rad-copy').textContent=label;
 $('rad-map-title').textContent=chosenCenter?'Area di ricerca · '+$('rad-radius').value+' km':'Segnalazioni pubblicate · Italia';
 const host=$('rad-reports');host.replaceChildren();
 if(map)clearGeometry();
 for(const event of filtered){
  const card=document.createElement('article');card.className='ba-radar-report';
  const a=document.createElement('a');a.textContent=event.title||'Segnalazione';a.href=event.permalink||'#';a.rel='noopener';
  const meta=document.createElement('small');meta.textContent=(event.event_type?.name||'Evento')+' · '+(event.public_place_name||event.territory?.name||'Territorio non specificato')+(event.public_geo.mode==='approximate_area'?' · area approssimata':'');
  card.append(a,meta);host.appendChild(card);
  if(!map)continue;
  const g=event.public_geo;
  if(g.mode==='approximate_area'){
   const circle=new google.maps.Circle({map,center:{lat:g.lat,lng:g.lng},radius:g.radius_meters,
    strokeColor:'#078e99',strokeOpacity:.7,strokeWeight:1,fillColor:'#0798a8',fillOpacity:.09});
   geometryLayers.push(circle);
  }else{
   const marker=new google.maps.Marker({map,position:{lat:g.lat,lng:g.lng},title:event.title||'Evento'});
   marker.addListener('click',()=>{card.scrollIntoView({block:'nearest',behavior:'smooth'});card.classList.add('ba-radar-report--selected');});
   geometryLayers.push(marker);
  }
 }
 if(searchCircle)searchCircle.setRadius(radiusMeters());
}
async function fetchReports(){
 loadingReports=true;render();
 try{
  const response=await fetch(endpoint+'?per_page=100',{credentials:'omit',headers:{Accept:'application/json'}});
  if(!response.ok)throw Error('Discovery HTTP '+response.status);
  const payload=await response.json();
  if(!Array.isArray(payload?.items))throw Error('Discovery response invalid');
  reports=payload.items.filter(e=>e&&typeof e.id==='number'&&typeof e.permalink==='string'&&!/^\\s*\\[?QA\\b/i.test(e.title||'')&&!/nessun evento reale|collaudo tecnico sintetico/i.test(e.title||''));
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
   center:{lat:42.2,lng:12.5},zoom:6,mapTypeControl:false,streetViewControl:false,
   styles:[{featureType:'poi',elementType:'all',stylers:[{visibility:'off'}]},{featureType:'transit',elementType:'all',stylers:[{visibility:'off'}]},{featureType:'road',elementType:'labels.icon',stylers:[{visibility:'off'}]}],
   fullscreenControl:true,zoomControl:true,gestureHandling:'cooperative'
  });
  geocoder=new google.maps.Geocoder();
  try{placesApi=await google.maps.importLibrary('places');}
  catch(error){console.warn('Radar autocomplete unavailable; manual address search remains enabled.',error);}
  status('');render();
 }catch(error){status('Google Maps non disponibile: verifica configurazione, autorizzazione del dominio o console del browser.');console.error('Radar map:',error);}
}
function focusLocation(position,address){
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
 if(!placesApi?.AutocompleteSuggestion || query.length<3){clearSuggestions();return;}
 const id=++requestId;
 try{
  if(!sessionToken)sessionToken=new placesApi.AutocompleteSessionToken();
  const {suggestions:results}=await placesApi.AutocompleteSuggestion.fetchAutocompleteSuggestions({input:query,sessionToken,region:'it',language:'it'});
  if(id!==requestId)return;
  clearSuggestions();
  for(const item of (results||[]).slice(0,6)){
   if(!item.placePrediction)continue;
   const prediction=item.placePrediction;
   const b=document.createElement('button');b.type='button';b.setAttribute('role','option');
   b.textContent=prediction.text?.text||prediction.mainText?.text||query;
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
 if(query.length<3){clearSuggestions();return;}
 debounce=setTimeout(()=>autocomplete(query),300);
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
$('rad-search').addEventListener('click',locate);
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
 searchCircle?.setMap(null);searchCircle=null;centerMarker?.setMap(null);centerMarker=null;
 if(map){map.setCenter({lat:42.2,lng:12.5});map.setZoom(6);}
 status('');render();
});
$('rad-drawer').addEventListener('click',()=>{const open=$('rad-panel').classList.toggle('is-open');$('rad-drawer').textContent=open?'Chiudi analisi':'Mostra analisi zona';$('rad-drawer').setAttribute('aria-expanded',String(open))});
render();loadMap();fetchReports();
})();
</script>
<?php get_footer(); ?>
