'use strict';
const {test}=require('node:test'),assert=require('node:assert/strict');
const {create,tick}=require('./native-media-dom-harness.cjs');
const {fixture,config,contract,backend}=require('./nf-ux-04-model.cjs');
function seed(h,oracle){h.model.values={...oracle.minimal_user_values,'media.availability':'no'};h.model.pruneInactive();h.set('content.description',h.model.values['content.description']);}
function wrapper(h,p){return h.doc.querySelector(`[data-native-field="${p}"]`);}
function review(h){h.model.step=5;h.set('content.description',h.model.values['content.description']);return [...h.doc.querySelectorAll('[data-native-review-field]')].map(el=>el.dataset.nativeReviewField);}
test('all 33 DOM profiles: active controls, required markers and summary/payload agree',()=>{
 const h=create();try{for(const oracle of contract.regression_oracle){seed(h,oracle);assert.equal(h.root.nativeReport.config.schemaVersion,'badaround-report/v2');
  for(let step=0;step<5;step++){h.model.step=step;h.set('content.description',h.model.values['content.description']);
   for(const [p,f] of Object.entries(config.fields)){const w=wrapper(h,p);assert(w,p);if(!h.model.active(p)){assert(w.hidden,p);assert([...w.querySelectorAll('input,select,textarea')].every(e=>e.disabled),p+' not tabbable');}
    if(f.step===Object.keys(config.steps)[step]&&h.model.active(p)&&!w.hidden&&!w.hasAttribute('data-beta-bundled')){const flags=w.querySelector('[data-native-required]');if(flags)assert.equal(flags.textContent.includes('obbligatorio'),h.model.required(p),p);}
   }
  }
  const keys=review(h);for(const p of keys){assert(h.model.active(p),p+' summary active');assert(h.model.effective(p)!==undefined,p);}
  for(const [p,f] of Object.entries(config.fields))if(h.model.active(p)&&h.model.effective(p)!==undefined&&!(p.startsWith('location.')&&p!=='location.exact_address'&&!f.sourceField)&&!p.startsWith('consents.'))assert(keys.includes(p),p+' summary present');
  assert.equal(h.model.validate().length,0,oracle.subtype);
 }}finally{h.close();}
});
test('all 8 specification fields reach summary then disappear from model/control/summary on parent change',()=>{
 const h=create();try{for(const [p,spec] of Object.entries(contract.fields).filter(([,s])=>s.source_field)){
  const row=contract.regression_oracle.find(o=>o.expected_fields?.[p]?.state!=='HIDDEN'&&contract.matrix.find(r=>r.subtype===o.subtype).fields[p].state!=='HIDDEN')||contract.regression_oracle[0];seed(h,row);
  function activate(expr){if(expr.all)expr.all.forEach(activate);else if(expr.any)activate(expr.any[0]);else if(expr.op!=='active'){const rule=h.model.rule(expr.field);if(rule?.condition)activate(rule.condition);h.model.set(expr.field,expr.op==='contains'?[expr.value]:expr.value??(expr.values.includes(h.model.effective(expr.field))?h.model.effective(expr.field):expr.values[0]));}}
  activate(h.model.rule(p).condition);h.model.step=2;h.set('content.description',h.model.values['content.description']);
  h.set(p,'Specifica sintetica');assert(review(h).includes(p),p+' summary');
  const f=config.fields[spec.source_field],alternate=(f.constraints.enum||f.constraints.item_enum).find(v=>v!=='other');h.model.set(spec.source_field,f.type==='array'?[alternate]:alternate);h.set('content.description',h.model.values['content.description']);assert.equal(wrapper(h,p).querySelector('[data-native-input]').value,'',p+' cleared DOM');assert(!review(h).includes(p));
 }}finally{h.close();}
});
test('Native DOM v2 submission, retry snapshot and backend field correction',async()=>{
 const h=create({fetch:async(url,req,body)=>{if(url.endsWith('/reports'))return {ok:false,status:422,json:async()=>({status:'error',error:{code:'required_field',field:'vehicle.type_other_detail',retryable:false}})};}});
 try{seed(h,contract.regression_oracle[0]);h.set('vehicle.type','other');h.set('vehicle.type_other_detail','Specifica sintetica');h.model.step=5;h.set('content.description',h.model.values['content.description']);h.submit();await tick();await tick();assert.equal(h.model.snapshot,null);assert.equal(h.model.step,2);assert.equal(wrapper(h,'vehicle.type_other_detail').querySelector('[data-native-input]').getAttribute('aria-invalid'),'true');assert(h.doc.querySelector('[data-native-errors]').textContent.includes('Specifica'));}finally{h.close();}
});
test('v2 no-media submission and refresh/new-report reset retain no stale answers',async()=>{
 const h=create();try{seed(h,contract.regression_oracle[0]);h.goReview();h.submit();await tick();await tick();const req=h.requests.find(r=>r.url.endsWith('/reports'));assert(req);const p=JSON.parse(req.request.body);assert.equal(p.schema_version,'badaround-report/v2');assert.equal(p.media.availability,'no');assert(!p.media.items?.length);assert.equal(h.requests.length,1);h.click('new');assert.equal(h.model.values['event.subtype'],undefined);assert.equal(h.model.snapshot,null);assert(!h.doc.querySelector('[data-native-emergency-notice]').textContent);}finally{h.close();}
});
