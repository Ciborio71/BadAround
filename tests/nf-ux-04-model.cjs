'use strict';
const {test}=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),{execFileSync}=require('node:child_process');
const php=process.env.BA_PHP||'php',root=path.join(__dirname,'..');
const config=JSON.parse(execFileSync(php,[path.join(__dirname,'native-report-f16.php'),'--config'],{encoding:'utf8'}));
const contract=JSON.parse(fs.readFileSync(path.join(root,'wordpress/plugins/badaround-core/contracts/nf-conditional-v1.0.json')));
const {Model}=require('../wordpress/themes/badaround-child/assets/js/native-report/model.js');
function fixture(row){const m=new Model(config);m.values={...row.minimal_user_values};m.values['media.availability']='no';m.pruneInactive();return m;}
function backend(cases){return JSON.parse(execFileSync(php,[path.join(__dirname,'nf-ux-04-backend.php')],{input:JSON.stringify(cases),encoding:'utf8',maxBuffer:100*1024*1024}));}
const paths=Object.keys(contract.matrix[0].fields),get=(p,key)=>key.split('.').reduce((v,k)=>v?.[k],p);
const models=contract.regression_oracle.map(fixture),results=backend(models.map(m=>m.payload()));
test('33 oracle / 2805 matrix pairs agree with frozen PHP, including payload normalization',()=>{
 let count=0,damage={REQUIRED:0,OPTIONAL:0,HIDDEN:0};
 for(const [i,m] of models.entries()){
  const b=results[i],row=contract.matrix[i];assert.equal(b.error,null,row.subtype+JSON.stringify(b.error));assert.equal(m.payload().schema_version,'badaround-report/v2');
  assert.deepEqual(m.payload(),b.normalized,row.subtype+' serialization');
  for(const path of paths){assert.equal(m.active(path),b.active.includes(path),row.subtype+'/'+path);assert.equal(m.required(path),b.required.includes(path),row.subtype+'/'+path+' required');count++;}
  assert.deepEqual(m.validate(),[],row.subtype+' local validation');damage[row.fields['damage.status'].state]++;
 }
 assert.equal(count,2805);assert.deepEqual(damage,{REQUIRED:2,OPTIONAL:14,HIDDEN:17});
});
test('all active required fields block locally when omitted; all optional fields may be absent',()=>{
 for(const [i,m] of models.entries())for(const [p,f] of Object.entries(config.fields))if(m.active(p)){
  const saved=m.values[p];delete m.values[p];const errors=m.validate();
  assert.equal(errors.some(e=>e.field===p),m.required(p),contract.matrix[i].subtype+'/'+p);m.values[p]=saved;
 }
});
test('8 Altro specifications: activation, limits, requiredness, stale removal and exact canonical key',()=>{
 const cases=[];
 for(const [key,spec] of Object.entries(contract.fields).filter(([,f])=>f.source_field)){
  const i=contract.matrix.findIndex(r=>r.fields[key].state!=='HIDDEN'),m=fixture(contract.regression_oracle[i]);
  // Establish all parents using the frozen condition tree, without subtype-specific JS rules.
  function activate(expr){if(expr.all)expr.all.forEach(activate);else if(expr.any)activate(expr.any[0]);else if(expr.op!=='active'){const rule=m.rule(expr.field);if(rule?.condition)activate(rule.condition);m.set(expr.field,expr.op==='contains'?[expr.value]:expr.value??(expr.values.includes(m.effective(expr.field))?m.effective(expr.field):expr.values[0]));}}
  activate(m.rule(key).condition);assert(m.active(key));assert(m.required(key));assert(m.validate().some(e=>e.field===key));
  m.set(key,'Specifica sintetica');assert.equal(get(m.payload(),key),'Specifica sintetica');cases.push(m.payload());
  m.set(key,'x'.repeat(spec.constraints.max_length+1));assert(m.validate().some(e=>e.field===key&&e.code==='value_too_long'));
  for(const bad of ['\nprova','<b>testo</b>','\u200b']){m.set(key,bad);assert(m.validate().some(e=>e.field===key),key+' plain text');}
  m.set(key,'Specifica sintetica');const parent=spec.source_field;const def=config.fields[parent];const alternate=def.constraints.enum?.find(v=>v!=='other')||def.constraints.item_enum.find(v=>v!=='other');
  m.set(parent,def.type==='array'?[alternate]:alternate);assert(!m.active(key));assert.equal(m.values[key],undefined);assert.equal(get(m.payload(),key),undefined);cases.push(m.payload());
 }
 const b=backend(cases);for(const [i,r] of b.entries())assert.deepEqual(r.normalized,cases[i]);
});
test('subtype and category changes clear old active state while preserving GEO and Social',()=>{
 const m=fixture(contract.regression_oracle.find(r=>r.subtype==='animal_missing'));
 m.set('reporter.social_visibility','requested');m.set('animal.name','STALE ANIMAL');m.set('reward.status','fixed');m.set('reward.amount',100);m.set('reward.conditions','STALE REWARD');
 m.set('event.category','hazard');m.set('event.subtype','hazard_fire_or_smoke');
 assert.equal(m.values['animal.name'],undefined);assert.equal(m.values['reward.amount'],undefined);assert.equal(m.payload().reporter.social_visibility,'requested');assert.equal(m.payload().location.exact_address,'Via QA sintetica 1');
 for(const row of contract.regression_oracle){m.set('event.category',row.minimal_user_values['event.category']);m.set('event.subtype',row.subtype);for(const p of Object.keys(m.values))assert(m.active(p),p);}
});
module.exports={fixture,config,contract,backend,paths};
test('every conditional dependency across 33 profiles agrees with backend after activation and stale clearing',()=>{
 const variants=[],expected=[];
 for(const oracle of contract.regression_oracle)for(const [p,rule] of Object.entries(contract.matrix.find(r=>r.subtype===oracle.subtype).fields).filter(([,r])=>r.state==='CONDITIONAL')){
  const m=fixture(oracle);
  function activate(expr){if(expr.all)expr.all.forEach(activate);else if(expr.any)activate(expr.any[0]);else if(expr.op!=='active'){const rule=m.rule(expr.field);if(rule?.condition)activate(rule.condition);m.set(expr.field,expr.op==='contains'?[expr.value]:expr.value??(expr.values.includes(m.effective(expr.field))?m.effective(expr.field):expr.values[0]));}}
  activate(rule.condition);variants.push(m.payload());expected.push({active:paths.filter(k=>m.active(k)),required:paths.filter(k=>m.required(k))});
 }
 const b=backend(variants);for(const [i,r] of b.entries()){assert.deepEqual(r.active,expected[i].active,'active '+i);assert.deepEqual(r.required,expected[i].required,'required '+i);assert.deepEqual(r.normalized,variants[i],'serialization '+i);}
 assert(variants.length>300);
});
