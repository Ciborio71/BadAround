'use strict';
const {test} = require('node:test');
const assert = require('node:assert/strict');
const {execFileSync} = require('node:child_process');
const path = require('node:path');
const fs = require('node:fs');
const php = process.env.BA_PHP || 'php';
const config = JSON.parse(execFileSync(php, [path.join(__dirname,'native-report-f16.php'), '--config'], {encoding:'utf8'}));
const base = '../wordpress/themes/badaround-child/assets/js/native-report/';
const {Model} = require(base+'model.js');
const send = require(base+'api.js');
const {errorMessage} = require(base+'errors.js');
const fixture = () => {
  const model = new Model(config);
  Object.entries({'event.category':'hazard','event.subtype':'hazard_road_obstruction','reporter.relationship':'territory_observer',
    'location.region':'Lazio','location.province':'Roma','location.municipality':'Pomezia','location.locality':'Torvaianica',
    'location.exact_address':'Via Test 1, Pomezia','location.place_type':'road_sidewalk','location.immediate_danger':'no',
    'time.mode':'exact','time.date':'2026-10-01','time.knowledge':'unknown','content.description':'Segnalazione sintetica F1.6.',
    'damage.status':'no','authority.status':'no','media.availability':'no','reporter.first_name':'QA','reporter.last_name':'Synthetic',
    'reporter.email':'qa@example.invalid','reporter.contact_preference':'none','consents.truthfulness':true,'consents.media_rights':true,
    'consents.publication_rules':true,'consents.terms':true,'consents.privacy':true}).forEach(([key,value])=>model.set(key,value));
  return model;
};
test('UUID once per compilation, stable edits/navigation/retry and new after reset',()=>{
  let count=0; const crypto={randomUUID(){count++;return globalThis.crypto.randomUUID();}};
  const model=new Model(config,crypto); const id=model.submissionId;
  model.set('event.category','vehicle'); model.step++; model.step--;
  model.payload(); model.payload(); assert.equal(count,1);assert.equal(model.submissionId,id);
  model.reset();assert.equal(count,2);assert.notEqual(model.submissionId,id);
});
test('schema/category/subtype source and full valid fixture',()=>{
  const model=fixture();assert.deepEqual(model.validate(),[]);
  assert.equal(model.payload().event.category,'hazard');assert.equal(model.payload().event.subtype,'hazard_road_obstruction');
  assert.equal(model.payload().schema_version,config.schemaVersion);
});
test('category subtype filtering and invalid pair',()=>{
  const model=fixture();model.set('event.category','vehicle');
  assert.deepEqual(model.subtypes(),Object.keys(config.categories.vehicle.subtypes));
  assert.equal(model.payload().event.subtype,undefined);assert(model.validate('event').some(e=>e.field==='event.subtype'));
});
test('transitive conditional visibility and stale plate/time exclusion',()=>{
  const model=fixture();model.set('event.category','vehicle');model.set('event.subtype','vehicle_stolen');
  model.set('vehicle.plate_knowledge','full');model.set('vehicle.plate_raw','AB123CD');
  assert(model.active('vehicle.plate_raw'));model.set('event.category','animal');model.set('event.subtype','animal_missing');
  assert(!model.active('vehicle.plate_raw'));assert(!model.payload().vehicle);
  model.set('time.knowledge','exact');model.set('time.exact_time','10:15');model.set('time.mode','unknown');
  assert(!model.active('time.exact_time'));assert.deepEqual(model.payload().time,{mode:'unknown'});
});
test('optional state retained while hidden; deterministic effective payload',()=>{
  const model=fixture();model.set('damage.status','yes');model.set('damage.description','Danno sintetico');
  model.set('damage.status','no');assert.equal(model.values['damage.description'],'Danno sintetico');assert.equal(model.payload().damage.description,undefined);
  model.set('damage.status','yes');assert.equal(model.payload().damage.description,'Danno sintetico');
});
test('required bool/array and coordinate pair validation',()=>{
  const model=fixture();model.set('consents.privacy',false);assert(model.validate().some(e=>e.field==='consents.privacy'));
  model.set('location.exact_lat','41.6');assert(model.validate('location').some(e=>e.code==='invalid_location'));
  model.set('location.exact_lng','12.5');assert.equal(model.payload().location.exact_lat,41.6);
  model.set('event.category','property');model.set('event.subtype','property_theft');assert(model.validate().some(e=>e.field==='property.stolen_item_categories'));
  model.set('property.stolen_item_categories',['cash','documents']);assert.deepEqual(model.payload().property.stolen_item_categories,['cash','documents']);
});
test('no WPForms identifiers, local storage or automatic API in model',()=>{
  const payload=JSON.stringify(fixture().payload());assert(!/wpforms|field_id|choice_id/i.test(payload));
  const sources=['model','api','errors','wizard'].map(x=>fs.readFileSync(path.join(__dirname,base,x+'.js'),'utf8')).join('');
  assert(!/localStorage|sessionStorage/.test(sources));assert(!/['"]Origin['"]\s*:|['"]Sec-Fetch/.test(sources));
});
test('retry immutable payload with same UUID, transport protocol and duplicate success',async()=>{
  const model=fixture();model.snapshot=model.payload();const requests=[];
  const fetcher=async(url,options)=>{requests.push({url,options});if(requests.length===1)throw Error('timeout');return {ok:true,status:200,json:async()=>({status:'success',submission_id:model.submissionId,schema_version:config.schemaVersion,next_state:'moderation_pending',duplicate:true})};};
  const first=await send(config,model.snapshot,fetcher,'https://staging.badaround.it');assert(first.error.retryable);
  model.set('content.description','Must not change retry');assert.equal(model.values['content.description'],'Segnalazione sintetica F1.6.');
  const second=await send(config,model.snapshot,fetcher,'https://staging.badaround.it');assert(second.ok&&second.duplicate);
  assert.equal(requests[0].options.body,requests[1].options.body);assert.equal(requests[0].options.mode,'same-origin');
  assert.equal(requests[0].options.credentials,'omit');assert.equal(requests[0].options.headers[config.markerHeader],config.markerValue);
  assert.equal(requests[0].options.headers['Content-Type'],'application/json');
});
test('structured rejection and rate limit; wrong success identity is not success',async()=>{
  const model=fixture();
  const limited=await send(config,model.payload(),async()=>({ok:false,status:429,json:async()=>({status:'error',error:{code:'rate_limited',field:null,retryable:true}})}),'https://staging.badaround.it');
  assert.equal(limited.error.code,'rate_limited');assert(limited.error.retryable);
  assert(!errorMessage(limited.error).includes('rate_limited'));
  const invalid=await send(config,model.payload(),async()=>({ok:true,status:201,json:async()=>({status:'success',submission_id:'wrong'})}),'https://staging.badaround.it');assert(!invalid.ok);
  let calls=0;const cross=await send({...config,endpoint:'https://elsewhere.invalid/reports'},model.payload(),async()=>{calls++;},'https://staging.badaround.it');assert(!cross.ok);assert.equal(calls,0);
});
module.exports={config,fixture};

