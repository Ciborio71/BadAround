'use strict';
const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const {JSDOM}=require('jsdom');
const source=fs.readFileSync(path.join(__dirname,'../wordpress/themes/badaround-child/assets/js/native-report/smart-address.js'),'utf8');
const {resolveTerritory}=require('../wordpress/themes/badaround-child/assets/js/native-report/smart-address.js');
const territory=[
{id:2,parent:0,name:'Lazio'},
{id:3,parent:2,name:'Roma'},
{id:4,parent:3,name:'Pomezia'},
{id:5,parent:4,name:'Torvaianica'},
{id:6,parent:3,name:'Roma'}
];
const ac=(type,longText,shortText)=>({types:[type],longText,shortText});
test('canonical Pomezia/Torvaianica match is strict and complete',()=>{
  const x=resolveTerritory([ac('country','Italia','IT'),ac('administrative_area_level_1','Lazio','Lazio'),ac('administrative_area_level_2','Città metropolitana di Roma Capitale','RM'),ac('locality','Pomezia','Pomezia'),ac('sublocality','Torvaianica','Torvaianica')],territory);
  assert.deepEqual(x.location,{region:'Lazio',province:'Roma',municipality:'Pomezia',locality:'Torvaianica'});
});
test('exact hierarchy is accepted; location is optional',()=>{
  const base=[ac('country','Italy','IT'),ac('administrative_area_level_1','Lazio','Lazio'),ac('administrative_area_level_2','Roma','RM'),ac('locality','Pomezia','Pomezia')];
  assert.deepEqual(resolveTerritory(base,territory).location,{region:'Lazio',province:'Roma',municipality:'Pomezia',locality:''});
  assert.deepEqual(resolveTerritory([...base,ac('sublocality','Torvaianica','Torvaianica')],territory).location,{region:'Lazio',province:'Roma',municipality:'Pomezia',locality:'Torvaianica'});
});
test('country guard, omissions and homonymous ambiguous terms are fail-closed',()=>{
  const base=[ac('country','France','FR'),ac('administrative_area_level_1','Lazio','Lazio')];
  assert.equal(resolveTerritory(base,territory).error,'outside_italy');
  const italy=[ac('country','Italia','IT'),ac('administrative_area_level_1','Lazio','Lazio')];
  assert.equal(resolveTerritory(italy,territory).error,'unresolved_province');
  const duplicate=[...territory,{id:99,parent:2,name:'Roma'}];
  assert.equal(resolveTerritory([...italy,ac('administrative_area_level_2','Roma','RM')],duplicate).error,'unresolved_province');
});
function setup(){
  const dom=new JSDOM(`<!doctype html><html><head></head><body><section data-native-report>
  <div data-native-smart-address><input data-smart-start type="text" readonly><div data-smart-widget></div><p data-smart-status></p><div data-smart-confirmation hidden><p data-smart-address-label></p></div><button data-smart-manual hidden type="button">Inserisci manualmente</button></div><div data-native-location-fields hidden>
  <input data-native-input name="location.region"><input data-native-input name="location.province"><input data-native-input name="location.municipality"><input data-native-input name="location.locality">
  <input data-native-input name="location.exact_address"><input data-native-input name="location.exact_lat"><input data-native-input name="location.exact_lng"></div>
  </section></body></html>`,{url:'https://staging.badaround.it/segnala-un-evento/',runScripts:'outside-only'});
  dom.window.eval(source);
  const win=dom.window,container=win.document.querySelector('[data-native-report]');
  const model={values:{},set(k,v){this.values[k]=v;}};
  container.addEventListener('input',e=>model.set(e.target.name,e.target.value));
  return {dom,win,container,model,button:container.querySelector('[data-smart-start]')};
}
test('zero Google initial requests: load only after explicit click, without map instance',async()=>{
  const h=setup(),controller=h.win.BadAroundNative.SmartAddress.mount(h.container,{smartAddress:{key:'TEST_BROWSER_KEY',territories:territory}},h.model);
  assert.equal(h.win.document.querySelectorAll('script[src*="maps.googleapis.com"]').length,0);
  h.button.click();
  const scripts=h.win.document.querySelectorAll('script[src*="maps.googleapis.com"]');
  assert.equal(scripts.length,1);
  assert(scripts[0].src.includes('loading=async'));
  assert(!scripts[0].src.includes('libraries=maps'));
  let mapCreated=0;
  class Widget extends h.win.HTMLElement {}
  h.win.customElements.define('fake-place-widget',Widget);
  h.win.google={maps:{importLibrary:async library=>{assert.equal(library,'places');return {PlaceAutocompleteElement:class {constructor(){return h.win.document.createElement('fake-place-widget');}}};},Map:class {constructor(){mapCreated++;}}}};
  h.win[new URL(h.win.document.querySelector('script[data-ba-smart-address]').src).searchParams.get('callback')]();
  await new Promise(resolve=>setImmediate(resolve));
  assert.equal(mapCreated,0);
  assert(h.container.querySelector('[data-smart-widget] fake-place-widget'));
  controller.dispose();h.dom.window.close();
});
test('when Google fails, manual canonical controls are still usable',async()=>{
  const h=setup(),controller=h.win.BadAroundNative.SmartAddress.mount(h.container,{smartAddress:{key:'TEST_BROWSER_KEY',territories:territory}},h.model);
  h.button.click();
  h.win.document.querySelector('script[src*="maps.googleapis.com"]').onerror();
  await new Promise(resolve=>setImmediate(resolve));
  assert.equal(h.button.disabled,false);
  assert(h.container.querySelector('[data-smart-status]').textContent.includes('campi manuali'));
  assert.equal(h.container.querySelector('[name="location.region"]').disabled,false);
  controller.dispose();h.dom.window.close();
});
test('frozen adapters and report schema are not touched by the smart address module',()=>{
  assert(!source.includes('google.maps.Map('));
  assert(!source.includes('register_term'));
  assert(!source.includes('console.log'));
  assert(source.includes("importLibrary('places')"));
});

