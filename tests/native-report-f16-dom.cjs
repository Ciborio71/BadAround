'use strict';
const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {execFileSync} = require('node:child_process');
const {JSDOM} = require('jsdom');
const html = execFileSync(process.env.BA_PHP || 'php',[path.join(__dirname,'native-report-f16.php'),'--html'],{encoding:'utf8'});
function create(responseFactory) {
  const dom = new JSDOM(html,{url:'https://staging.badaround.it/segnala-un-evento/?native_report=1',runScripts:'outside-only'});
  const {window} = dom, requests = [];
  window.AbortController = AbortController;
  window.fetch = async (url,options) => { requests.push({url,options});return responseFactory(JSON.parse(options.body),requests.length); };
  for (const file of ['model','api','errors','wizard']) window.eval(fs.readFileSync(path.join(__dirname,'../wordpress/themes/badaround-child/assets/js/native-report',file+'.js'),'utf8'));
  const doc=window.document, root=doc.querySelector('[data-native-report]');
  const set=(key,value)=>{
    const input=doc.querySelector(`[name="${key}"]`);
    if(input.type==='checkbox') input.checked=value;else input.value=value;
    input.dispatchEvent(new window.Event('input',{bubbles:true}));
  };
  const click=key=>doc.querySelector(`[data-native-${key}]`).click();
  const submit=()=>doc.querySelector('form').dispatchEvent(new window.Event('submit',{bubbles:true,cancelable:true}));
  return {dom,window,doc,root,requests,set,click,submit,model:root.nativeReport.model};
}
const completed=payload=>({ok:true,status:201,json:async()=>({status:'success',submission_id:payload.submission_id,schema_version:payload.schema_version,next_state:'moderation_pending',duplicate:false})});
function populate(h) {
  const data={'event.category':'hazard','event.subtype':'hazard_road_obstruction','reporter.relationship':'territory_observer',
    'location.region':'Lazio','location.province':'Roma','location.municipality':'Pomezia','location.locality':'Torvaianica',
    'location.exact_address':'Via Test 1, Pomezia','location.place_type':'road_sidewalk','location.immediate_danger':'no',
    'time.mode':'exact','time.date':'2026-10-01','time.knowledge':'unknown','content.description':'Segnalazione sintetica F1.6.',
    'damage.status':'no','authority.status':'no','media.availability':'no','reporter.first_name':'QA','reporter.last_name':'Synthetic',
    'reporter.email':'qa@example.invalid','reporter.contact_preference':'none','consents.truthfulness':true,'consents.media_rights':true,
    'consents.publication_rules':true,'consents.terms':true,'consents.privacy':true};
  Object.entries(data).forEach(([key,value])=>h.set(key,value));
}
const tick=()=>new Promise(resolve=>setImmediate(resolve));
test('boot uses actual SSR, no request before explicit final submit',()=>{
  const h=create(completed);assert(h.root.nativeReport);assert.equal(h.requests.length,0);
  assert.equal(h.doc.querySelector('[data-native-step="event"]').hidden,false);
  assert.equal(h.doc.querySelector('[data-native-step="location"]').hidden,true);
  h.submit();assert.equal(h.requests.length,0);h.dom.window.close();
});
test('category selection, subtype filtering and conditional visibility',()=>{
  const h=create(completed);h.set('event.category','vehicle');
  const opts=Array.from(h.doc.querySelector('[name="event.subtype"]').options).map(o=>o.value).filter(Boolean);
  assert.equal(opts.length,6);assert(opts.every(x=>x.startsWith('vehicle_')));
  h.set('event.subtype','vehicle_stolen');h.set('vehicle.plate_knowledge','full');
  const plate=h.doc.querySelector('[data-native-field="vehicle.plate_raw"]');assert(!plate.hidden);
  h.set('vehicle.plate_raw','AB123CD');h.set('event.category','animal');h.set('event.subtype','animal_missing');
  assert(plate.hidden);assert(h.doc.querySelector('[name="vehicle.plate_raw"]').disabled);assert(!h.model.payload().vehicle);
  h.dom.window.close();
});
test('forward/back, focus and state retention',()=>{
  const h=create(completed);h.set('event.category','hazard');h.set('event.subtype','hazard_road_obstruction');h.set('reporter.relationship','territory_observer');
  const id=h.model.submissionId;h.click('next');assert.equal(h.model.step,1);
  assert.equal(h.doc.activeElement.id,'ba-native-heading-location');h.set('location.exact_address','Via Test 1');
  h.click('back');assert.equal(h.model.step,0);assert.equal(h.doc.querySelector('[name="event.category"]').value,'hazard');
  h.click('next');assert.equal(h.doc.querySelector('[name="location.exact_address"]').value,'Via Test 1');assert.equal(h.model.submissionId,id);
  h.dom.window.close();
});
test('invalid step cannot advance; accessible error summary and error focus',()=>{
  const h=create(completed);h.click('next');assert.equal(h.model.step,0);
  const summary=h.doc.querySelector('[data-native-errors]');assert(!summary.hidden);assert.equal(h.doc.activeElement,summary);
  const category=h.doc.querySelector('[name="event.category"]');assert.equal(category.getAttribute('aria-invalid'),'true');
  assert(category.getAttribute('aria-describedby').includes('ba-native-event-category-error'));
  summary.querySelector('button').click();assert.equal(h.doc.activeElement,category);h.dom.window.close();
});
test('successful submit, double-click guard, UUID reset only after distinct report',async()=>{
  let finish;const h=create(payload=>new Promise(resolve=>{finish=()=>resolve(completed(payload));}));populate(h);
  for(let i=0;i<6;i++)h.click('next');assert.equal(h.model.step,6);
  const id=h.model.submissionId;h.submit();h.submit();assert.equal(h.requests.length,1);
  assert(h.doc.querySelector('[data-native-submit]').disabled);assert.equal(h.doc.querySelector('form').getAttribute('aria-busy'),'true');
  finish();await tick();assert(!h.doc.querySelector('[data-native-success]').hidden);assert(h.doc.querySelector('form').hidden);
  assert.equal(h.doc.activeElement.id,'ba-native-received');assert(h.doc.querySelector('[data-native-success]').textContent.includes('Non è ancora pubblica'));
  h.click('new');assert.notEqual(h.model.submissionId,id);assert.equal(h.model.step,0);assert.equal(h.requests.length,1);h.dom.window.close();
});
test('network uncertainty freezes fields, retry same payload, completed duplicate shows success',async()=>{
  const h=create((payload,count)=>{if(count===1)throw Error('network timeout');return {...completed(payload),status:200,json:async()=>({status:'success',submission_id:payload.submission_id,schema_version:payload.schema_version,next_state:'moderation_pending',duplicate:true})};});
  populate(h);for(let i=0;i<6;i++)h.click('next');h.submit();await tick();
  assert(h.model.snapshot);assert(h.doc.querySelector('[data-native-back]').disabled);assert(h.doc.querySelector('[name="content.description"]').disabled);
  assert.equal(h.model.lastError.code,'network_error');assert(!h.doc.querySelector('[data-native-errors]').textContent.includes('network_error'));
  h.submit();await tick();assert.equal(h.requests.length,2);assert.equal(h.requests[0].options.body,h.requests[1].options.body);
  assert(!h.doc.querySelector('[data-native-success]').hidden);h.dom.window.close();
});
test('structured server field error routes to matching step; edits keep UUID',async()=>{
  const h=create(()=>({ok:false,status:422,json:async()=>({status:'error',error:{code:'invalid_email',field:'reporter.email',retryable:false}})}));
  populate(h);for(let i=0;i<6;i++)h.click('next');const id=h.model.submissionId;h.submit();await tick();
  assert.equal(h.model.step,5);assert.equal(h.model.snapshot,null);assert.equal(h.doc.querySelector('[name="reporter.email"]').getAttribute('aria-invalid'),'true');
  h.set('reporter.email','changed@example.invalid');assert.equal(h.model.submissionId,id);assert.equal(h.model.values['reporter.email'],'changed@example.invalid');h.dom.window.close();
});
test('non-validation 422 preserves snapshot and blocks unsafe editing or resubmission',async()=>{
  for(const code of ['insert_failed','persistence_invalid_intake','unrecognized_error']) {
    const h=create(()=>({ok:false,status:422,json:async()=>({status:'error',error:{code,field:null,retryable:false}})}));
    populate(h);for(let i=0;i<6;i++)h.click('next');const id=h.model.submissionId;h.submit();await tick();
    assert(h.model.snapshot);assert.equal(h.model.submissionId,id);assert.equal(h.model.step,6);
    assert(h.doc.querySelector('[name="content.description"]').disabled);assert(h.doc.querySelector('[data-native-back]').disabled);
    assert(h.doc.querySelector('[data-native-submit]').disabled);h.submit();assert.equal(h.requests.length,1);h.dom.window.close();
  }
});
test('retryable 422 retains exact snapshot and retries even if it names a user field',async()=>{
  const h=create((payload,count)=>count===1?{ok:false,status:422,json:async()=>({status:'error',error:{code:'invalid_email',field:'reporter.email',retryable:true}})}:completed(payload));
  populate(h);for(let i=0;i<6;i++)h.click('next');h.submit();await tick();
  assert(h.model.snapshot);assert.equal(h.model.step,6);h.submit();await tick();
  assert.equal(h.requests.length,2);assert.equal(h.requests[0].options.body,h.requests[1].options.body);
  assert(!h.doc.querySelector('[data-native-success]').hidden);h.dom.window.close();
});
test('all control descriptions resolve, labels associated and no executable user HTML',()=>{
  const h=create(completed);populate(h);h.set('content.description','<img src=x onerror=alert(1)>');for(let i=0;i<6;i++)h.click('next');
  assert(!h.doc.querySelector('[data-native-review] img'));assert(h.doc.querySelector('[data-native-review]').textContent.includes('<img'));
  h.doc.querySelectorAll('[aria-describedby]').forEach(input=>input.getAttribute('aria-describedby').split(' ').forEach(id=>assert(h.doc.getElementById(id))));
  h.doc.querySelectorAll('input,select,textarea').forEach(input=>assert(input.closest('label')||h.doc.querySelector(`label[for="${input.id}"]`)));
  assert(!/wpforms/i.test(h.root.innerHTML));h.dom.window.close();
});
