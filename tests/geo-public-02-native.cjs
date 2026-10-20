'use strict';
const {test}=require('node:test');
const assert=require('node:assert/strict');
const {execFileSync}=require('node:child_process');
const {Model}=require('../wordpress/themes/badaround-child/assets/js/native-report/model.js');
const php=process.env.BA_PHP||'php';
const config=JSON.parse(execFileSync(php,['tests/native-report-f16.php','--config'],{encoding:'utf8'}));
test('new Native model explicitly serializes a versioned exact request without public coordinates',()=>{
 const m=new Model(config);
 m.set('location.exact_address','Via Test 10');m.set('location.exact_lat',41.669);m.set('location.exact_lng',12.501);
 const p=m.payload();
 assert.equal(p.location.public_location_request,'exact_point');
 assert.equal(p.location.public_location_request_version,'public-location-request-v1');
 assert.equal(p.location.exact_lat,41.669);assert.equal(p.location.exact_lng,12.501);
 assert.equal(p.location.public_lat,undefined);assert.equal(p.public_geo,undefined);
 m.set('location.exact_lat',41.67);m.set('location.exact_lng',12.502);
 assert.equal(m.payload().location.exact_lat,41.67);assert.equal(m.payload().location.exact_lng,12.502);
 assert.equal(m.payload().location.public_location_request,'exact_point');
});
test('legacy client config does not invent a request or change retry payload',()=>{
 const old=structuredClone(config);delete old.publicLocationRequest;
 const m=new Model(old);const a=JSON.stringify(m.payload());
 assert.equal(m.payload().location.public_location_request,undefined);
 assert.equal(JSON.stringify(m.payload()),a);
});
test('contract-only extension adds no visible native control or technical field',()=>{
 const html=execFileSync(php,['tests/native-report-f16.php','--html'],{encoding:'utf8'});
 assert(!config.fields['location.public_location_request']);
 assert(!config.fields['location.public_location_request_version']);
 assert(!html.includes('data-native-field="location.public_location_request"'));
 assert(!html.includes('data-native-field="location.public_location_request_version"'));
});
