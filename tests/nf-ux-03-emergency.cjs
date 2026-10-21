'use strict';
const {test}=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const {create,tick}=require('./native-media-dom-harness.cjs');
const contract=JSON.parse(fs.readFileSync(path.join(__dirname,'../wordpress/plugins/badaround-core/contracts/nf-conditional-v1.0.json')));
const automatic=contract.matrix.filter(row=>row.emergency.automatic_subtype_notice);
const notice=h=>h.doc.querySelector('[data-native-emergency-notice]');
function choose(h,row){h.set('event.category',row.category,'change');h.set('event.subtype',row.subtype,'change');}
function visible(h){return !notice(h).hidden&&!notice(h).closest('[data-native-step]').hidden;}
test('NF-UX-03: config projects exact frozen emergency rules without changing v1 schema',()=>{
 const h=create();assert.equal(automatic.length,9);
 assert.deepEqual([...h.root.nativeReport.config.emergencyNotice.subtypes],automatic.map(r=>r.subtype));
 assert.deepEqual([...h.root.nativeReport.config.emergencyNotice.dangerValues],['yes','unsure']);
 assert.equal(h.model.payload().schema_version,'badaround-report/v1');assert(!Object.keys(h.root.nativeReport.config.fields).some(k=>/emergency/i.test(k)));h.close();
});
for(const row of contract.matrix)test(`NF-UX-03: frozen subtype ${row.subtype}`,()=>{
 const h=create();choose(h,row);h.set('location.immediate_danger','no','change');
 assert.equal(visible(h),row.emergency.automatic_subtype_notice);
 assert.equal(h.doc.querySelectorAll('[data-native-emergency-notice]').length,1);
 if(visible(h))assert.equal(notice(h).previousElementSibling.dataset.nativeField,'event.subtype');
 h.close();
});
test('NF-UX-03: global yes/unsure/no, dual trigger and stale category/subtype changes',()=>{
 const h=create();choose(h,{category:'vehicle',subtype:'vehicle_stolen'});
 for(const [answer,expected] of [['no',false],['yes',true],['unsure',true],['no',false]]){h.set('location.immediate_danger',answer,'change');assert.equal(visible(h),expected);}
 choose(h,automatic[0]);assert(visible(h));h.set('location.immediate_danger','yes');assert.equal(h.doc.querySelectorAll('[data-native-emergency-notice]:not([hidden])').length,1);
 h.set('location.immediate_danger','no');assert(visible(h));
 choose(h,{category:'vehicle',subtype:'vehicle_stolen'});assert(!visible(h));
 choose(h,{category:'animal',subtype:'animal_injured'});assert(visible(h));h.set('event.category','property','change');assert(!visible(h),'invalid stale subtype cannot retain notice');h.close();
});
test('NF-UX-03: navigation, in-memory boot, keyboard focus, reset and payload isolation',async()=>{
 const h=create();h.fill();h.set('event.category','vehicle');h.set('event.subtype','vehicle_stolen');
 const original=JSON.stringify(h.model.payload());h.click('next');h.click('next');
 const input=h.doc.querySelector('[data-native-field="location.immediate_danger"] .ba-native-card-grid input[value=yes]');input.focus();input.click();await new Promise(r=>setTimeout(r,30));
 assert(visible(h));assert.equal(notice(h).previousElementSibling.dataset.nativeField,'location.immediate_danger');
 assert.equal(notice(h).getAttribute('role'),'status');assert.equal(notice(h).getAttribute('aria-live'),'polite');assert.equal(notice(h).getAttribute('aria-atomic'),'true');
 assert(notice(h).textContent.includes('112'));assert(notice(h).textContent.includes('BadAround non sostituisce'));assert(!notice(h).hasAttribute('tabindex'));
 const focused=h.doc.activeElement;h.w.eval(fs.readFileSync(path.join(__dirname,'../wordpress/themes/badaround-child/assets/js/native-report/wizard.js'),'utf8'));assert.equal(h.doc.activeElement,focused);assert.equal(h.doc.querySelectorAll('[data-native-emergency-notice]').length,1);
 h.click('back');assert(visible(h));h.click('next');assert(visible(h));
 h.set('location.immediate_danger','no');assert(!visible(h));assert.equal(JSON.stringify(h.model.payload()),original);
 h.fill();h.set('location.immediate_danger','unsure');h.goReview();assert(visible(h));assert.equal(notice(h).previousElementSibling.tagName,'H2');
 h.set('location.immediate_danger','no');h.set('reporter.social_visibility','requested');h.model.setPublicLocationRequest('approximate_area');
 const expected=h.model.payload();h.submit();await tick();await tick();const sent=JSON.parse(h.requests.at(-1).request.body);assert.deepEqual(sent,JSON.parse(JSON.stringify(expected)));assert.equal(sent.schema_version,'badaround-report/v1');assert.equal(sent.location.public_location_request,'approximate_area');assert.equal(sent.reporter.social_visibility,'requested');assert.equal(sent.media.availability,'no');
 for(const key of ['truthfulness','media_rights','publication_rules','terms','privacy'])assert.equal(sent.consents[key],true);
 h.click('new');assert(!visible(h));assert.equal(h.model.step,0);h.close();
});
