'use strict';
const {test}=require('node:test'),assert=require('node:assert/strict');
const {create,tick}=require('./native-media-dom-harness.cjs');
test('SOCIAL: no default, optional, independent from contact and required declaration',()=>{
 const h=create();h.fill();h.model.step=4;h.set('reporter.email','qa@example.invalid');
 const field=h.doc.querySelector('[data-native-field="reporter.social_visibility"]');
 assert(!field.hidden);assert.equal(h.model.effective('reporter.social_visibility'),undefined);
 assert(!h.model.validate().some(e=>e.field.includes('social')));assert.equal(field.querySelectorAll('input:checked').length,0);
 assert.equal(h.model.payload().consents.social_visibility_version,undefined);assert.equal(h.model.payload().reporter.social_visibility,undefined);
 assert(!h.doc.querySelector('[name="consents.social_visibility_version"]'));assert.match(field.textContent,/Facebook.*Instagram.*TikTok/);
 h.doc.querySelector('[data-beta-confirm]').click();h.doc.querySelector('[data-beta-confirm]').click();
 assert.equal(h.model.effective('reporter.social_visibility'),undefined);h.close();
});
for(const choice of ['requested','declined'])test('SOCIAL: '+choice+' persists in snapshot/review; reset clears it',async()=>{
 const h=create();h.fill();h.set('reporter.social_visibility',choice,'change');h.goReview();
 const payload=h.model.payload();assert.equal(payload.reporter.social_visibility,choice);assert.equal(payload.consents.social_visibility_version,'social-preference-v1');
 assert.match(h.doc.querySelector('[data-native-review]').textContent,/Vuoi dare visibilità/);
 assert.equal(payload.reporter.contact_preference,'none');h.submit();h.submit();await tick();await tick();
 const requests=h.requests.filter(r=>r.url.endsWith('/reports'));assert.equal(requests.length,1);assert.equal(JSON.parse(requests[0].request.body).reporter.social_visibility,choice);
 assert(h.model.completed);h.click('new');assert.equal(h.model.payload().reporter?.social_visibility,undefined);assert.equal(h.model.payload().consents.social_visibility_version,undefined);h.close();
});
