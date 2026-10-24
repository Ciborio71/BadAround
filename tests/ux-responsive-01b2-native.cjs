'use strict';
const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const {JSDOM}=require('jsdom');
const smartSource=fs.readFileSync(path.join(__dirname,'../wordpress/themes/badaround-child/assets/js/native-report/smart-address.js'),'utf8');
const wizardSource=fs.readFileSync(path.join(__dirname,'../wordpress/themes/badaround-child/assets/js/native-report/wizard.js'),'utf8');

test('Smart Address reset removes provider widget and restores a clean explicit start control',async()=>{
  const dom=new JSDOM('<!doctype html><html><head></head><body><section data-native-report><div data-native-smart-address><input data-smart-start type="text" readonly><div data-smart-widget></div><p data-smart-status></p><div data-smart-confirmation hidden><p data-smart-address-label></p></div><button data-smart-manual hidden type="button">Manuale</button></div><div data-native-location-fields hidden><input data-native-input name="location.region"><input data-native-input name="location.province"><input data-native-input name="location.municipality"><input data-native-input name="location.locality"><input data-native-input name="location.exact_address"><input data-native-input name="location.exact_lat"><input data-native-input name="location.exact_lng"></div></section></body></html>',{url:'https://staging.badaround.it/segnala-un-evento/',runScripts:'outside-only'});
  const w=dom.window;w.eval(smartSource);
  class FakeWidget extends w.HTMLElement{};w.customElements.define('ux01b2-widget',FakeWidget);
  w.google={maps:{importLibrary:async()=>({PlaceAutocompleteElement:class{constructor(){return w.document.createElement('ux01b2-widget')}}})}};
  const container=w.document.querySelector('[data-native-report]');
  const model={set(){}};
  const controller=w.BadAroundNative.SmartAddress.mount(container,{smartAddress:{key:'K',territories:[]},locationManualEntry:true},model);
  const start=container.querySelector('[data-smart-start]');
  start.dispatchEvent(new w.Event('focus'));
  await new Promise(resolve=>setImmediate(resolve));
  assert(container.querySelector('ux01b2-widget'));
  assert.equal(start.hidden,true);
  controller.reset();
  assert.equal(container.querySelector('[data-smart-widget]').children.length,0);
  assert.equal(start.hidden,false);
  assert.equal(start.disabled,false);
  assert.equal(start.value,'');
  assert.match(container.querySelector('[data-smart-status]').textContent,/Seleziona il campo/);
  controller.dispose();dom.window.close();
});

test('wizard review exposes direct edit actions without changing canonical field values',()=>{
  assert(wizardSource.includes("edit.className = 'ba-native-review-edit'"));
  assert(wizardSource.includes("model.step=index;ns.presentErrors(container,[],config);render(true)"));
  assert(wizardSource.includes("model.step=steps.indexOf('location');ns.presentErrors(container,[],config);render(true)"));
});

test('presentation error filtering ignores progressively hidden questions while canonical validation remains untouched',()=>{
  assert(wizardSource.includes("!wrapper.hidden && !wrapper.closest('[hidden]')"));
  assert(wizardSource.includes('const errors = model.validate(steps[model.step])'));
  assert(wizardSource.includes('const errors = model.validate();'));
});