function deferred(){
  let resolve,reject;
  const promise=new Promise((yes,no)=>{resolve=yes;reject=no;});
  return {promise,resolve,reject};
}
async function liveWidget(){
  const h=setup(),requests=[];
  class Widget extends h.win.HTMLElement {}
  h.win.customElements.define('fake-r6-place-widget',Widget);
  const adapter=h.win.BadAroundNative.SmartAddress;
  const controller=adapter.mount(h.container,{smartAddress:{key:'TEST_BROWSER_KEY',territories:territory}},h.model);
  h.button.click();
  h.win.google={maps:{importLibrary:async()=>({PlaceAutocompleteElement:class {constructor(){return h.win.document.createElement('fake-r6-place-widget');}}})}};
  h.win[new URL(h.win.document.querySelector('script[data-ba-smart-address]').src).searchParams.get('callback')]();
  await new Promise(resolve=>setImmediate(resolve));
  const widget=h.container.querySelector('fake-r6-place-widget');
  assert(widget,'widget must be ready');
  const select=(options={})=>{
    const event=new h.win.Event('gmp-select');
    const waiting=options.waiting||deferred();
    const place={
      addressComponents:options.components||[
        ac('country','Italia','IT'), ac('administrative_area_level_1','Lazio','Lazio'),
        ac('administrative_area_level_2','Roma','RM'),ac('locality','Pomezia','Pomezia'),
        ac('sublocality','Torvaianica','Torvaianica')
      ],
      formattedAddress:options.address||'Via Gran Bretagna 66, Pomezia',
      location:{lat:()=>41.66,lng:()=>12.43},
      id:'provider-internal-id-not-to-be-persisted',
      fetchFields({fields}){requests.push(fields);return waiting.promise;}
    };
    event.placePrediction={
      toPlace:()=>place,
      text:{text:options.predictionText||''},
      structuredFormat:{mainText:{text:options.mainText||''}}
    };
    widget.dispatchEvent(event);
    return waiting;
  };
  const input=p=>h.container.querySelector('[name="'+p+'"]');
  return {...h,controller,widget,requests,select,input,close(){controller.dispose();h.dom.window.close();}};
}
test('R6-01: unreconciled second selection never retains the first canonical territory or private coordinates',async()=>{
  const h=await liveWidget();
  const first=h.select();first.resolve();await new Promise(resolve=>setImmediate(resolve));
  assert.equal(h.model.values['location.locality'],'Torvaianica');
  assert.equal(h.model.values['location.exact_lat'],'41.66');
  const second=h.select({components:[ac('country','Italia','IT'),ac('administrative_area_level_1','Lazio','Lazio'),ac('administrative_area_level_2','Roma','RM'),ac('locality','Comune non censito','X')]});
  second.resolve();await new Promise(resolve=>setImmediate(resolve));
  for(const p of ['location.region','location.province','location.municipality','location.locality','location.exact_lat','location.exact_lng'])
    assert.equal(h.input(p).value,'',p+' cannot retain stale values');
  assert(h.container.querySelector('[data-smart-status]').textContent.includes('manualmente'));
  h.close();
});
test('R6-02: user edits during pending Place Details; late response cannot overwrite manual values',async()=>{
  const h=await liveWidget(),pending=h.select();
  h.input('location.region').value='Manuale';
  h.input('location.region').dispatchEvent(new h.win.Event('input',{bubbles:true}));
  pending.resolve();await new Promise(resolve=>setImmediate(resolve));
  assert.equal(h.input('location.region').value,'Manuale');
  assert.equal(h.input('location.exact_lat').value,'');
  assert.equal(h.input('location.exact_address').value,'');
  h.close();
});
test('R6-02: second Google selection wins even when first resolves last',async()=>{
  const h=await liveWidget(),old=h.select({address:'Vecchio indirizzo'});
  const latest=h.select({address:'Nuovo indirizzo'});
  latest.resolve();await new Promise(resolve=>setImmediate(resolve));
  old.resolve();await new Promise(resolve=>setImmediate(resolve));
  assert.equal(h.input('location.exact_address').value,'Nuovo indirizzo');
  h.close();
});
test('R6-03: no Google Place ID requested, stored or sent to the Native model',async()=>{
  const h=await liveWidget(),selection=h.select();
  selection.resolve();await new Promise(resolve=>setImmediate(resolve));
  assert.equal(JSON.stringify(h.requests),JSON.stringify([['addressComponents','formattedAddress','location']]));
  assert.equal(h.model.values['location.place_id'],undefined);
  assert.equal(h.model.values['location.exact_lat'],'41.66');
  assert.equal(h.model.values['location.municipality'],'Pomezia');
  h.close();
});
test('R6-04: manual edit after selected address invalidates private coordinates and stale street when territory changes',async()=>{
  const h=await liveWidget(),p=h.select();p.resolve();await new Promise(resolve=>setImmediate(resolve));
  h.input('location.province').value='Provincia inserita manualmente';
  h.input('location.province').dispatchEvent(new h.win.Event('input',{bubbles:true}));
  assert.equal(h.input('location.exact_lat').value,'');
  assert.equal(h.input('location.exact_lng').value,'');
  assert.equal(h.input('location.exact_address').value,'');
  assert.equal(h.model.values['location.province'],'Provincia inserita manualmente');
  h.close();
});
test('R6-04: foreign address cannot inject coordinates or old territory',async()=>{
  const h=await liveWidget(),first=h.select();first.resolve();await new Promise(resolve=>setImmediate(resolve));
  const out=h.select({components:[ac('country','France','FR')],address:'Paris'});
  out.resolve();await new Promise(resolve=>setImmediate(resolve));
  assert.equal(h.input('location.region').value,'');
  assert.equal(h.input('location.exact_lat').value,'');
  assert.equal(h.input('location.exact_address').value,'');
  h.close();
});
test('R6-04: absent coordinates leave manual address and canonical hierarchy usable',async()=>{
  const h=await liveWidget();
  const wait=deferred();
  const event=new h.win.Event('gmp-select');
  event.placePrediction={toPlace:()=>({
    addressComponents:[ac('country','Italia','IT'),ac('administrative_area_level_1','Lazio','Lazio'),ac('administrative_area_level_2','Roma','RM'),ac('locality','Pomezia','Pomezia')],
    formattedAddress:'Pomezia, Roma', location:null,fetchFields:()=>wait.promise
  })};
  h.widget.dispatchEvent(event);wait.resolve();await new Promise(resolve=>setImmediate(resolve));
  assert.equal(h.input('location.municipality').value,'Pomezia');
  assert.equal(h.input('location.exact_lat').value,'');
  assert.equal(h.input('location.exact_lng').value,'');
  h.close();
});

