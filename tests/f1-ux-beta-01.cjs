'use strict';
const {test}=require('node:test'),assert=require('node:assert/strict');
const {create,tick}=require('./native-media-dom-harness.cjs');
const field=(h,p)=>h.doc.querySelector(`[data-native-field="${p}"]`);
const refresh=h=>h.set('reporter.email',h.model.values['reporter.email']||'');
function at(h,step){h.model.step=step;refresh(h);}
function visibleNumbers(h){return [...h.doc.querySelectorAll('[data-native-step]:not([hidden]) [data-native-question-number]')].filter(el=>!el.closest('[hidden]')).map(el=>Number(el.textContent.replace('.','')));}
function sequential(h){const n=visibleNumbers(h);assert(n.length);assert.deepEqual(n,n.map((_,i)=>n[0]+i));}
test('BETA01: reference removed only from presentation, identity comes first, exact-location privacy is immediate',()=>{
 const h=create();assert(!h.root.nativeReport.config.fields['authority.reference']);assert(!h.doc.querySelector('[name="authority.reference"]'));
 const contact=h.doc.querySelector('[data-native-step="contact"]');assert.equal(contact.querySelector('[data-native-field]').dataset.nativeField,'reporter.public_identity_mode');
 assert.equal(h.root.nativeReport.config.steps.contact,'Informazioni di contatto');assert.match(h.doc.querySelector('#ba-smart-privacy').textContent,/numero civico restano riservati/);
 assert.match(h.doc.querySelector('.ba-native-notice').textContent,/non saranno pubblicati automaticamente/);h.close();
});
test('BETA01: questions disclose progressively without required hidden inputs; numbering has no gaps',()=>{
 const h=create();sequential(h);h.set('event.category','vehicle');sequential(h);h.set('event.subtype','vehicle_stolen');sequential(h);
 h.fill();for(let i=0;i<7;i++){at(h,i);if(i!==6)sequential(h);
  for(const input of h.doc.querySelectorAll('input[required],select[required],textarea[required]'))assert(!input.closest('[hidden]'),input.name||input.id);
 }
 at(h,3);h.set('damage.status','yes');assert(!field(h,'damage.description').hidden);sequential(h);h.set('damage.status','no');assert(field(h,'damage.description').hidden);sequential(h);
 h.set('reward.status','none');assert(field(h,'reward.amount').hidden);assert(field(h,'reward.conditions').hidden);
 at(h,4);h.set('media.availability','no');assert(h.doc.querySelector('[data-native-media]').hidden);h.close();
});
test('BETA01: 112 is an explicit UI choice classified as existing public_service; no police inference or extra force required',()=>{
 const h=create();h.fill();at(h,3);const choice=h.doc.querySelector('[data-beta-authority]');
 choice.value='yes:public_service';choice.dispatchEvent(new h.w.Event('change',{bubbles:true}));
 assert.equal(h.model.payload().authority.status,'yes');assert.equal(h.model.payload().authority.type,'public_service');assert(field(h,'authority.type').hidden);assert(!field(h,'authority.type').querySelector('select').required);
 choice.value='no';choice.dispatchEvent(new h.w.Event('change',{bubbles:true}));assert.equal(h.model.payload().authority.type,undefined);assert(!h.model.validate().some(e=>e.field.startsWith('authority.')));h.close();
});
test('BETA01: only two declarations are bundled; privacy, terms, rules and contact choices stay independent',()=>{
 const h=create();h.fill();at(h,5);
 for(const p of ['consents.truthfulness','consents.media_rights','consents.publication_rules','consents.terms','consents.privacy'])h.set(p,false);
 const bundle=h.doc.querySelector('[data-beta-confirm]');assert(!bundle.checked);bundle.click();
 assert.equal(h.model.effective('consents.truthfulness'),true);assert.equal(h.model.effective('consents.media_rights'),true);
 for(const p of ['consents.publication_rules','consents.terms','consents.privacy'])assert.equal(h.model.effective(p),false,p);
 assert.equal(h.model.effective('reporter.contact_preference'),'none');assert(h.model.validate().some(e=>e.field==='consents.privacy'));h.close();
});
test('BETA01: anonymous and pseudonym flows preserve private fields, branch answers and canonical submission identity',async()=>{
 const h=create();h.fill();const id=h.model.submissionId;at(h,5);
 h.set('reporter.public_identity_mode','pseudonym');assert(!field(h,'reporter.pseudonym').hidden);h.set('reporter.pseudonym','Sentinella QA');
 assert.equal(h.model.payload().reporter.pseudonym,'Sentinella QA');h.set('reporter.public_identity_mode','anonymous');assert(field(h,'reporter.pseudonym').hidden);assert.equal(h.model.payload().reporter.pseudonym,undefined);
 h.set('reporter.public_identity_mode','pseudonym');assert.equal(h.model.effective('reporter.pseudonym'),'Sentinella QA');assert.equal(h.model.submissionId,id);
 h.set('reporter.public_identity_mode','anonymous');h.goReview();h.submit();h.submit();await tick();await tick();assert.equal(h.requests.length,1);assert(h.model.completed);assert.equal(JSON.parse(h.requests[0].request.body).submission_id,id);h.close();
});
