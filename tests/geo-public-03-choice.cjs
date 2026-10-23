'use strict';
const {test}=require('node:test');
const assert=require('node:assert/strict');
const {execFileSync}=require('node:child_process');
const fs=require('node:fs');
const {Model}=require('../wordpress/themes/badaround-child/assets/js/native-report/model.js');
const php=process.env.BA_PHP||'php';
const config=JSON.parse(execFileSync(php,['tests/native-report-f16.php','--config'],{encoding:'utf8'}));
const html=execFileSync(php,['tests/native-report-f16.php','--html'],{encoding:'utf8'});
const root='wordpress/themes/badaround-child/';
const wizard=fs.readFileSync(root+'assets/js/native-report/wizard.js','utf8');
const template=fs.readFileSync(root+'template-parts/native-report/step.php','utf8');
const css=fs.readFileSync(root+'assets/css/native-report.css','utf8');
function m(){return new Model(config,{randomUUID:()=> 'b1f8a000-0000-4000-8000-000000000001'});}
test('GEO-PUBLIC-03: default request is exact_point and version is immutable',()=>{
 const v=m(),p=v.payload();
 assert.equal(v.publicLocationRequest,'exact_point');
 assert.deepEqual(p.location.public_location_request,{mode:'exact_point'});
 assert.equal(p.location.public_location_request_version,'public-location-request-v2');
});
test('selection is actual serialized request, not config default',()=>{
 const v=m();v.setPublicLocationRequest('approximate_area');
 assert.deepEqual(v.payload().location.public_location_request,{mode:'approximate_area',radius_m:500});
 assert.equal(v.payload().location.public_location_request_version,'public-location-request-v2');
 v.setPublicLocationRequest('exact_point');assert.deepEqual(v.payload().location.public_location_request,{mode:'exact_point'});
});
test('location controls are independent of exact private point and legacy public_precision',()=>{
 const v=m();v.set('location.exact_lat',41.68);v.set('location.exact_lng',12.49);
 const before=v.payload(),id=before.submission_id;
 v.setPublicLocationRequest('approximate_area');
 const after=v.payload();
 assert.equal(after.location.exact_lat,41.68);assert.equal(after.location.exact_lng,12.49);
 assert.equal(after.submission_id,id);
 assert.equal(after.location.public_precision,before.location.public_precision);
 assert.equal(after.location.public_lat,undefined);assert.equal(after.location.public_lng,undefined);
});
test('snapshot/retry locks choice and reset restores default',()=>{
 const v=m();v.setPublicLocationRequest('approximate_area');
 const p=v.payload();v.snapshot=p;
 v.setPublicLocationRequest('exact_point');
 assert.equal(v.publicLocationRequest,'approximate_area');
 assert.deepEqual(v.snapshot.location.public_location_request,{mode:'approximate_area',radius_m:500});
 assert.equal(v.snapshot.location.public_location_request_version,'public-location-request-v2');
 v.snapshot=null;v.reset();
 assert.equal(v.publicLocationRequest,'exact_point');
});
test('invalid modes cannot corrupt canonical request',()=>{
 const v=m();v.setPublicLocationRequest('legacy_point');assert.equal(v.publicLocationRequest,'exact_point');
 v.setPublicLocationRequest('approximate_area');v.setPublicLocationRequest('municipality');assert.equal(v.publicLocationRequest,'approximate_area');
});
test('legacy config is untouched, with no fabricated request',()=>{
 const old=structuredClone(config);delete old.publicLocationRequest;
 const v=new Model(old,{randomUUID:()=> 'b1f8a000-0000-4000-8000-000000000001'});
 v.setPublicLocationRequest('approximate_area');
 assert.equal(v.payload().location.public_location_request,undefined);
 assert.equal(v.payload().location.public_location_request_version,undefined);
});
test('HTML displays exactly two descriptive radio choices, no technical fields',()=>{
 assert(html.includes('data-native-public-location-choice'));
 assert(html.includes('value="exact_point" checked'));
 assert(html.includes('value="approximate_area"'));
 assert(html.includes('Come vuoi che venga mostrata pubblicamente la posizione?'));
 assert(html.includes('Se la segnalazione è approvata'));
 assert(!html.includes('name="location.public_location_request_version"'));
 assert(!html.includes('name="location.public_location_request"'));
});
test('display location choice is between private pinpoint and time details',()=>{
 const a=template.indexOf('data-smart-map-panel');
 const b=template.indexOf('data-native-public-location-choice');
 const c=template.indexOf("<?php elseif ( 'time' === $key )");
 assert(a!==-1 && b>a && c>b);
});
test('wizard keeps source of truth in model, renders review without coordinates',()=>{
 assert(wizard.includes('model.setPublicLocationRequest(event.target.value)'));
 assert(wizard.includes("model.publicLocationLabel()"));
 assert(wizard.includes('Posizione pubblica richiesta'));
 assert(!wizard.includes('config.publicLocationRequest.default);'));
 assert(wizard.includes('model.snapshot=payload;'));
});
test('private GPS and Smart Address handlers remain unchanged; teal GPS is accessible',()=>{
 assert(html.includes('data-smart-geolocate'));
 assert(css.includes('[data-smart-geolocate].ba-native-gps-button'));
 assert(css.includes(':focus-visible'));
 assert(css.includes(':disabled'));
 assert(fs.readFileSync(root+'assets/js/native-report/location-map.js','utf8').includes('navigator.geolocation.getCurrentPosition'));
});
