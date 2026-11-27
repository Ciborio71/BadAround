'use strict';
const {test}=require('node:test'),assert=require('node:assert/strict');
const {create,tick}=require('./native-media-dom-harness.cjs');
test('SOCIAL: unchecked checkbox records declined and stays independent from contact and required declaration',()=>{
 const h=create();h.fill();h.model.step=4;h.set('reporter.email','qa@example.invalid');
 const field=h.doc.querySelector('[data-native-field="reporter.social_visibility"]');
 assert(!field.hidden);assert.equal(h.model.effective('reporter.social_visibility'),'declined');
 assert(!h.model.validate().some(e=>e.field.includes('social')));assert.equal(field.querySelectorAll('input:checked').length,0);
 assert.equal(h.model.payload().consents.social_visibility_version,'social-preference-v1');assert.equal(h.model.payload().reporter.social_visibility,'declined');
 assert(!h.doc.querySelector('[name="consents.social_visibility_version"]'));assert.match(field.textContent,/Facebook.*Instagram.*TikTok/);
 h.doc.querySelector('[data-beta-confirm]').click();h.doc.querySelector('[data-beta-confirm]').click();
 assert.equal(h.model.effective('reporter.social_visibility'),'declined');h.close();
});
for(const choice of ['requested','declined'])test('SOCIAL: '+choice+' persists in snapshot/review; reset returns to declined',async()=>{
 const h=create();h.fill();h.set('reporter.social_visibility',choice,'change');h.goReview();
 const payload=h.model.payload();assert.equal(payload.reporter.social_visibility,choice);assert.equal(payload.consents.social_visibility_version,'social-preference-v1');
 assert.match(h.doc.querySelector('[data-native-review]').textContent,/Vuoi dare visibilità/);
 assert.equal(payload.reporter.contact_preference,'none');h.submit();h.submit();await tick();await tick();
 const requests=h.requests.filter(r=>r.url.endsWith('/reports'));assert.equal(requests.length,1);assert.equal(JSON.parse(requests[0].request.body).reporter.social_visibility,choice);
 assert(h.model.completed);h.click('new');assert.equal(h.model.payload().reporter?.social_visibility,'declined');assert.equal(h.model.payload().consents.social_visibility_version,'social-preference-v1');h.close();
});

test('SOCIAL: actual checkbox maps false/true to the compatible canonical enum and locks during submit',()=>{
 const h=create();try{
 h.fill();h.model.step=4;h.set('reporter.email','qa@example.invalid');
 const checkbox=h.doc.querySelector('[data-native-social-consent]'),field=checkbox.closest('[data-native-field]');
 assert.equal(checkbox.type,'checkbox');assert.equal(checkbox.checked,false);assert.equal(checkbox.disabled,false);assert.equal(field.querySelector('.ba-native-card-grid'),null);
 checkbox.click();assert.equal(h.model.payload().reporter.social_visibility,'requested');assert.equal(checkbox.checked,true);
 checkbox.click();assert.equal(h.model.payload().reporter.social_visibility,'declined');assert.equal(checkbox.checked,false);
 assert.equal(h.model.payload().reporter.contact_preference,'none');
 h.model.busy=true;h.set('reporter.email','qa@example.invalid');assert.equal(checkbox.disabled,true);
 }finally{h.close();}
});