function loaderHarness(){
  const h=setup(),win=h.win;
  const pending=new Map();let seq=0;
  const realSet=win.setTimeout,realClear=win.clearTimeout;
  win.setTimeout=(fn,ms)=>{assert.equal(ms,15000);const id=++seq;pending.set(id,fn);return id;};
  win.clearTimeout=id=>pending.delete(id);
  const tick=()=>new Promise(resolve=>setImmediate(resolve));
  const activate=()=>h.win.BadAroundNative.SmartAddress.loadPlaces('FAKE_KEY',win.document);
  const script=()=>win.document.querySelector('script[data-ba-smart-address]');
  const scripts=()=>win.document.querySelectorAll('script[data-ba-smart-address]');
  const invokeCallback=el=>{const cb=new URL(el.src).searchParams.get('callback');return {name:cb,invoke:win[cb]};};
  const close=()=>{win.setTimeout=realSet;win.clearTimeout=realClear;h.dom.window.close();};
  return {...h,win,pending,activate,script,scripts,invokeCallback,tick,close};
}
test('R6D timeout removes loader script, clears callback, and permits explicit retry',async()=>{
  const h=loaderHarness();
  const first=h.activate();
  const old=h.script();
  const callback=h.invokeCallback(old);
  assert.equal(h.scripts().length,1);
  assert.equal(h.pending.size,1);
  const expire=[...h.pending.values()][0];
  expire();
  await assert.rejects(first,/places_timeout/);
  assert.equal(h.scripts().length,0);
  assert.equal(h.win[callback.name],undefined);
  const second=h.activate();
  assert.equal(h.scripts().length,1);
  const fresh=h.script(),newCb=h.invokeCallback(fresh);
  assert.notEqual(callback.name,newCb.name);
  h.win.google={maps:{importLibrary:async name=>({library:name})}};
  newCb.invoke();
  assert.deepEqual(await second,{library:'places'});
  assert.equal(h.pending.size,0);
  h.close();
});
test('R6D in-flight callers share one pending loader and never inject duplicate scripts',async()=>{
  const h=loaderHarness();
  const a=h.activate(),b=h.activate(),c=h.activate();
  assert.equal(h.scripts().length,1);
  assert.strictEqual(a,b);
  assert.strictEqual(b,c);
  h.win.google={maps:{importLibrary:async()=>({ready:true})}};
  h.invokeCallback(h.script()).invoke();
  assert.equal((await a).ready,true);
  assert.equal((await b).ready,true);
  assert.equal((await c).ready,true);
  h.close();
});
test('R6D late callback from expired attempt cannot complete or poison new retry',async()=>{
  const h=loaderHarness();
  const old=h.activate(),oldScript=h.script();
  const expired=h.invokeCallback(oldScript);
  [...h.pending.values()][0]();
  await assert.rejects(old,/places_timeout/);
  const retry=h.activate();
  const current=h.invokeCallback(h.script());
  assert.notEqual(expired.name,current.name);
  expired.invoke(); // stale callback reference from an earlier script
  assert.equal(h.pending.size,1);
  assert.equal(h.win[current.name]!==undefined,true);
  h.win.google={maps:{importLibrary:async()=>({ready:'new-attempt'})}};
  current.invoke();
  assert.equal((await retry).ready,'new-attempt');
  h.close();
});
test('R6D network error cleans script and permits a subsequent attempt',async()=>{
  const h=loaderHarness();
  const promise=h.activate(),failed=h.script(),cb=h.invokeCallback(failed);
  failed.onerror();
  await assert.rejects(promise,/places_unavailable/);
  assert.equal(h.scripts().length,0);
  assert.equal(h.win[cb.name],undefined);
  assert.equal(h.pending.size,0);
  const retry=h.activate();
  assert.equal(h.scripts().length,1);
  h.win.google={maps:{importLibrary:async()=>({ok:true})}};
  h.invokeCallback(h.script()).invoke();
  assert.equal((await retry).ok,true);
  h.close();
});
test('R6D existing Google Maps bootstrap is reused without a new script',async()=>{
  const h=loaderHarness();
  h.win.google={maps:{importLibrary:async lib=>({library:lib})}};
  assert.equal((await h.activate()).library,'places');
  assert.equal(h.scripts().length,0);
  assert.equal(h.pending.size,0);
  h.close();
});

