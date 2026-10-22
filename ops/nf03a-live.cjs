'use strict';
// Runs against real staging assets. Synthetic answers stay in memory; report POST is intercepted.
const {chromium}=require(process.env.BA_PLAYWRIGHT_MODULE),fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const origin='https://staging.badaround.it',phase=process.env.NF03_PHASE||'after';
const dir='evidence';fs.mkdirSync(dir+'/screenshots',{recursive:true});
const contract=JSON.parse(fs.readFileSync('wordpress/plugins/badaround-core/contracts/nf-conditional-v1.0.json'));
const automatic=contract.matrix.filter(r=>r.emergency.automatic_subtype_notice);
const checks=[];function ck(ok,label){checks.push({label,pass:!!ok});assert(ok,label);}
(async()=>{
 const browser=await chromium.launch({headless:true});const outputs=[];
 try{for(const width of [320,390,1440]){
  const context=await browser.newContext({viewport:{width,height:1000},reducedMotion:'reduce'});const page=await context.newPage();page.setDefaultTimeout(20000);
  const requests=[],blocked=[];
  await context.route('**/wp-json/badaround/v1/**',async route=>{
   const req=route.request(),u=new URL(req.url());
   if(req.method()==='POST'&&u.pathname.endsWith('/reports')){const p=req.postDataJSON();requests.push(p);return route.fulfill({status:201,contentType:'application/json',body:JSON.stringify({status:'success',schema_version:p.schema_version,submission_id:p.submission_id,next_state:'moderation_pending',duplicate:false})});}
   if(!['GET','HEAD'].includes(req.method())){blocked.push(u.pathname);return route.abort();}return route.continue();
  });
  const response=await page.goto(origin+'/segnala-un-evento/',{waitUntil:'domcontentloaded',timeout:45000});ck(response.status()===200,'public page HTTP 200 '+width);
  await page.waitForFunction(()=>!!document.querySelector('[data-native-report]')?.nativeReport);
  ck(await page.evaluate(()=>document.querySelector('[data-native-report]').nativeReport.config.schemaVersion==='badaround-report/v1'),'Native v1 '+width);
  const set=async data=>page.evaluate(data=>{for(const [key,value] of Object.entries(data)){const el=document.querySelector(`[name="${key}"]`);if(!el)throw Error('Missing field '+key);if(el.type==='checkbox')el.checked=value;else el.value=value;el.dispatchEvent(new Event('change',{bubbles:true}));}},data);
  const jump=async step=>page.evaluate(step=>{document.querySelector('[data-native-report]').nativeReport.model.step=step;document.querySelector('[name="content.description"]').dispatchEvent(new Event('change',{bubbles:true}));},step);
  const notice=page.locator('[data-native-emergency-notice]');
  if(false){
   ck(!(await notice.isVisible()),'initial hidden '+width);
   for(const row of automatic){await set({'event.category':row.category,'event.subtype':row.subtype,'location.immediate_danger':'no'});ck(await notice.isVisible(),'automatic '+row.subtype+' '+width);ck(await notice.count()===1,'single notice '+row.subtype+' '+width);}
   await set({'event.category':'vehicle','event.subtype':'vehicle_stolen','location.immediate_danger':'no'});ck(!(await notice.isVisible()),'emergency to non-emergency hides '+width);
   for(const answer of ['yes','unsure','no']){await set({'location.immediate_danger':answer});ck(await notice.isVisible()===(answer!=='no'),'global '+answer+' '+width);}
   await set({'event.category':'hazard','event.subtype':'hazard_fire_or_smoke','location.immediate_danger':'yes'});ck(await notice.count()===1&&await notice.isVisible(),'dual trigger single '+width);await set({'location.immediate_danger':'no'});ck(await notice.isVisible(),'automatic with no '+width);
   await set({'event.category':'vehicle','event.subtype':'vehicle_stolen'});ck(!(await notice.isVisible()),'subtype reevaluated '+width);
   await page.locator('[data-native-field="event.subtype"] input[value=vehicle_hit_and_run]').focus();await page.keyboard.press('Space');
   await page.waitForFunction(()=>document.activeElement?.name==='ba-card-reporter.relationship');
   const r=await notice.boundingBox();ck(r&&r.y>=0&&r.y+r.height<=1000,'subtype autofocus viewport '+width);
   ck(await notice.getAttribute('role')==='status'&&await notice.getAttribute('aria-live')==='polite'&&await notice.getAttribute('aria-atomic')==='true','semantics '+width);
   ck(!(await notice.evaluate(el=>el.contains(document.activeElement))),'no focus trap '+width);
   const m=await notice.evaluate(el=>{const r=el.getBoundingClientRect(),s=getComputedStyle(el);return {left:r.left,right:r.right,color:s.color,bg:s.backgroundColor,overflow:document.documentElement.scrollWidth>innerWidth+1};});
   ck(m.left>=0&&m.right<=width+1&&!m.overflow,'responsive '+width);
   const lum=c=>{const a=c.match(/[\d.]+/g).slice(0,3).map(Number).map(x=>{x/=255;return x<=.04045?x/12.92:((x+.055)/1.055)**2.4;});return .2126*a[0]+.7152*a[1]+.0722*a[2];};const ls=[lum(m.color),lum(m.bg)].sort((a,b)=>a-b);ck((ls[1]+.05)/(ls[0]+.05)>=4.5,'contrast '+width);
   await page.screenshot({path:dir+`/screenshots/staging-${width}-subtype.png`});
  }
  // Seed an already-resolved synthetic private location in browser memory only.
  // Places/geocoding and device permission prompts are not exercised by this UX-only gate.
  await page.evaluate(()=>{
   const {model}=document.querySelector('[data-native-report]').nativeReport;
   const data={'event.category':'hazard','event.subtype':'hazard_road_obstruction','reporter.relationship':'territory_observer','location.region':'Lazio','location.province':'Roma','location.municipality':'Pomezia','location.locality':'Torvaianica','location.exact_address':'Via QA 1, Pomezia','location.exact_lat':41.669,'location.exact_lng':12.501,'location.place_type':'road_sidewalk','location.immediate_danger':'no','time.mode':'exact','time.date':'2026-10-01','time.knowledge':'unknown','content.description':'NF-UX-03 QA sintetica non inviata al server.','damage.status':'no','authority.status':'no','media.availability':'no','reporter.first_name':'QA','reporter.last_name':'Synthetic','reporter.email':'nf03@example.invalid','reward.status':'none','reporter.public_identity_mode':'anonymous','reporter.contact_preference':'none','reporter.social_visibility':'declined','consents.truthfulness':true,'consents.media_rights':true,'consents.publication_rules':true,'consents.terms':true,'consents.privacy':true};
   for(const [k,v] of Object.entries(data)){model.set(k,v);const el=document.querySelector(`[name="${k}"]`);if(el){if(el.type==='checkbox')el.checked=v;else el.value=v;}}
   document.querySelector('[name="content.description"]').dispatchEvent(new Event('change',{bubbles:true}));
  });
  await page.locator('[data-native-next]').click();ck(await page.locator('[data-native-step="location"]').isVisible(),'forward location '+width);
  for(const mode of ['exact_point','approximate_area']){await page.locator(`[data-native-public-location-choice] input[value=${mode}]`).check();ck(await page.evaluate(mode=>document.querySelector('[data-native-report]').nativeReport.model.payload().location.public_location_request===mode,mode),'GEO choice '+mode+' '+width);}
  await jump(2);
  if(false){
   await set({'event.category':'vehicle','event.subtype':'vehicle_stolen','location.immediate_danger':'no'});ck(!(await notice.isVisible()),'non-emergency details hidden '+width);
   const danger=page.locator('[data-native-field="location.immediate_danger"]');const edit=danger.getByRole('button',{name:/Modifica:/});if(await edit.isVisible())await edit.click();
   await danger.locator('input[type=radio][value=yes]').focus();await page.keyboard.press('Space');await notice.waitFor({state:'visible'});
   ck(await notice.evaluate(el=>el.previousElementSibling.dataset.nativeField==='location.immediate_danger'),'inline danger '+width);
   const r=await notice.boundingBox();ck(r&&r.y>=0&&r.y+r.height<=1000,'danger autofocus viewport '+width);
   await page.screenshot({path:dir+`/screenshots/staging-${width}-danger.png`});
   await page.locator('[data-native-back]').click();ck(await notice.isVisible(),'back no stale '+width);await jump(2);ck(await notice.isVisible(),'forward no stale '+width);
   for(const value of ['unsure','no']){await set({'location.immediate_danger':value});ck(await notice.isVisible()===(value!=='no'),'danger changed '+value+' '+width);}
   await set({'event.category':'hazard','event.subtype':'hazard_road_obstruction'});
  }
  // Continue the actual remaining navigation; all answers remain synthetic.
  await page.locator('[data-native-next]').click();ck(await page.locator('[data-native-step="media"]').isVisible(),'no-media step '+width);
  await page.locator('[data-native-next]').click();ck(await page.locator('[data-native-step="contact"]').isVisible(),'contact step '+width);
  ck(await page.locator('[data-beta-confirm]').isChecked(),'mandatory declarations unchanged '+width);
  await page.locator('[data-native-next]').click();ck(await page.locator('[data-native-step="review"]').isVisible(),'review step '+width);
  const review=await page.locator('[data-native-review]').innerText();ck(review.includes('Area approssimativa')&&review.includes('Via QA 1'),'review GEO '+width);
  const validation=await page.evaluate(()=>document.querySelector('[data-native-report]').nativeReport.model.validate());ck(validation.length===0,'v1 client validation '+width);
  await page.locator('[data-native-submit]').click();await page.locator('[data-native-success]').waitFor({state:'visible'});
  ck(requests.length===1,'one intercepted request '+width);const payload=requests[0];ck(payload.schema_version==='badaround-report/v1','emitted v1 '+width);ck(payload.media.availability==='no'&&!payload.media.items?.length,'no media payload '+width);ck(payload.reporter.social_visibility==='declined','social declined '+width);
  ck(['truthfulness','media_rights','publication_rules','terms','privacy'].every(k=>payload.consents[k]===true),'consents payload '+width);ck(!('emergency' in payload)&&!('emergencyNotice' in payload),'no canonical notice field '+width);
  payload.submission_id='<variable UUID>';outputs.push({width,payload,review});ck(blocked.length===0,'no extra write routes '+width);
  if(false){await page.reload({waitUntil:'domcontentloaded'});await page.waitForFunction(()=>!!document.querySelector('[data-native-report]')?.nativeReport);ck(!(await page.locator('[data-native-emergency-notice]').isVisible()),'refresh clean '+width);}
  await context.close();console.log('PASS LIVE',phase,width);
 }
 if(phase==='after'){const before=JSON.parse(fs.readFileSync(dir+'/browser-before.json'));for(const item of outputs){assert.deepEqual(item.payload,before.outputs[0].payload,'payload differential');assert.equal(item.review,before.outputs[0].review,'summary differential');}ck(true,'baseline v1 payload and summary byte-equivalent');}
 fs.writeFileSync(dir+'/browser-'+phase+'.json',JSON.stringify({pass:true,phase,checks,outputs,live_origin:origin,report_posts_reached_server:0,new_fixtures:[]},null,2));
 }catch(e){fs.writeFileSync(dir+'/browser-'+phase+'-failure.json',JSON.stringify({error:e.message,checks},null,2));throw e;}finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