test('frontend payloads for all six groups accepted by frozen F1.3 validator',()=>{
  const payloads=[];
  for(const [category,group] of Object.entries(config.categories)) {
    const model=fixture();model.set('event.category',category);model.set('event.subtype',Object.keys(group.subtypes)[0]);
    Object.entries({'vehicle.type':'car','vehicle.plate_knowledge':'unknown','witness.status':'seeking',
      'property.stolen_item_categories':['cash'],'property.access_method':'unknown','animal.type':'dog',
      'object.description':'Oggetto sintetico','object.status':'lost'}).forEach(([key,value])=>model.set(key,value));
    assert.deepEqual(model.validate(),[],category);payloads.push(model.payload());
  }
  const code = 'require "tests/native-report-f13.php"; $validator = new BadAround_Report_Validator(); foreach (json_decode(file_get_contents("php://stdin"), true) as $payload) { $raw = $validator->validate_raw_contract($payload); if (is_wp_error($raw)) { fwrite(STDERR, $raw->get_error_code()); exit(1); } $valid = $validator->validate($validator->normalize($payload)); if (is_wp_error($valid)) { fwrite(STDERR, $valid->get_error_code()); exit(1); } }';
  execFileSync(php,['-r',code],{cwd:path.dirname(__dirname),input:JSON.stringify(payloads),encoding:'utf8'});
});