test('SF2: no selected address cannot advance and canonical fields are hidden by default',()=>{
  const h=setup(),adapter=h.win.BadAroundNative.SmartAddress.mount(h.container,{smartAddress:{key:'TEST_BROWSER_KEY',territories:territory}},h.model);
  assert.equal(h.container.querySelector('[data-native-location-fields]').hidden,true);
  assert.equal(adapter.canAdvance(),false);
  assert.equal(h.win.document.querySelectorAll('script[data-ba-smart-address]').length,0);
  adapter.dispose();h.dom.window.close();
});
test('SF2: Google failure reveals fallback only on explicit manual action',async()=>{
  const h=setup(),adapter=h.win.BadAroundNative.SmartAddress.mount(h.container,{smartAddress:{key:'TEST_BROWSER_KEY',territories:territory}},h.model);
  h.button.click();h.win.document.querySelector('script[data-ba-smart-address]').onerror();
  await new Promise(resolve=>setImmediate(resolve));
  const fallback=h.container.querySelector('[data-smart-manual]');
  assert.equal(fallback.hidden,false);
  assert.equal(h.container.querySelector('[data-native-location-fields]').hidden,true);
  fallback.click();
  assert.equal(h.container.querySelector('[data-native-location-fields]').hidden,false);
  assert.equal(adapter.canAdvance(),false);
  adapter.dispose();h.dom.window.close();
});
test('SF2: route without house number is stored in private exact address',async()=>{
  const h=await liveWidget();
  const selection=h.select({address:'80132 Napoli NA, Italia',components:[
    ac('country','Italia','IT'),ac('administrative_area_level_1','Campania','Campania'),
    ac('administrative_area_level_2','Napoli','NA'),ac('locality','Napoli','Napoli'),
    ac('route','Piazza del Plebiscito','Piazza del Plebiscito')
  ]});
  selection.resolve();await new Promise(resolve=>setImmediate(resolve));
  // Even an unresolved canonical match must preserve the verified private street.
  assert.equal(h.input('location.exact_address').value,'Piazza del Plebiscito, 80132 Napoli NA, Italia');
  assert.equal(h.controller.canAdvance(),false);
  h.close();
});
test('SF2: selected Google address is invalidated on widget input edit',async()=>{
  const h=await liveWidget(),p=h.select();p.resolve();await new Promise(resolve=>setImmediate(resolve));
  assert.equal(h.controller.canAdvance(),true);
  h.widget.dispatchEvent(new h.win.Event('input',{bubbles:true}));
  assert.equal(h.controller.canAdvance(),false);
  h.close();
});

