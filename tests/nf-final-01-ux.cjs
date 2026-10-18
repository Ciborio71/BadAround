'use strict';
const {test}=require('node:test');
const assert=require('node:assert/strict');
const {create}=require('./native-media-dom-harness.cjs');
test('NF-FINAL: all seven steps expose progress and focus, preserve payload and hide technical review fields',()=>{
  const h=create();h.fill();
  const before=JSON.stringify(h.model.payload());
  for(let i=0;i<7;i++){
    assert.equal(h.model.step,i);
    assert(h.doc.querySelector('[data-native-position]').textContent.includes(`Sei in: ${h.root.nativeReport.config.steps[Object.keys(h.root.nativeReport.config.steps)[i]]}`));
    assert.equal(h.doc.querySelectorAll('[data-native-step]:not([hidden])').length,1);
    assert.equal(h.doc.querySelectorAll('[aria-current="step"]').length,1);
    if(i>0)assert.equal(h.doc.activeElement,h.doc.querySelector('[data-native-step]:not([hidden]) h2'));
    if(i<6)h.click('next');
  }
  const labels=[...h.doc.querySelectorAll('[data-native-review] dt')].map(n=>n.textContent);
  for(const path of ['location.region','location.province','location.municipality','location.locality','location.exact_lat','location.exact_lng'])assert(!labels.includes(h.root.nativeReport.config.fields[path].label));
  assert(labels.includes('Indirizzo preciso, compreso il civico'));
  assert.deepEqual([...h.doc.querySelectorAll('[data-native-review] h3')].map(n=>n.textContent),['Cosa è successo','Dove','Quando','Dettagli','Immagini','Informazioni di contatto']);
  assert.equal(JSON.stringify(h.model.payload()),before);
  h.close();
});
test('NF-FINAL: select change clears stale error and checkbox required indication is associated',()=>{
  const h=create();h.click('next');
  assert(!h.doc.querySelector('[data-native-errors]').hidden);
  h.set('event.category','hazard','change');
  assert(h.doc.querySelector('[data-native-errors]').hidden);
  h.fill();h.goReview();h.click('back');
  const box=h.doc.querySelector('[data-beta-confirm]');
  assert(box.closest('label').textContent.includes('obbligatorio'));
  assert(box.getAttribute('aria-describedby').includes(`${box.id}-error`));
  box.focus();assert.equal(h.doc.activeElement,box);
  h.close();
});
test('NF-FINAL: configured minimal address UI cannot reveal manual technical fields',()=>{
  const h=create();
  h.w.eval(require('node:fs').readFileSync(require('node:path').join(__dirname,'../wordpress/themes/badaround-child/assets/js/native-report/smart-address.js'),'utf8'));
  const config=h.root.nativeReport.config;
  assert.equal(config.locationManualEntry,false);
  config.smartAddress=null;
  const address=h.w.BadAroundNative.SmartAddress.mount(h.root,config,h.model);
  const manual=h.doc.querySelector('[data-smart-manual]');
  assert(manual.hidden);manual.click();
  assert(h.doc.querySelector('[data-native-location-fields]').hidden);
  assert.equal(address.canAdvance(),false);
  address.reset();assert(manual.hidden);assert(h.doc.querySelector('[data-native-location-fields]').hidden);
  address.dispose();h.close();
});
