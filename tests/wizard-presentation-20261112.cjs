'use strict';
const {test}=require('node:test'),assert=require('node:assert/strict');
const {create,tick}=require('./native-media-dom-harness.cjs');
test('relationship found option follows event context and clears incompatible answer',()=>{
 const h=create();try{
 h.set('event.category','vehicle');h.set('event.subtype','vehicle_stolen');
 const select=()=>h.doc.querySelector('[name="reporter.relationship"]');
 assert(![...select().options].some(o=>o.value==='found_subject'));
 h.set('event.subtype','vehicle_observed');assert.match([...select().options].find(o=>o.value==='found_subject').textContent,/veicolo/);
 h.set('reporter.relationship','found_subject');h.set('event.subtype','vehicle_stolen');assert.equal(h.model.effective('reporter.relationship'),undefined);
 h.set('event.category','animal');h.set('event.subtype','animal_sighted_or_found');assert.match([...select().options].find(o=>o.value==='found_subject').textContent,/animale/);
 }finally{h.close();}
});
test('anonymous choice explains private names and social sharing remains unselected',async()=>{
 const h=create();try{
 assert.equal(h.model.effective('reporter.contact_preference'),'community');assert.equal(h.model.effective('reporter.social_visibility'),'declined');
 h.fill();h.set('reporter.public_identity_mode','anonymous');const note=h.doc.querySelector('[data-native-anonymous-note]');assert.equal(note.hidden,false);assert.match(note.textContent,/non saranno pubblicati/i);
 h.set('reporter.public_identity_mode','pseudonym');assert.equal(note.hidden,false);assert.match(note.textContent,/pseudonimo/);
 h.set('reporter.first_name','Marco');h.set('reporter.last_name','Rossi');h.set('reporter.public_identity_mode','name_initial');assert.match(note.textContent,/Marco R\./);assert(!note.textContent.includes('Rossi'));
 const select=h.doc.querySelector('[name="reporter.public_identity_mode"]');assert.equal(select.hidden,true);const group=select.parentElement.querySelector('.ba-native-card-grid');assert(group);
 group.querySelector('input[value="name_initial"]').click();await new Promise(resolve=>setTimeout(resolve,10));
 assert.equal(group.classList.contains('is-collapsed'),false);assert.equal([...group.querySelectorAll('label')].filter(el=>!el.hidden).length,4);assert.equal(select.parentElement.querySelector('.ba-native-edit-choice').hidden,true);
 assert.deepEqual([...select.options].map(o=>o.value),['','name_initial','first_name','pseudonym','anonymous']);
 assert(select.compareDocumentPosition(h.doc.querySelector('[data-native-field="reporter.first_name"]'))&h.w.Node.DOCUMENT_POSITION_FOLLOWING);
 assert.equal(h.root.nativeReport.config.fields['reporter.public_identity_mode'].label,'Come vuoi comparire nella segnalazione?');
 assert.equal(h.doc.querySelectorAll('.ba-native-engagement__intro').length,2);
 }finally{h.close();}
});
test('submission feedback persists while request is pending and prevents duplicates',async()=>{
 let finish;const h=create({fetch:async(url,request,body)=>url.endsWith('/reports')?await new Promise(resolve=>{finish=()=>resolve({ok:true,status:201,json:async()=>({status:'success',schema_version:body.schema_version,submission_id:body.submission_id,next_state:'moderation_pending'})});}):null});try{
 h.fill();h.goReview();h.submit();await tick();const overlay=h.doc.querySelector('[data-native-submit-feedback]');assert.equal(overlay.hidden,false);assert.match(overlay.textContent,/Invio/);assert.equal(h.doc.activeElement.className,'ba-native-submit-panel');h.submit();await tick();assert.equal(h.requests.filter(r=>r.url.endsWith('/reports')).length,1);finish();await tick();await tick();assert.equal(overlay.hidden,true);assert.equal(h.model.completed,true);
 }finally{h.close();}
});

test('network choices default to community for new eligible reports and retain an explicit alternative',()=>{
 const h=create();try{
 h.set('event.category','hazard');h.set('event.subtype','hazard_road_obstruction');
 assert.equal(h.model.effective('reporter.contact_preference'),'community');assert.equal(h.model.effective('reporter.social_visibility'),'declined');
 const select=h.doc.querySelector('[name="reporter.contact_preference"]'),wrapper=select.parentElement,group=wrapper.querySelector('.ba-native-card-grid');
 assert.equal(group.querySelectorAll('input[type="radio"]').length,2);assert.equal(group.querySelector('input:checked').value,'community');assert(wrapper.querySelector('.ba-native-recommended'));
 h.set('reporter.contact_preference','badaround_only');h.set('reporter.first_name','Marco');assert.equal(h.model.effective('reporter.contact_preference'),'badaround_only');assert.equal(group.classList.contains('is-collapsed'),false);
 h.set('reporter.contact_preference','none');assert.equal(h.model.effective('reporter.contact_preference'),'none','legacy canonical preference remains intact');assert.equal(group.querySelectorAll('input[type="radio"]').length,2);
 h.model.reset();h.set('event.category','hazard');h.set('event.subtype','hazard_road_obstruction');assert.equal(h.model.effective('reporter.contact_preference'),'community');
 }finally{h.close();}
});