test('SF2: synthetic canonical mappings for Milano, Napoli and Moricone',()=>{
  const municipalities=[
    {region:'Lombardia',province:'Milano',municipality:'Milano'},
    {region:'Campania',province:'Napoli',municipality:'Napoli'},
    {region:'Lazio',province:'Roma',municipality:'Moricone'}
  ];
  const entries=[];let id=1;
  for(const item of municipalities){
    let parent=0;
    for(const name of [item.region,item.province,item.municipality]){
      let current=entries.find(x=>x.parent===parent&&x.name===name);
      if(!current){current={id:id++,parent,name};entries.push(current);}
      parent=current.id;
    }
  }
  for(const expected of municipalities){
    const google=[
      ac('country','Italia','IT'),
      ac('administrative_area_level_1',expected.region,expected.region),
      ac('administrative_area_level_2',expected.province,expected.province),
      ac('locality',expected.municipality,expected.municipality)
    ];
    assert.deepEqual(resolveTerritory(google,entries).location,{...expected,locality:''});
  }
});
test('SF2: frozen schema untouched and non-geographic required questions moved out of Step 2',()=>{
  const inc=fs.readFileSync(path.join(__dirname,'../wordpress/themes/badaround-child/inc/native-report.php'),'utf8');
  const step=fs.readFileSync(path.join(__dirname,'../wordpress/themes/badaround-child/template-parts/native-report/step.php'),'utf8');
  assert(inc.includes("array( 'location.place_type', 'location.immediate_danger' )"));
  assert(step.includes('data-native-location-fields hidden'));
  assert(step.includes('data-smart-confirmation hidden'));
});

