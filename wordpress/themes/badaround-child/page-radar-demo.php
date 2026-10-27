<?php
/**
 * Template Name: BadAround — Radar Demo
 * RADAR-ONLINE-UX-01: DEMO ONLY — no real event or location data.
 */
if (!defined('ABSPATH')) exit;
get_header();
?>
<main class="ba-radar-demo" id="main-content">
 <header class="ba-radar-heading"><div><p class="ba-radar-kicker">RADAR · ANTEPRIMA INTERATTIVA</p><h1>BadAround Radar</h1><p>Conosci una zona prima di andarci.</p></div><strong class="ba-radar-demo-pill">DATI SIMULATI · NON SONO ALLERTE REALI</strong></header>
 <div class="ba-radar-controls" aria-label="Filtri dimostrativi Radar">
  <label class="ba-radar-search">Luogo dimostrativo<input id="rad-place" value="Quartiere Alfa — area immaginaria" aria-label="Luogo dimostrativo" /></label>
  <label>Categoria<select id="rad-cat"><option>Tutte le categorie</option><option>Veicoli</option><option>Pericoli</option><option>Spazi pubblici</option><option>Animali</option><option>Case e attività</option><option>Oggetti e documenti</option></select></label>
  <label>Periodo<select id="rad-period"><option value="30">30 giorni</option><option value="7">7 giorni</option><option value="90">90 giorni</option><option value="365">12 mesi</option></select></label>
  <label>Raggio<select id="rad-radius"><option value="1">1 km</option><option value="0.5">500 m</option><option value="2">2 km</option><option value="5">5 km</option></select></label>
  <button id="rad-reset" type="button">↺ Reset</button>
 </div>
 <div class="ba-radar-workspace">
  <section class="ba-radar-map" aria-label="Cartografia schematica e fittizia">
   <svg viewBox="0 0 1000 690" preserveAspectRatio="xMidYMid slice" role="img" aria-label="Mappa di fantasia con distribuzione simulata di segnalazioni">
    <defs><pattern id="rad-street" width="100" height="100" patternUnits="userSpaceOnUse" patternTransform="rotate(-13)"><path d="M100 0H0V100" fill="none" stroke="#cbdcdd" stroke-width="5"/></pattern><radialGradient id="rad-heat"><stop stop-color="#cb3749" stop-opacity=".85"/><stop offset=".3" stop-color="#ee784b" stop-opacity=".72"/><stop offset=".65" stop-color="#f8c95e" stop-opacity=".53"/><stop offset="1" stop-color="#ffeaa3" stop-opacity="0"/></radialGradient></defs>
    <rect width="1000" height="690" fill="#e7efe9"/><rect width="1000" height="690" fill="url(#rad-street)"/>
    <path d="M-50 370Q400 220 1060 445M320 -80L685 760" stroke="white" stroke-width="34" fill="none"/>
    <path d="M-50 370Q400 220 1060 445M320 -80L685 760" stroke="#bbd0d4" stroke-width="3" fill="none"/>
    <g fill="#6a8484" font-family="sans-serif" font-size="23"><text x="95" y="190">Quartiere Alfa</text><text x="700" y="180">Zona Beta</text><text x="630" y="555">Parco Gamma</text></g>
    <g id="rad-heat-layer"><ellipse cx="390" cy="342" rx="285" ry="220" fill="url(#rad-heat)"/><ellipse cx="735" cy="330" rx="200" ry="140" fill="url(#rad-heat)" opacity=".6"/></g>
    <circle cx="490" cy="363" r="120" stroke="#078f9c" stroke-width="4" stroke-dasharray="9 8" fill="#0798a8" fill-opacity=".04"/><circle cx="490" cy="363" r="8" fill="#087d90" stroke="white" stroke-width="4"/>
   </svg>
   <div class="ba-radar-map-title" id="rad-map-title">Area dimostrativa · 1 km</div>
   <div class="ba-radar-legend" id="rad-legend"><strong>Concentrazione simulata</strong><div class="ba-radar-gradient"></div><small>Non rappresenta la pericolosità reale</small></div>
  </section>
  <aside class="ba-radar-panel" id="rad-panel">
   <span class="ba-radar-tag">Analisi territoriale · DEMO</span>
   <h2 id="rad-title">Quartiere Alfa</h2>
   <p id="rad-copy">Questa è una simulazione UX. Nessun evento reale viene analizzato.</p>
   <div class="ba-radar-metrics"><div><strong id="rad-count">12</strong><small>Segnalazioni fittizie</small></div><div><strong id="rad-types">3</strong><small>Tipologie fittizie</small></div></div>
   <div id="rad-advice" class="ba-radar-advice"><strong>Esempio di attenzione contestuale</strong><p>Evita di lasciare oggetti di valore in automobile. Questo consiglio non deriva da episodi reali relativi alla zona indicata.</p></div>
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
</style>
<script>
(()=>{'use strict';const root=document.querySelector('.ba-radar-demo');if(!root)return;const $=id=>root.querySelector('#'+id);let state='sample';const initial={place:'Quartiere Alfa — area immaginaria',cat:'Tutte le categorie',period:'30',radius:'1'};function render(){const place=$('rad-place').value||'Area dimostrativa';$('rad-title').textContent=place;$('rad-map-title').textContent=place+' · '+$('rad-radius').value+' km';const on=state==='sample';$('rad-heat-layer').style.display=on?'':'none';$('rad-legend').style.display=on?'':'none';$('rad-count').textContent=on?'12':'—';$('rad-types').textContent=on?'3':'—';$('rad-advice').style.display=on?'':'none';$('rad-copy').textContent=on?'Questi dati sono simulati. Categoria: '+$('rad-cat').value+'. Periodo: '+$('rad-period').selectedOptions[0].text+'.':state==='few'?'Dati insufficienti per rappresentare una concentrazione significativa.':state==='zero'?'Nessun risultato nel campione simulato: ciò non indica che la zona sia sicura.':'Simulazione di errore: analisi momentaneamente non disponibile.';}['rad-place','rad-cat','rad-period','rad-radius'].forEach(id=>$(id).addEventListener('input',render));root.querySelectorAll('[data-rad-state]').forEach(btn=>btn.addEventListener('click',()=>{state=btn.dataset.radState;root.querySelectorAll('[data-rad-state]').forEach(x=>x.classList.toggle('is-active',x===btn));render()}));$('rad-reset').addEventListener('click',()=>{$('rad-place').value=initial.place;$('rad-cat').value=initial.cat;$('rad-period').value=initial.period;$('rad-radius').value=initial.radius;state='sample';root.querySelectorAll('[data-rad-state]').forEach(x=>x.classList.toggle('is-active',x.dataset.radState==='sample'));render()});$('rad-drawer').addEventListener('click',()=>{const on=$('rad-panel').classList.toggle('is-open');$('rad-drawer').textContent=on?'Chiudi analisi':'Mostra analisi zona';$('rad-drawer').setAttribute('aria-expanded',String(on))});render()})();
</script>
<?php get_footer(); ?>
