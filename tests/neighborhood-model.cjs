'use strict';
const {test}=require('node:test'),assert=require('node:assert/strict'),{execFileSync}=require('node:child_process');
const {Model}=require('../wordpress/themes/badaround-child/assets/js/native-report/model.js');
const config=JSON.parse(execFileSync(process.env.BA_PHP||'php',['tests/native-report-f16.php','--config'],{encoding:'utf8'}));
const contract=require('../wordpress/plugins/badaround-core/contracts/nf-conditional-v1.0.json');
function fixture(enabled=false){const m=new Model({...config,neighborhoodProposal:enabled});m.values={...contract.regression_oracle.find(x=>x.subtype==='civic_waste').minimal_user_values,'media.availability':'no','location.region':'Lazio','location.province':'Roma','location.municipality':'Roma'};m.pruneInactive();return m;}
test('only explicitly confirmed zone enters private payload; snapshot and reset are stable',()=>{
 const m=fixture(true);assert.equal(config.neighborhoodProposal,false);assert(!config.fields['location.neighborhood_label']);
 assert(!('neighborhood_label' in m.payload().location));m.neighborhood={label:'Aurelio',source:'google_neighborhood',confirmed:false};assert(!('neighborhood_label' in m.payload().location));
 m.neighborhood.confirmed=true;assert.equal(m.payload().location.neighborhood_label,'Aurelio');assert.equal(m.payload().location.neighborhood_confirmed,true);
 m.snapshot=m.payload();m.neighborhood.label='EUR';assert.equal(m.snapshot.location.neighborhood_label,'Aurelio');m.reset();assert.equal(m.neighborhood,null);
});
test('client payload with confirmed zone passes real V2 backend without exposing it as public geo',()=>{
 const m=fixture(true);m.neighborhood={label:'Aurelio',source:'user_proposed',confirmed:true};
 const out=JSON.parse(execFileSync(process.env.BA_PHP||'php',['tests/nf-ux-04-backend.php'],{input:JSON.stringify([m.payload()]),encoding:'utf8'}));
 assert.equal(out[0].error,null);assert.equal(out[0].normalized.location.neighborhood_label,'Aurelio');assert(!out[0].normalized.location.public_location_request.neighborhood);
});