test('SF4: double focus/click while loading uses one Google bootstrap',()=>{
  const h=setup(),adapter=h.win.BadAroundNative.SmartAddress.mount(h.container,{smartAddress:{key:'TEST_BROWSER_KEY',territories:territory}},h.model);
  h.button.dispatchEvent(new h.win.Event('focus'));
  h.button.dispatchEvent(new h.win.Event('click'));
  assert.equal(h.win.document.querySelectorAll('script[data-ba-smart-address]').length,1);
  adapter.dispose();h.dom.window.close();
});
test('SF4: editing an accepted Google suggestion clears stale private address and coordinates',async()=>{
  const h=await liveWidget(),selection=h.select();selection.resolve();
  await new Promise(resolve=>setImmediate(resolve));
  assert.equal(h.controller.canAdvance(),true);
  h.widget.dispatchEvent(new h.win.Event('input',{bubbles:true}));
  assert.equal(h.input('location.exact_address').value,'');
  assert.equal(h.input('location.exact_lat').value,'');
  assert.equal(h.input('location.exact_lng').value,'');
  assert.equal(h.controller.canAdvance(),false);
  h.close();
});
test('SF4: missing Places browser key never loads Google and provides explicit manual entry',()=>{
  const h=setup(),adapter=h.win.BadAroundNative.SmartAddress.mount(h.container,{smartAddress:{territories:territory}},h.model);
  assert.equal(h.win.document.querySelectorAll('script[data-ba-smart-address]').length,0);
  assert.equal(h.container.querySelector('[data-smart-manual]').hidden,false);
  h.container.querySelector('[data-smart-manual]').click();
  assert.equal(h.container.querySelector('[data-native-location-fields]').hidden,false);
  adapter.dispose();h.dom.window.close();
});

