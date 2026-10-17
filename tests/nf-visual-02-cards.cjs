'use strict';
const {test}=require('node:test'),assert=require('node:assert/strict');
const {create}=require('./native-media-dom-harness.cjs');
function choose(h,path,value){
 const radio=[...h.doc.querySelectorAll(`[data-native-field="${path}"] .ba-native-card-grid input`)].find(r=>r.value===value);
 assert(radio && !radio.disabled);radio.click();return radio;
}
test('six category cards use canonical options and disclose subtype then relationship',()=>{
 const h=create(),category=h.doc.querySelector('[name="event.category"]');
 assert(category.hidden);
 assert.equal(h.doc.querySelectorAll('.ba-native-category-grid input').length,6);
 assert(h.doc.querySelector('[data-native-field="event.subtype"]').hidden);
 choose(h,'event.category','vehicle');
 assert.equal(h.model.values['event.category'],'vehicle');
 assert(!h.doc.querySelector('[data-native-field="event.subtype"]').hidden);
 assert(h.doc.querySelector('[data-native-field="reporter.relationship"]').hidden);
 choose(h,'event.subtype','vehicle_stolen');
 assert(!h.doc.querySelector('[data-native-field="reporter.relationship"]').hidden);
 choose(h,'reporter.relationship','directly_involved');
 h.click('next');assert.equal(h.model.step,1);
 h.click('back');assert.equal(h.doc.querySelector('.ba-native-category-grid input:checked').value,'vehicle');
 h.close();
});
test('card changes invalidate incompatible subtype and errors focus visible controls',()=>{
 const h=create();
 choose(h,'event.category','vehicle');choose(h,'event.subtype','vehicle_stolen');
 choose(h,'event.category','animal');
 assert.equal(h.model.effective('event.subtype'),undefined);
 assert([...h.doc.querySelectorAll('[data-native-field="event.subtype"] .ba-native-card-grid input')].every(r=>r.value.startsWith('animal_')));
 h.click('next');
 const summary=h.doc.querySelector('[data-native-errors]');assert(!summary.hidden);
 summary.querySelector('button').click();
 assert(h.doc.activeElement.closest('.ba-native-card-grid'));
 assert.equal(h.doc.activeElement.getAttribute('aria-invalid'),'true');
 h.close();
});
test('presentation cards preserve exactly the payload produced by canonical controls',()=>{
 const h=create();h.fill();const payload=JSON.stringify(h.model.payload());
 choose(h,'event.category','hazard');choose(h,'event.subtype','hazard_road_obstruction');
 choose(h,'time.mode','exact');choose(h,'time.knowledge','unknown');
 assert.equal(JSON.stringify(h.model.payload()),payload);
 h.goReview();h.model.busy=true;h.set('reporter.email','qa@example.invalid');
 assert([...h.doc.querySelectorAll('.ba-native-card-grid input')].every(r=>r.disabled));
 h.close();
});

test('change-only assistive interaction updates the canonical category',()=>{
 const h=create();
 const radio=[...h.doc.querySelectorAll('.ba-native-category-grid input')].find(r=>r.value==='animal');
 radio.checked=true;radio.dispatchEvent(new h.w.Event('change',{bubbles:true}));
 assert.equal(h.model.values['event.category'],'animal');
 assert.equal(h.doc.querySelector('[name="event.category"]').value,'animal');
 h.close();
});


test('home and Native category icons share all six shapes and preserve canonical values',()=>{
 const fs=require('node:fs'),path=require('node:path');
 const home=fs.readFileSync(path.join(__dirname,'../wordpress/themes/badaround-child/front-page.php'),'utf8');
 const start=home.indexOf("<?php if ( 'car'");const block=home.slice(start,home.indexOf('<?php endif; ?>',start));
 const homeIcons=[...block.matchAll(/<svg[^>]*>(.*?)<\/svg>/g)].map(m=>m[1]);assert.equal(homeIcons.length,6);
 const h=create();const keys=['vehicle','property','hazard','public_space','animal','item_document'];
 const colors=['#1677E8','#C9153D','#F57C00','#0AA184','#7040C8','#E3A415'];
 keys.forEach((key,i)=>{
  const radio=h.doc.querySelector(`.ba-native-category-grid input[value="${key}"]`),card=radio.closest('label'),svg=card.querySelector('svg');
  const expected=[...homeIcons[i].matchAll(/d="([^"]+)"/g)].map(m=>m[1]);
  assert.deepEqual([...svg.querySelectorAll('path')].map(p=>p.getAttribute('d')),expected,key);
  assert.equal(svg.getAttribute('fill'),key==='animal'?'currentColor':'none');assert.equal(svg.getAttribute('stroke'),key==='animal'?'none':'currentColor');
  assert.equal(card.style.getPropertyValue('--ba-card-accent'),colors[i]);assert.equal(radio.value,key);
 });h.close();
});
