'use strict';
const {test}=require('node:test'),assert=require('node:assert/strict'),{execFileSync}=require('node:child_process');
const {Model}=require('../wordpress/themes/badaround-child/assets/js/native-report/model.js');
const config=JSON.parse(execFileSync(process.env.BA_PHP||'php',['tests/native-report-f16.php','--config'],{encoding:'utf8'}));
const contract=require('../wordpress/plugins/badaround-core/contracts/nf-conditional-v1.0.json');
function fixture(){const m=new Model(config);m.values={...contract.regression_oracle.find(x=>x.subtype==='civic_waste').minimal_user_values,'media.availability':'no','location.region':'Lazio','location.province':'Roma','location.municipality':'Pomezia','location.locality':'Torvaianica'};m.pruneInactive();return m;}
test('radius choices, stale policy, exact null semantics, summary and lock preserve report v2',()=>{
 const m=fixture();assert.equal(m.publicLocationRequest,'exact_point');assert.deepEqual(m.payload().location.public_location_request,{mode:'exact_point'});assert.equal(m.publicLocationLabel(),'Punto esatto');
 m.setPublicLocationRequest('approximate_area');assert.equal(m.publicLocationRadius,500);
 for(const r of [50,100,250,500,1000]){m.setPublicLocationRadius(r);assert.deepEqual(m.payload().location.public_location_request,{mode:'approximate_area',radius_m:r});assert.deepEqual(m.validate(),[]);assert(m.publicLocationLabel().includes(r===1000?'1 km':r+' m'));}
 for(const r of [50,1000]){m.setPublicLocationRadius(r);m.setPublicLocationRequest('exact_point');assert.equal(m.publicLocationRadius,null);assert.deepEqual(m.payload().location.public_location_request,{mode:'exact_point'});m.setPublicLocationRequest('approximate_area');assert.equal(m.publicLocationRadius,500);}
 m.setPublicLocationRadius(100);const p=m.payload();m.snapshot=p;m.setPublicLocationRadius(500);m.setPublicLocationRequest('exact_point');assert.deepEqual(m.payload(),p);assert.equal(m.publicLocationRadius,100);
});
test('strict raw backend validation rejects aliases/floats/unknown version; accepts explicit exact null',()=>{
 const good=[];for(const r of [50,100,250,500,1000]){const m=fixture();m.setPublicLocationRequest('approximate_area');m.setPublicLocationRadius(r);good.push(m.payload());}
 const m=fixture();good.push(m.payload());const explicitNull=structuredClone(good.at(-1));explicitNull.location.public_location_request.radius_m=null;
 const invalid=[];for(const r of [null,0,75,-100,500.5,'500',true,{},[]]){const p=structuredClone(good[0]);p.location.public_location_request.radius_m=r;invalid.push(p);}
 for(const p of [(()=>{const p=structuredClone(good[0]);delete p.location.public_location_request.radius_m;return p;})(),(()=>{const p=structuredClone(good.at(-1));p.location.public_location_request.radius_m=500;return p;})(),(()=>{const p=structuredClone(good[0]);p.location.public_location_request_version='future';return p;})(),(()=>{const p=structuredClone(good[0]);p.location.public_location_request.extra='hidden';return p;})()])invalid.push(p);
 const out=JSON.parse(execFileSync(process.env.BA_PHP||'php',['tests/nf-ux-04-backend.php'],{input:JSON.stringify([...good,explicitNull,...invalid]),encoding:'utf8'}));
 out.slice(0,good.length+1).forEach(r=>assert.equal(r.error,null));out.slice(good.length+1).forEach(r=>assert(r.error));assert.deepEqual(out[good.length].normalized.location.public_location_request,{mode:'exact_point'});
 const persisted=JSON.parse(execFileSync(process.env.BA_PHP||'php',['tests/geo-area-01-persistence.php'],{input:JSON.stringify(good),encoding:'utf8'}));assert(persisted.pass);assert.equal(persisted.cases,6);
});
test('real wizard DOM associates radius errors, clears hidden errors, and reviews only active radius',()=>{
 const {create}=require('./native-media-dom-harness.cjs'),h=create();try{
  h.model.values={...fixture().values};h.model.step=1;h.set('content.description',h.model.values['content.description']);
  const radius=h.doc.querySelector('#ba-public-location-radius'),field=radius.closest('[data-native-radius-field]'),mode=v=>{const el=h.doc.querySelector('[name="ba-public-location-choice"][value="'+v+'"]');el.checked=true;el.dispatchEvent(new h.w.Event('change',{bubbles:true}));};
  assert(field.hidden&&radius.disabled);mode('approximate_area');assert(!field.hidden&&radius.required&&!radius.disabled);assert.equal(radius.value,'500');
  radius.value='100';radius.dispatchEvent(new h.w.Event('change',{bubbles:true}));assert.equal(h.model.publicLocationRadius,100);
  h.w.BadAroundNative.presentErrors(h.root,[{field:'location.public_location_request.radius_m',code:'invalid_public_radius'}],config);assert.equal(radius.getAttribute('aria-invalid'),'true');assert(!h.doc.querySelector('#ba-public-radius-error').hidden);
  mode('exact_point');assert(field.hidden&&radius.disabled&&!radius.required);assert(h.doc.querySelector('#ba-public-radius-error').hidden);assert.equal(radius.getAttribute('aria-invalid'),'false');
  mode('approximate_area');h.model.step=5;h.set('content.description',h.model.values['content.description']);assert(h.doc.querySelector('[data-native-review]').textContent.includes('Area approssimativa — circa 500 m'));
  mode('exact_point');assert(!h.doc.querySelector('[data-native-review]').textContent.includes('circa 500 m'));assert(h.doc.querySelector('[data-native-review]').textContent.includes('Punto esatto'));
 }finally{h.close();}
});
