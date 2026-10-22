'use strict';
const {chromium,fs,ck,checks,boot,set,seed,geography,contract}=require('./nf05-helpers.cjs');
(async()=>{const browser=await chromium.launch();const out=[];try{
 for(const [index,subtype] of ['civic_waste','property_theft','hazard_fire_or_smoke'].entries()){
 const {context,page}=await boot(browser,1440,true);const geo=await geography(page,index===0);await page.evaluate(()=>document.querySelector('[data-native-report]').nativeReport.model.reset());const row=contract.regression_oracle.find(o=>o.subtype===subtype);await seed(page,row,1);
 await page.evaluate(geo=>{const m=document.querySelector('[data-native-report]').nativeReport.model;for(const [p,v] of Object.entries(geo))m.set(p,v);document.querySelector('[name="content.description"]').dispatchEvent(new Event('change',{bubbles:true}));},geo);
 await set(page,{'content.description':`NF-UX-05 QA sintetica ${subtype}. Nessun evento reale. Verifica semantica Native v2, non pubblicare.`, 'reporter.first_name':'QA','reporter.last_name':'NFUX05','reporter.email':'nfux05@example.invalid','reporter.social_visibility':'declined'});
 if(subtype==='property_theft'){
  await page.evaluate(()=>{const m=document.querySelector('[data-native-report]').nativeReport.model;for(const [p,v] of Object.entries({'property.stolen_item_categories':['other'],'property.stolen_item_categories_other_detail':'Oggetto sintetico di collaudo','damage.status':'yes','damage.description':'Danno sintetico di verifica, nessun danno reale.','authority.status':'yes','authority.type':'other','authority.type_other_detail':'Servizio sintetico di collaudo'}))m.set(p,v);document.querySelector('[name="content.description"]').dispatchEvent(new Event('change',{bubbles:true}));});
 }
 if(subtype==='hazard_fire_or_smoke')await set(page,{'location.immediate_danger':'unsure','damage.status':'no','authority.status':'no'});
 const mode=index===1?'exact_point':'approximate_area';await page.locator(`[data-native-public-location-choice] input[value=${mode}]`).check();
 ck(await page.evaluate(()=>!document.querySelector('[data-native-report]').nativeReport.model.validate().length),'golden validation '+subtype);
 await page.locator('[data-native-next]').click();ck(await page.locator('[data-native-step="details"]').isVisible(),'details '+subtype);await page.locator('[data-native-next]').click();ck(await page.locator('[data-native-step="media"]').isVisible(),'no media '+subtype);await page.locator('[data-native-next]').click();await page.locator('[data-beta-confirm]').check();await page.locator('[data-native-next]').click();ck(await page.locator('[data-native-step="review"]').isVisible(),'review '+subtype);
 const state=await page.evaluate(()=>{const {model:m,config:c}=document.querySelector('[data-native-report]').nativeReport;return {payload:m.payload(),summary:[...document.querySelectorAll('[data-native-review-field]')].map(e=>e.dataset.nativeReviewField),inactive:Object.keys(c.fields).filter(p=>!m.active(p))};});
 ck(state.payload.schema_version==='badaround-report/v2'&&state.payload.reporter.social_visibility==='declined'&&!state.payload.media?.items?.length,'v2 social no media '+subtype);for(const p of state.inactive)ck(p.split('.').reduce((v,k)=>v?.[k],state.payload)===undefined&&!state.summary.includes(p),'no inactive '+subtype+' '+p);
 for(const p of ['truthfulness','media_rights','publication_rules','terms','privacy'])ck(state.payload.consents[p]===true,'consent '+subtype+' '+p);
 fs.writeFileSync(`evidence/golden-${index}-before.json`,JSON.stringify(state,null,2));
 const received=page.waitForResponse(r=>r.request().method()==='POST'&&new URL(r.url()).pathname.endsWith('/reports'));await page.locator('[data-native-submit]').click();const response=await received,body=await response.json();
 // Save accepted IDs immediately, even if a later gate fails. No automatic deletion or conversion.
 out.push({subtype,payload:state.payload,summary:state.summary,http:response.status(),response:body});fs.writeFileSync('evidence/golden05.json',JSON.stringify(out,null,2));
 ck(response.ok()&&body.status==='success'&&body.schema_version==='badaround-report/v2','REST v2 '+subtype);await page.locator('[data-native-success]').waitFor({state:'visible'});
 const retry=await page.evaluate(async payload=>{const c=document.querySelector('[data-native-report]').nativeReport.config;const r=await fetch(c.endpoint,{method:'POST',credentials:'omit',cache:'no-store',headers:{'Content-Type':'application/json',[c.markerHeader]:c.markerValue},body:JSON.stringify(payload)});return {http:r.status,body:await r.json()};},state.payload);out.at(-1).retry=retry;fs.writeFileSync('evidence/golden05.json',JSON.stringify(out,null,2));ck(retry.body.status==='success'&&retry.body.duplicate===true&&retry.body.event_id===body.event_id,'idempotent retry '+subtype);
 await page.screenshot({path:`evidence/screenshots/nf05-real-${subtype}.png`});console.log('NF05_REAL_V2_PENDING_CANDIDATE',subtype,'EVENT',body.event_id);await context.close();
 }
 fs.writeFileSync('evidence/golden-checks.json',JSON.stringify({pass:true,count:out.length,checks},null,2));
 }finally{await browser.close();}})().catch(e=>{fs.writeFileSync('evidence/nf05-golden-failure.json',JSON.stringify({message:e.message,stack:e.stack},null,2));console.error(e);process.exitCode=1;});