test('SF6: verified selected Google suggestion restores Piazza del Plebiscito when Place Details omit route',async()=>{
  const h=await liveWidget();
  const p=h.select({
    address:'80132 Napoli NA, Italia',
    mainText:'Piazza del Plebiscito',
    predictionText:'Piazza del Plebiscito, Napoli, NA, Italia',
    components:[
      ac('country','Italia','IT'),ac('administrative_area_level_1','Lazio','Lazio'),
      ac('administrative_area_level_2','Roma','RM'),ac('locality','Pomezia','Pomezia')
    ]
  });
  p.resolve();await new Promise(resolve=>setImmediate(resolve));
  assert.equal(h.input('location.exact_address').value,'Piazza del Plebiscito, 80132 Napoli NA, Italia');
  assert.equal(h.container.querySelector('[data-smart-address-label]').textContent,'Piazza del Plebiscito, 80132 Napoli NA, Italia');
  h.close();
});
test('SF6: suggestion name is not duplicated when full formatted address already contains it',async()=>{
  const h=await liveWidget();
  const p=h.select({
    address:'Piazza del Plebiscito, Napoli, NA, Italia',
    mainText:'Piazza del Plebiscito'
  });
  p.resolve();await new Promise(resolve=>setImmediate(resolve));
  assert.equal(h.input('location.exact_address').value,'Piazza del Plebiscito, Napoli, NA, Italia');
  h.close();
});
test('SF6: city-only Google selection does not fabricate a street',async()=>{
  const h=await liveWidget();
  const p=h.select({address:'Pomezia, Roma, Italia',mainText:'Pomezia'});
  p.resolve();await new Promise(resolve=>setImmediate(resolve));
  assert.equal(h.input('location.exact_address').value,'Pomezia, Roma, Italia');
  h.close();
});
test('SF6: never reuse the query or accept a postal code as a street name',async()=>{
  const h=await liveWidget();
  const p=h.select({address:'00071 Pomezia RM, Italia',mainText:'00071 Pomezia',predictionText:'00071 Pomezia, Italia'});
  p.resolve();await new Promise(resolve=>setImmediate(resolve));
  assert.equal(h.input('location.exact_address').value,'00071 Pomezia RM, Italia');
  h.close();
});

test('SF7: Rocca di Mezzo AQ canonical registry resolves Google AQ sigla',()=>{
  const registry=[
    {id:5545,parent:0,name:'Abruzzo'},
    {id:5546,parent:5545,name:"L'Aquila"},
    {id:5628,parent:5546,name:'Rocca di Mezzo'}
  ];
  const x=resolveTerritory([
    ac('country','Italia','IT'),
    ac('administrative_area_level_1','Abruzzo','Abruzzo'),
    ac('administrative_area_level_2','Provincia dell’Aquila','AQ'),
    ac('locality','Rocca di Mezzo','Rocca di Mezzo')
  ],registry);
  assert.deepEqual(x.location,{region:'Abruzzo',province:"L'Aquila",municipality:'Rocca di Mezzo',locality:''});
  assert.equal(x.termIds.municipality,5628);
});
test('SF7: AQ province sigla cannot resolve into wrong region or arbitrary municipality',()=>{
  const registry=[
    {id:5545,parent:0,name:'Abruzzo'},
    {id:5546,parent:5545,name:"L'Aquila"},
    {id:5628,parent:5546,name:'Rocca di Mezzo'},
    {id:2,parent:0,name:'Lazio'},
    {id:3,parent:2,name:'Roma'}
  ];
  const x=resolveTerritory([
    ac('country','Italia','IT'),
    ac('administrative_area_level_1','Lazio','Lazio'),
    ac('administrative_area_level_2','Provincia dell’Aquila','AQ'),
    ac('locality','Rocca di Mezzo','Rocca di Mezzo')
  ],registry);
  assert.equal(x.error,'unresolved_province');
});
test('SF7: curly apostrophes and fully named province can resolve without a sigla',()=>{
  const registry=[
    {id:5545,parent:0,name:'Abruzzo'},
    {id:5546,parent:5545,name:"L'Aquila"},
    {id:5628,parent:5546,name:'Rocca di Mezzo'}
  ];
  const x=resolveTerritory([
    ac('country','Italia','IT'),
    ac('administrative_area_level_1','Abruzzo','Abruzzo'),
    ac('administrative_area_level_2','L’Aquila',''),
    ac('administrative_area_level_3','Rocca di Mezzo','')
  ],registry);
  assert.equal(x.location?.municipality,'Rocca di Mezzo');
});