test('possible images explain later upload and the note clears for other choices',()=>{
 const h=create();try{
 h.set('event.category','vehicle');h.set('event.subtype','vehicle_stolen');
 const note=h.doc.querySelector('[data-native-media-later]');assert(note.hidden);
 h.set('media.availability','possible');assert.equal(note.hidden,false);assert.match(note.textContent,/aggiungere le immagini in un secondo momento/);
 const input=h.doc.querySelector('[name="media.availability"]');assert(input.getAttribute('aria-describedby').includes(note.id));
 h.set('media.availability','yes');assert(note.hidden);h.set('media.availability','no');assert(note.hidden);
 }finally{h.close();}
});

test('notification card clicks keep focus and never auto-scroll to Continue',async()=>{
 const h=create();try{
 h.fill();h.model.step=4;h.set('reporter.contact_preference','community');
 let scrolls=0;h.w.scrollTo=()=>{scrolls++;};
 const wrapper=h.doc.querySelector('[data-native-field="reporter.contact_preference"]');
 for(const value of ['badaround_only','community']){
  const radio=wrapper.querySelector('input[type="radio"][value="'+value+'"]');radio.focus();radio.click();
  await new Promise(resolve=>setTimeout(resolve,10));
  assert.equal(h.model.effective('reporter.contact_preference'),value);assert.equal(h.doc.activeElement,radio);assert.equal(scrolls,0);
 }
 }finally{h.close();}
});

test('last entry step offers direct submission and opt-in review without changing payload',async()=>{
 const h=create();try{
 h.fill();const steps=Object.keys(h.root.nativeReport.config.steps),last=steps.filter(key=>key!=='review').at(-1);
 while(steps[h.model.step]!==last)h.click('next');
 assert.equal(h.doc.querySelector('[data-native-next]').hidden,true);
 const submit=h.doc.querySelector('[data-native-submit]'),preview=h.doc.querySelector('[data-native-review-toggle]');
 assert.equal(submit.hidden,false);assert.equal(preview.hidden,false);assert.equal(preview.textContent,'Vedi riepilogo');
 assert.equal(h.doc.querySelector('[data-native-step="review"]').hidden,true);
 assert.equal([...h.doc.querySelectorAll('[data-native-progress]')].filter(li=>!li.hidden).length,5);
 const payload=JSON.stringify(h.model.payload());preview.click();
 assert.equal(steps[h.model.step],'review');assert.equal(h.doc.querySelector('[data-native-step="review"]').hidden,false);
 assert(h.doc.querySelector('[data-native-review] dd'));assert.equal(h.requests.length,0);assert.equal(JSON.stringify(h.model.payload()),payload);
 h.click('back');assert.equal(steps[h.model.step],last);assert.equal(submit.hidden,false);
 h.submit();await tick();await tick();assert.equal(h.model.completed,true);assert.equal(h.requests.filter(r=>r.url.endsWith('/reports')).length,1);
 }finally{h.close();}
});

test('direct submit validates earlier hidden fields before any access or network call',async()=>{
 const h=create();try{
 h.fill();h.model.step=4;h.set('reporter.email','qa@example.invalid');
 h.set('content.description','');h.submit();await tick();
 assert.equal(h.requests.filter(r=>r.url.endsWith('/reports')).length,0);assert.equal(h.model.completed,false);
 assert.equal(h.model.step,Object.keys(h.root.nativeReport.config.steps).indexOf('details'));
 assert.equal(h.doc.querySelector('[data-native-errors]').hidden,false);
 }finally{h.close();}
});

test('direct submission preserves the immutable retry snapshot and disables optional review while pending',async()=>{
 let finish;const h=create({fetch:async(url,request,body)=>url.endsWith('/reports')?await new Promise(resolve=>{finish=()=>resolve({ok:true,status:201,json:async()=>({status:'success',schema_version:body.schema_version,submission_id:body.submission_id,next_state:'moderation_pending'})});}):null});try{
 h.fill();h.model.step=4;h.set('reporter.email','qa@example.invalid');h.submit();await tick();
 const preview=h.doc.querySelector('[data-native-review-toggle]');assert(preview.disabled);preview.click();assert.equal(h.model.step,4);
 h.submit();await tick();assert.equal(h.requests.filter(r=>r.url.endsWith('/reports')).length,1);
 finish();await tick();await tick();assert(h.model.completed);
 }finally{h.close();}
});
