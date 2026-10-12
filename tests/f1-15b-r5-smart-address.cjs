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
  // Full name does not equal canonical Roma: no heuristic based on postal address.
  assert.equal(x.error,'unresolved_province');
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
  <div data-native-smart-address><button data-smart-start type="button">Cerca</button><div data-smart-widget></div><p data-smart-status></p></div>
  <input name="location.region"><input name="location.province"><input name="location.municipality"><input name="location.locality">
  <input name="location.exact_address"><input name="location.exact_lat"><input name="location.exact_lng">
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
  h.win.google={maps:{importLibrary:async library=>{assert.equal(library,'places');return {PlaceAutocompleteElement:class extends Widget {constructor(){super();return h.win.document.createElement('fake-place-widget');}}};},Map:class {constructor(){mapCreated++;}}}};
  h.win.__badaroundSmartPlacesReady();
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
  assert(h.container.querySelector('[data-smart-status]').textContent.includes('campi manualmente'));
  assert.equal(h.container.querySelector('[name="location.region"]').disabled,false);
  controller.dispose();h.dom.window.close();
});
test('frozen adapters and report schema are not touched by the smart address module',()=>{
  assert(!source.includes('google.maps.Map('));
  assert(!source.includes('register_term'));
  assert(!source.includes('console.log'));
  assert(source.includes("importLibrary('places')"));
});
