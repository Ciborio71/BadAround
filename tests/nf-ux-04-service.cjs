'use strict';
const {test}=require('node:test'),assert=require('node:assert/strict'),{execFileSync}=require('node:child_process'),path=require('node:path');
const {fixture,contract}=require('./nf-ux-04-model.cjs');
test('33 actual Native v2 payloads pass REST/Golden Path, SQL persistence, pending and retry',()=>{
 const payloads=contract.regression_oracle.map(row=>{const m=fixture(row);for(const [p,v] of Object.entries({'location.region':'Lazio','location.province':'Roma','location.municipality':'Pomezia','location.locality':'Torvaianica','location.area_label':'Pomezia'}))m.set(p,v);return m.payload();});
 const out=execFileSync(process.env.BA_PHP||'php',[path.join(__dirname,'nf-ux-04-service.php')],{input:JSON.stringify(payloads),encoding:'utf8',maxBuffer:10000000});
 const r=JSON.parse(out);assert(r.pass);assert.equal(r.payloads,33);assert.equal(r.assertions,231);
});
