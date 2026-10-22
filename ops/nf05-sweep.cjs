'use strict';
const {chromium,fs,assert,contract,paths,ck,checks,boot,set,seed}=require('./nf05-helpers.cjs');
(async()=>{const browser=await chromium.launch();try{const {context,page}=await boot(browser,1440);const errors=[];page.on('pageerror',e=>errors.push(e.message));
const result=await page.evaluate(contract=>{
 const root=document.querySelector('[data-native-report]'),{model:m,config:c}=root.nativeReport,paths=Object.keys(contract.matrix[0].fields),rows=[],differential=[],stress=[];let matrix=0,dependencies=0;
 const failures=[];function check(ok,label,detail){if(!ok){failures.push({label,...detail});throw Error(JSON.stringify(failures.at(-1)));}}
 function render(step=2){m.step=step;const el=document.querySelector('[name="content.description"]');el.value=m.values['content.description']||'';el.dispatchEvent(new Event('input',{bubbles:true}));}
 function reset(o){m.values={...o.minimal_user_values,'media.availability':'no'};m.pruneInactive();render();}
 function expr(e,row,seen){if(e.all)return e.all.every(x=>expr(x,row,seen));if(e.any)return e.any.some(x=>expr(x,row,seen));if(e.op==='active')return active(e.field,row,seen);if(!active(e.field,row,seen))return false;const v=m.values[e.field];return e.op==='eq'?v===e.value:e.op==='in'?e.values.includes(v):Array.isArray(v)&&v.includes(e.value);}
 function active(p,row,seen=new Set()){if(['schema_version','submission_id'].includes(p))return true;const r=row.fields[p];if(!r||r.state==='HIDDEN'||seen.has(p))return false;const next=new Set(seen);next.add(p);return r.state!=='CONDITIONAL'||expr(r.condition,row,next);}
 function activate(e){if(e.all)e.all.forEach(activate);else if(e.any)activate(e.any[0]);else if(e.op!=='active'){const r=m.rule(e.field);if(r?.condition)activate(r.condition);m.set(e.field,e.op==='contains'?[e.value]:e.value??(e.values.includes(m.effective(e.field))?m.effective(e.field):e.values[0]));}}
 function capture(){return {payload:m.payload(),active:paths.filter(p=>m.active(p)),required:paths.filter(p=>m.required(p))};}
 function agreement(label){render(5);const payload=m.payload(),keys=[...document.querySelectorAll('[data-native-review-field]')].map(e=>e.dataset.nativeReviewField);const get=p=>p.split('.').reduce((v,k)=>v?.[k],payload);
  const summaryAllowed=p=>!(p.startsWith('location.')&&p!=='location.exact_address'&&!c.fields[p]?.sourceField)&&(!p.startsWith('consents.')||p==='consents.truthfulness');
  const expected=Object.keys(c.fields).filter(p=>summaryAllowed(p)&&get(p)!==undefined&&m.active(p));check(JSON.stringify(keys.slice().sort())===JSON.stringify(expected.sort()),'summary/payload agreement',{label,expected,actual:keys});
  for(const p of Object.keys(c.fields))if(!m.active(p)){check(get(p)===undefined,'inactive payload',{label,field:p,actual:get(p)});check(!keys.includes(p),'inactive summary',{label,field:p});}
  return keys;
 }
 for(const oracle of contract.regression_oracle){const row=contract.matrix.find(r=>r.subtype===oracle.subtype);reset(oracle);check(!m.validate().length,'oracle validation',{subtype:row.subtype,actual:m.validate()});
  const visible=[],required=[],optional=[],hidden=[],conditional=[];
  for(const p of paths){const expected=active(p,row);check(m.active(p)===expected,'matrix active',{subtype:row.subtype,field:p,expected,actual:m.active(p)});matrix++;
   if(!c.fields[p])continue;
   const w=root.querySelector(`[data-native-field="${p}"]`);check(!!w,'wrapper exists',{subtype:row.subtype,field:p});
   const req=expected&&row.fields[p].required_when_active;check(m.required(p)===!!req,'required flag',{subtype:row.subtype,field:p,expected:req,actual:m.required(p)});
   if(expected){visible.push(p);(req?required:optional).push(p);const copy={...m.values};delete m.values[p];const e=m.validate();check(e.some(e=>e.field===p)===!!req,'required/optional missing validation',{subtype:row.subtype,field:p,expected:!!req,actual:e});m.values=copy;
   }else{hidden.push(p);check(w.hidden&&[...w.querySelectorAll('[data-native-input]')].every(e=>e.disabled),'inactive DOM disabled',{subtype:row.subtype,field:p});}
  }
  const summary=agreement(row.subtype);differential.push(capture());
  for(const danger of ['no','yes','unsure','no']){m.set('location.immediate_danger',danger);render(0);const n=root.querySelector('[data-native-emergency-notice]'),expected=row.emergency.automatic_subtype_notice||danger!=='no';check(n.hidden===!expected&&root.querySelectorAll('[data-native-emergency-notice]').length===1,'emergency sweep',{subtype:row.subtype,danger,expected,actual:!n.hidden});}
  // Every profile must actually refuse forward navigation and submission without its shared required description.
  reset(oracle);delete m.values['content.description'];render(2);root.querySelector('[data-native-next]').click();check(m.step===2,'required forward blocker',{subtype:row.subtype});render(5);root.querySelector('[data-native-submit]').click();check(!m.busy&&!m.snapshot,'required submission blocker',{subtype:row.subtype});
  reset(oracle);render(2);root.querySelector('[data-native-next]').click();check(m.step===3,'optional fields do not block forward',{subtype:row.subtype,step:m.step});root.querySelector('[data-native-back]').click();check(m.step===2,'back',{subtype:row.subtype});root.querySelector('[data-native-next]').click();check(m.step===3&&!root.querySelector('[aria-invalid="true"]'),'forward without stale errors',{subtype:row.subtype});
  for(const [p,r] of Object.entries(row.fields).filter(([,r])=>r.state==='CONDITIONAL')){reset(oracle);activate(r.condition);render(c.fields[p]?.step?Object.keys(c.steps).indexOf(c.fields[p].step):2);check(active(p,row)&&m.active(p),'dependency activation',{subtype:row.subtype,field:p});check(m.required(p)===!!r.required_when_active,'dependency requiredness',{subtype:row.subtype,field:p});agreement(row.subtype+' '+p);differential.push(capture());conditional.push(p);dependencies++;}
  rows.push({category:row.category,subtype:row.subtype,profile:row.profile,visible,required,optional,hidden,conditional,dependency_result:'PASS',emergency:row.emergency.automatic_subtype_notice?'AUTOMATIC':'DANGER_ONLY',damage:row.fields['damage.status'].state,summary,result:'PASS'});
 }
 // Stress preserve the old answers while changing the subtype through the actual control event.
 for(const [from,to] of [['vehicle_stolen','civic_waste'],['civic_waste','vehicle_stolen'],['vehicle_parked_damage','animal_missing'],['animal_missing','animal_sighted_or_found'],['hazard_fire_or_smoke','civic_waste']]){
  reset(contract.regression_oracle.find(o=>o.subtype===from));
  if(from==='vehicle_stolen'){m.set('vehicle.type','other');m.set('vehicle.type_other_detail','NF05_STALE_SPEC');m.set('reward.status','fixed');m.set('reward.amount','10');m.set('reward.conditions','NF05_STALE_REWARD');}
  if(from==='animal_missing'){m.set('reward.status','fixed');m.set('reward.amount','10');m.set('reward.conditions','NF05_STALE_REWARD');}
  const target=contract.matrix.find(r=>r.subtype===to);for(const [p,v] of [['event.category',target.category],['event.subtype',to]]){const el=root.querySelector(`[name="${p}"]`);el.value=v;el.dispatchEvent(new Event('change',{bubbles:true}));}
  for(const p of Object.keys(m.values))check(m.active(p),'stale stored value',{from,to,field:p});agreement(from+' -> '+to);check(!JSON.stringify(m.payload()).includes('NF05_STALE'),'stale marker payload',{from,to});check(!root.querySelector('[data-native-review]').textContent.includes('NF05_STALE'),'stale marker summary',{from,to});stress.push({from,to,result:'PASS'});
 }
 reset(contract.regression_oracle[0]);m.set('authority.status','yes');m.set('authority.type','other');m.set('authority.type_other_detail','NF05_STALE_AUTHORITY');render(2);m.set('authority.status','no');render(2);agreement('authority yes -> no');check(!m.values['authority.type']&&!m.values['authority.type_other_detail']&&!JSON.stringify(m.payload()).includes('NF05_STALE'),'authority transitive stale',{actual:m.values});stress.push({from:'authority yes:other',to:'authority no',result:'PASS'});
 return {rows,differential,matrix,dependencies,stress};
},contract);
fs.writeFileSync('evidence/subtype-oracles.json',JSON.stringify(result.rows,null,2));fs.writeFileSync('evidence/differential.json',JSON.stringify(result.differential));fs.writeFileSync('evidence/stress.json',JSON.stringify(result.stress,null,2));ck(result.matrix===2805&&result.dependencies===554,'33 browser DOM oracles / 2805 independent frozen comparisons / 554 dependencies');ck(!errors.length,'no JS errors');await context.close();
fs.writeFileSync('evidence/sweep-result.json',JSON.stringify({pass:true,matrix:result.matrix,dependencies:result.dependencies,subtypes:result.rows.length,checks},null,2));console.log('NF05_33_LIVE_ORACLES_2805_554_PASS');
}finally{await browser.close();}})().catch(e=>{fs.writeFileSync('evidence/nf05-sweep-failure.json',JSON.stringify({message:e.message,stack:e.stack},null,2));console.error(e);process.exitCode=1;});
