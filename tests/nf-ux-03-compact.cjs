'use strict';
const {test}=require('node:test'),assert=require('node:assert/strict');
const {create,tick}=require('./native-media-dom-harness.cjs');
const wrapper=(h,path)=>h.doc.querySelector(`[data-native-field="${path}"]`);
const radio=(h,path,value)=>[...wrapper(h,path).querySelectorAll('.ba-native-card-grid input')].find(r=>r.value===value);
test('single choice compacts to visible selected radio and editable summary, without changing step',async()=>{
 const h=create();const selected=radio(h,'event.category','vehicle');selected.focus();selected.click();await new Promise(r=>setTimeout(r,10));
 const w=wrapper(h,'event.category'),group=w.querySelector('fieldset'),edit=w.querySelector('.ba-native-edit-choice');
 assert(group.classList.contains('is-collapsed'));assert(!selected.closest('label').hidden);assert.equal(h.doc.activeElement.closest('[data-native-field]').dataset.nativeField,'event.subtype');
 assert.equal(group.querySelectorAll('label:not([hidden])').length,1);assert(!edit.hidden);assert.equal(edit.getAttribute('aria-expanded'),'false');assert.equal(h.model.step,0);
 const before=JSON.stringify(h.model.payload());edit.click();
 assert(!group.classList.contains('is-collapsed'));assert.equal(group.querySelectorAll('label:not([hidden])').length,6);assert.equal(h.doc.activeElement,selected);
 assert.equal(JSON.stringify(h.model.payload()),before);h.close();
});
test('arrow navigation keeps alternatives open until explicit confirmation',async()=>{
 const h=create();const r=radio(h,'event.category','vehicle'),g=r.closest('fieldset');r.focus();r.dispatchEvent(new h.w.KeyboardEvent('keydown',{key:'ArrowRight',bubbles:true}));r.click();await new Promise(r=>setTimeout(r,10));
 assert(!g.classList.contains('is-collapsed'));r.dispatchEvent(new h.w.KeyboardEvent('keyup',{key:'ArrowRight',bubbles:true}));
 h.doc.querySelector('[data-native-next]').focus();await new Promise(r=>setTimeout(r,10));assert(!g.classList.contains('is-collapsed'));r.click();await new Promise(r=>setTimeout(r,10));assert(g.classList.contains('is-collapsed'));h.close();
});
test('changing parent invalidates old compact subtype and preserves original canonical validator',async()=>{
 const h=create();radio(h,'event.category','vehicle').click();radio(h,'event.subtype','vehicle_stolen').click();await new Promise(r=>setTimeout(r,10));
 assert(wrapper(h,'event.subtype').querySelector('.is-collapsed'));
 wrapper(h,'event.category').querySelector('.ba-native-edit-choice').click();radio(h,'event.category','animal').click();await new Promise(r=>setTimeout(r,10));
 assert.equal(h.model.effective('event.subtype'),undefined);assert(!wrapper(h,'event.subtype').querySelector('.is-collapsed'));h.click('next');assert.equal(h.model.step,0);h.close();
});
test('paired fields preserve semantic order, descriptions and payload',()=>{
 const h=create();h.fill();const before=JSON.stringify(h.model.payload());
 for(const names of [['reporter.email','reporter.phone'],['reporter.first_name','reporter.last_name'],['vehicle.make','vehicle.model'],['time.range_start','time.range_end']]){
  const row=wrapper(h,names[0]).parentElement;assert(row.classList.contains('ba-native-field-pair'));assert.deepEqual([...row.children].map(n=>n.dataset.nativeField),names);
 }
 assert.equal(JSON.stringify(h.model.payload()),before);assert(!h.doc.querySelector('[data-native-location-fields] .ba-native-field-pair'));h.close();
});
test('submission lock disables editing and legal confirmations never collapse',async()=>{
 const h=create();h.fill();h.doc.querySelector('.ba-native-category-grid input:checked').click();await new Promise(r=>setTimeout(r,10));
 const edit=wrapper(h,'event.category').querySelector('.ba-native-edit-choice');assert(!edit.hidden);
 h.model.snapshot=h.model.payload();h.set('reporter.email','changed@example.invalid');assert(edit.disabled);
 assert(!wrapper(h,'consents.privacy').querySelector('.ba-native-edit-choice'));
 assert(!wrapper(h,'consents.privacy').querySelector('.is-collapsed'));h.close();
});

test('theft damage wording clarifies additional damage without hiding required canonical field',()=>{
 const h=create();h.set('event.category','vehicle');h.set('event.subtype','vehicle_stolen');
 const w=wrapper(h,'damage.status');assert.match(w.querySelector('label').textContent,/danni aggiuntivi/);assert.match(w.querySelector('.ba-native-help').textContent,/Da verificare/);assert(!w.hidden);assert(w.querySelector('select').required);
 h.set('event.category','hazard');h.set('event.subtype','hazard_road_obstruction');assert.match(w.querySelector('label').textContent,/Sono presenti danni/);assert(!w.querySelector('.ba-native-help').textContent.includes('oltre al furto'));h.close();
});
