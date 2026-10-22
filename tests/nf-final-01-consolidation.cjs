'use strict';
const {test}=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),{execFileSync}=require('node:child_process');
const {create}=require('./native-media-dom-harness.cjs');
const contract=JSON.parse(fs.readFileSync('wordpress/plugins/badaround-core/contracts/nf-conditional-v1.0.json'));
const base='a4ef16aa067afc94a96cacd55a46a62d1248ed93';
function seed(h,row,step){h.model.values={...row.minimal_user_values,'media.availability':'no','reporter.social_visibility':'declined'};h.model.pruneInactive();h.model.step=step;h.set('content.description',h.model.values['content.description']);}
test('presentation consolidation preserves exact baseline payload and review keys on all 33 profiles',()=>{
 const old=create({runtimeRef:base}),current=create();
 try{assert.deepEqual(JSON.parse(JSON.stringify(current.root.nativeReport.config)),JSON.parse(JSON.stringify(old.root.nativeReport.config)));
 for(const row of contract.regression_oracle){
  seed(old,row,5);seed(current,row,5);
  const a=JSON.parse(JSON.stringify(old.model.payload())),b=JSON.parse(JSON.stringify(current.model.payload()));delete a.submission_id;delete b.submission_id;assert.deepEqual(b,a,row.subtype);
  const keys=h=>[...h.doc.querySelectorAll('[data-native-review-field]')].map(e=>e.dataset.nativeReviewField);
  assert.deepEqual(keys(current),keys(old),row.subtype+' summary keys');
 }
 }finally{old.close();current.close();}
 const count=contract.matrix.reduce((n,row)=>n+Object.values(row.fields).filter(f=>f.state==='CONDITIONAL').length,0);assert.equal(count,554);
});
test('single field errors focus the real control, clear on input and cannot leak into inactive fields',()=>{
 const h=create();try{
  seed(h,contract.regression_oracle.find(r=>r.subtype==='civic_waste'),2);h.set('content.description','');h.click('next');
  const input=h.doc.querySelector('[name="content.description"]');assert.equal(h.doc.activeElement,input);assert.equal(input.getAttribute('aria-invalid'),'true');assert(input.getAttribute('aria-describedby').includes(h.doc.querySelector('[data-native-field="content.description"] .ba-native-error').id));
  assert(h.doc.querySelector('[data-native-errors]').hidden,'single error needs no duplicate generic summary');
  h.set('content.description','Descrizione sintetica utile per il collaudo.');assert.equal(input.getAttribute('aria-invalid'),'false');
  h.model.set('event.category','animal');h.model.set('event.subtype','animal_missing');h.set('content.description','Descrizione sintetica utile per il collaudo.');
  assert(![...h.doc.querySelectorAll('[data-native-field][hidden] [aria-invalid="true"]')].length);
 }finally{h.close();}
});
test('choice confirmation is painted before auto-next; edit and arrow navigation retain alternatives',async()=>{
 const h=create();try{
  const first=h.doc.querySelector('.ba-native-category-grid input[value="animal"]');first.click();
  assert(first.checked);assert(!first.closest('fieldset').classList.contains('is-collapsed'));
  await new Promise(r=>setTimeout(r,200));assert(first.closest('fieldset').classList.contains('is-collapsed'));
  assert.equal(h.doc.activeElement.name,'ba-card-event.subtype');
  h.doc.querySelector('[data-native-field="event.category"] .ba-native-edit-choice').click();assert.equal(h.doc.querySelectorAll('.ba-native-category-grid label:not([hidden])').length,6);
  const group=first.closest('fieldset');group.dispatchEvent(new h.w.KeyboardEvent('keydown',{key:'ArrowRight',bubbles:true}));first.click();await new Promise(r=>setTimeout(r,200));assert(!group.classList.contains('is-collapsed'));
 }finally{h.close();}
});
test('topic labels never reveal irrelevant blocks, reporter choices remain explicit and progress is announced',()=>{
 const h=create();try{seed(h,contract.regression_oracle.find(r=>r.subtype==='civic_waste'),2);
  for(const t of h.doc.querySelectorAll('[data-native-topic]'))if(!t.hidden){const p=t.dataset.nativeTopic;assert([...h.doc.querySelectorAll('[data-native-field]')].some(w=>w.dataset.nativeField.startsWith(p)&&!w.hidden&&!w.closest('[hidden]')));}
  assert(h.doc.querySelector('[data-native-position]').textContent.startsWith('Passaggio 3 di 6.'));
  assert.equal(h.doc.querySelectorAll('[aria-current="step"]').length,1);
  h.click('new');assert.equal(h.model.values['reward.status'],undefined);assert.equal(h.model.values['reporter.public_identity_mode'],undefined);
 }finally{h.close();}
});
