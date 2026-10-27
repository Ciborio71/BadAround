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
 <header class="ba-radar-heading"><div><p class="ba-radar-kicker">RADAR · ANTEPRIMA INTERATTIVA</p><h1>BadAround Radar</h1><p>Conosci una zona prima di andarci.</p></div><strong class="ba-radar-demo-pill">DATI SIMULATI · NON SONO ALLERTE REALI</strong></header>
 <div class="ba-radar-controls" aria-label="Filtri dimostrativi Radar">
  <label class="ba-radar-search">Cerca un luogo reale<input id="rad-place" value="" placeholder="Indirizzo, quartiere o località" aria-label="Cerca un luogo reale" autocomplete="off" /><button id="rad-search" type="button">Cerca</button></label>
  <label>Categoria<select id="rad-cat"><option>Tutte le categorie</option><option>Veicoli</option><option>Pericoli</option><option>Spazi pubblici</option><option>Animali</option><option>Case e attività</option><option>Oggetti e documenti</option></select></label>
  <label>Periodo<select id="rad-period"><option value="30">30 giorni</option><option value="7">7 giorni</option><option value="90">90 giorni</option><option value="365">12 mesi</option></select></label>
  <label>Raggio<select id="rad-radius"><option value="1">1 km</option><option value="0.5">500 m</option><option value="2">2 km</option><option value="5">5 km</option></select></label>
  <button id="rad-reset" type="button">↺ Reset</button>
 </div>
 <div class="ba-radar-workspace">
  <section class="ba-radar-map" aria-label="Cartografia schematica e fittizia">
   <div id="rad-google-map" class="ba-radar-google-map" role="application" aria-label="Google Maps — cartografia reale, nessuna segnalazione reale visualizzata"></div>
   <div id="rad-demo-heat" class="ba-radar-demo-heat" hidden aria-hidden="true"></div>
   <div id="rad-map-message" class="ba-radar-map-message" role="status" aria-live="polite">Caricamento cartografia Google Maps…</div>
   <div class="ba-radar-map-disclaimer">CARTOGRAFIA REALE · ANALISI E HEATMAP SIMULATE</div>
   <div class="ba-radar-layers" id="rad-layers"><div class="ba-radar-layers__head"><strong>Livelli</strong><button id="rad-layers-close" type="button" aria-label="Chiudi livelli">×</button></div><p>Scegli cosa visualizzare nella simulazione.</p><label><input type="checkbox" id="rad-layer-heat" checked> Heatmap simulata</label><label><input type="checkbox" id="rad-layer-streets" checked> Cartografia stradale</label><label><input type="checkbox" id="rad-layer-radius" checked> Area di ricerca</label><small>Tutti i livelli e i dati sono fittizi.</small></div><button id="rad-layers-toggle" type="button" class="ba-radar-layers-toggle" aria-controls="rad-layers" aria-expanded="false">☷ Livelli</button>
   <div class="ba-radar-map-title" id="rad-map-title">Area dimostrativa · 1 km</div>
   <button class="ba-radar-layer-toggle" id="rad-layer-toggle" type="button" aria-pressed="true">Livello termico: attivo</button><div class="ba-radar-legend" id="rad-legend"><strong>Concentrazione simulata</strong><div class="ba-radar-gradient"></div><small>Non rappresenta la pericolosità reale</small></div>
  </section>
  <aside class="ba-radar-panel" id="rad-panel">
   <span class="ba-radar-tag">Analisi territoriale · DEMO</span>
   <h2 id="rad-title">Quartiere Alfa</h2>
   <p id="rad-copy" role="status" aria-live="polite">Questa è una simulazione UX. Nessun evento reale viene analizzato.</p>
   <div class="ba-radar-metrics" id="rad-metrics"><div><strong id="rad-count">12</strong><small>Segnalazioni fittizie</small></div><div><strong id="rad-types">3</strong><small>Tipologie fittizie</small></div></div>
   <div class="ba-radar-brief"><strong>Panoramica della zona</strong><p id="rad-brief">Distribuzione simulata di segnalazioni, senza attribuzioni di rischio.</p></div><div id="rad-advice" class="ba-radar-advice"><strong>Esempio di attenzione contestuale</strong><p>Evita di lasciare oggetti di valore in automobile. Questo consiglio non deriva da episodi reali relativi alla zona indicata.</p></div>
   <p class="ba-radar-note">Una maggiore concentrazione di segnalazioni non certifica la pericolosità. Nessun dato non significa zona sicura.</p>
   <div class="ba-radar-states" role="group" aria-label="Simula stati Radar"><button class="is-active" data-rad-state="sample" type="button">Esempio</button><button data-rad-state="few" type="button">Dati insufficienti</button><button data-rad-state="zero" type="button">Nessun risultato</button><button data-rad-state="error" type="button">Errore</button></div>
   <a class="ba-radar-back" href="<?php echo esc_url(home_url('/mappa/')); ?>">Apri la mappa delle segnalazioni reali →</a>
  </aside>
 </div>
 <button id="rad-drawer" type="button" aria-controls="rad-panel" aria-expanded="false">Mostra analisi zona</button>
 <p class="ba-radar-foot">RADAR-ONLINE-UX-01 · Prototipo dimostrativo · Nessuna heatmap reale o avviso attivo · Dati sintetici</p>
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
const initial={place:'',cat:'Tutte le categorie',period:'30',radius:'1'};
const categories={'Tutte le categorie':12,'Veicoli':4,'Pericoli':3,'Spazi pubblici':3,'Animali':1,'Case e attività':1,'Oggetti e documenti':0};
const config=window.BadAroundRadarMaps||{};
const status=(message)=>{$('rad-map-message').textContent=message;$('rad-map-message').hidden=!message;};
const radiusMeters=()=>Number($('rad-radius').value)*1000;
function updateCircle(){
 if(!map||!searchCircle)return;
 searchCircle.setRadius(radiusMeters());
}
function render(){
 const place=$('rad-place').value.trim()||'Italia · zona non selezionata';
 $('rad-title').textContent=place;
 $('rad-map-title').textContent=place+' · '+$('rad-radius').value+' km';
 const base=categories[$('rad-cat').value]??0;
 const periodFactor=({'7':.35,'30':1,'90':1.3,'365':1.6})[$('rad-period').value]??1;
 const count=Math.round(base*periodFactor);
 const active=state==='sample'&&count>=3;
 const effective=state==='sample'&&!active?'few':state;
 const demoHeat=active&&layer&&$('rad-layer-heat').checked;
 $('rad-demo-heat').hidden=!demoHeat;
 $('rad-legend').style.display=demoHeat?'':'none';
 $('rad-layer-toggle').setAttribute('aria-pressed',String(layer));
 $('rad-layer-toggle').textContent=layer?'Simulazione termica: attiva':'Simula heatmap: disattivata';
 $('rad-count').textContent=active?String(count):'—';
 $('rad-types').textContent=active?($('rad-cat').value==='Tutte le categorie'?'3':'1'):'—';
 $('rad-metrics').hidden=effective!=='sample';
 $('rad-advice').style.display=effective==='sample'?'':'none';
 const messages={
 sample:'Esempio dimostrativo con '+count+' segnalazioni fittizie. Nessuna informazione sui rischi effettivi di '+place+'.',
 few:'Dati insufficienti: nessuna concentrazione significativa rappresentabile.',
 zero:'Nessun risultato nella simulazione; questo non dimostra che l’area sia sicura.',
 error:'Errore simulato: analisi non disponibile.'
 };
 $('rad-copy').textContent=messages[effective];
 $('rad-brief').textContent=effective==='sample'?'La mappa Google mostra luoghi reali; dati, numeri e avvisi Radar restano completamente simulati.':'Nessuna conclusione territoriale disponibile.';
 root.querySelectorAll('[data-rad-state]').forEach(x=>x.setAttribute('aria-pressed',String(x.dataset.radState===state)));
 if(map)map.setMapTypeId($('rad-layer-streets').checked?'roadmap':'terrain');
 if(searchCircle)searchCircle.setVisible($('rad-layer-radius').checked);
 updateCircle();
}
async function loadMap(){
 if(!config.mapsUrl){status('Chiave Google Maps non disponibile: verificare la configurazione del sito.');return;}
 try{
  if(!window.google?.maps){
   await new Promise((resolve,reject)=>{
    const tag=document.createElement('script');tag.src=config.mapsUrl;tag.async=true;
    tag.onload=resolve;tag.onerror=()=>reject(new Error('Google Maps non raggiungibile'));
    document.head.appendChild(tag);
   });
  }
  if(!window.google?.maps)throw new Error('Google Maps non inizializzato');
  map=new google.maps.Map($('rad-google-map'),{
   center:{lat:42.2,lng:12.5},zoom:6,mapTypeControl:false,streetViewControl:false,
   fullscreenControl:true,zoomControl:true,gestureHandling:'cooperative',
  });
  geocoder=new google.maps.Geocoder();
  status('');
  render();
 }catch(error){status('Impossibile caricare Google Maps. Riprova oppure verifica le autorizzazioni della chiave.');console.error('BadAround Radar demo map:',error);}
}
function locate(){
 if(!geocoder||!map){status('La cartografia non è ancora disponibile.');return;}
 const address=$('rad-place').value.trim();
 if(!address){status('Inserisci un indirizzo, una località o un quartiere.');return;}
 status('Ricerca indirizzo in corso…');
 geocoder.geocode({address,region:'IT'},(results,code)=>{
  if(code!=='OK'||!results?.length){status('Località non trovata. Verifica l’indirizzo.');return;}
  const first=results[0],pos=first.geometry.location;
  if(!searchCircle)searchCircle=new google.maps.Circle({map,center:pos,radius:radiusMeters(),strokeColor:'#078e99',strokeWeight:2,strokeOpacity:.9,fillColor:'#0798a8',fillOpacity:.05});
  else searchCircle.setCenter(pos);
  if(!centerMarker)centerMarker=new google.maps.Marker({map,position:pos,title:'Centro della ricerca Radar (nessun evento)'});
  else centerMarker.setPosition(pos);
  searchCircle.setRadius(radiusMeters());
  searchCircle.setVisible($('rad-layer-radius').checked);
  map.fitBounds(searchCircle.getBounds(),60);
  $('rad-place').value=first.formatted_address;
  status('Area geografica individuata. Nessuna analisi reale viene effettuata.');
  render();
 });
}
['rad-cat','rad-period','rad-radius'].forEach(id=>$(id).addEventListener('change',render));
$('rad-radius').addEventListener('change',()=>{if(searchCircle&&map)map.fitBounds(searchCircle.getBounds(),60)});
$('rad-place').addEventListener('input',render);
$('rad-place').addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();locate()}});
$('rad-search').addEventListener('click',locate);
root.querySelectorAll('[data-rad-state]').forEach(btn=>btn.addEventListener('click',()=>{state=btn.dataset.radState;root.querySelectorAll('[data-rad-state]').forEach(x=>x.classList.toggle('is-active',x===btn));render()}));
$('rad-layer-toggle').addEventListener('click',()=>{layer=!layer;render()});
const layers=$('rad-layers'),toggle=$('rad-layers-toggle');
const setLayers=open=>{layers.classList.toggle('is-open',open);toggle.setAttribute('aria-expanded',String(open))};
toggle.addEventListener('click',()=>setLayers(!layers.classList.contains('is-open')));
$('rad-layers-close').addEventListener('click',()=>{setLayers(false);toggle.focus()});
['rad-layer-heat','rad-layer-streets','rad-layer-radius'].forEach(id=>$(id).addEventListener('change',render));
document.addEventListener('keydown',e=>{if(e.key==='Escape'&&layers.classList.contains('is-open')){setLayers(false);toggle.focus()}});
$('rad-reset').addEventListener('click',()=>{
 for(const [key,value] of Object.entries(initial))$('rad-'+key).value=value;
 state='sample';layer=false;$('rad-layer-heat').checked=true;$('rad-layer-streets').checked=true;$('rad-layer-radius').checked=true;
 root.querySelectorAll('[data-rad-state]').forEach(x=>x.classList.toggle('is-active',x.dataset.radState==='sample'));
 searchCircle?.setMap(null);searchCircle=null;centerMarker?.setMap(null);centerMarker=null;
 if(map){map.setCenter({lat:42.2,lng:12.5});map.setZoom(6);}
 status('');render();
});
$('rad-drawer').addEventListener('click',()=>{const open=$('rad-panel').classList.toggle('is-open');$('rad-drawer').textContent=open?'Chiudi analisi':'Mostra analisi zona';$('rad-drawer').setAttribute('aria-expanded',String(open))});
render();loadMap();
})();
</script>
<?php get_footer(); ?>
