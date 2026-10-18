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
 const h=create();assert.match(h.doc.querySelector('.ba-native-category-grid').textContent,/Furti, tentati furti/);sequential(h);h.set('event.category','vehicle');sequential(h);h.set('event.subtype','vehicle_stolen');sequential(h);
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
test('BETA02: one explicit checkbox records all mandatory acknowledgements, never optional contact preferences',()=>{
 const h=create();h.fill();at(h,5);
 const paths=['consents.truthfulness','consents.media_rights','consents.publication_rules','consents.terms','consents.privacy'];
 for(const p of paths)h.set(p,false);
 const bundle=h.doc.querySelector('[data-beta-confirm]');assert(!bundle.checked);assert(!bundle.closest('label').querySelector('[data-native-question-number]'));assert.equal(h.doc.querySelector('#ba-native-declarations-detail').parentElement,bundle.closest('[data-beta-declarations]'));
 const copy=bundle.closest('[data-beta-declarations]').textContent;for(const text of ['buona fede','diritto di condividere','regole di pubblicazione','termini e le condizioni','informativa privacy'])assert(copy.includes(text),text);
 assert.equal(h.doc.querySelectorAll('.ba-native-final-statements input[type=checkbox]:not(:disabled)').length,1);
 h.click('next');assert.equal(h.doc.querySelectorAll('[data-native-errors] li').length,1);h.doc.querySelector('[data-native-errors] button').click();assert.equal(h.doc.activeElement,bundle);assert.equal(bundle.getAttribute('aria-invalid'),'true');
 bundle.click();assert(h.doc.querySelector('[data-native-errors]').hidden);for(const p of paths)assert.equal(h.model.effective(p),true,p);
 assert.equal(h.model.effective('reporter.contact_preference'),'none');assert(!h.model.validate().some(e=>paths.includes(e.field)));
 bundle.click();for(const p of paths)assert.equal(h.model.effective(p),false,p);
 assert(h.model.validate().some(e=>e.field==='consents.privacy'));
 h.set('consents.truthfulness',true);assert(!bundle.checked);h.close();
});
test('BETA01: anonymous and pseudonym flows preserve private fields, branch answers and canonical submission identity',async()=>{
 const h=create();h.fill();const id=h.model.submissionId;at(h,5);
 h.set('reporter.public_identity_mode','pseudonym');assert(!field(h,'reporter.pseudonym').hidden);h.set('reporter.pseudonym','Sentinella QA');
 assert.equal(h.model.payload().reporter.pseudonym,'Sentinella QA');h.set('reporter.public_identity_mode','anonymous');assert(field(h,'reporter.pseudonym').hidden);assert.equal(h.model.payload().reporter.pseudonym,undefined);
 h.set('reporter.public_identity_mode','pseudonym');assert.equal(h.model.effective('reporter.pseudonym'),'Sentinella QA');assert.equal(h.model.submissionId,id);
 h.set('reporter.public_identity_mode','anonymous');h.goReview();h.submit();h.submit();await tick();await tick();assert.equal(h.requests.length,1);assert(h.model.completed);assert.equal(JSON.parse(h.requests[0].request.body).submission_id,id);h.close();
});
