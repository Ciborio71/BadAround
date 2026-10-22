'use strict';
// Candidate full PHP templates + certified deployed Astra context. Every request intercepted; no staging writes.
const {chromium}=require(process.env.BA_PLAYWRIGHT_MODULE||'playwright'),fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict'),{execFileSync}=require('node:child_process');
const root=path.join(__dirname,'..'),out=process.env.BA_SCREENSHOT_DIR||path.join(root,'evidence/screenshots');fs.mkdirSync(out,{recursive:true});
const html=execFileSync(process.env.BA_PHP||'php',['ops/nf05c-render.php'],{encoding:'utf8'}),contract=JSON.parse(fs.readFileSync('wordpress/plugins/badaround-core/contracts/nf-conditional-v1.0.json'));
const results=[],requests=[],screens=[];
function contrast(a,b){const l=c=>c.match(/[\d.]+/g).slice(0,3).map(Number).map(v=>{v/=255;return v<=.04045?v/12.92:((v+.055)/1.055)**2.4;}).reduce((n,v,i)=>n+v*[.2126,.7152,.0722][i],0);return (Math.max(l(a),l(b))+.05)/(Math.min(l(a),l(b))+.05);}
(async()=>{const browser=await chromium.launch();try{for(const width of [320,390,768,1024,1440]){
 const page=await browser.newPage({viewport:{width,height:1000},reducedMotion:'reduce'});page.setDefaultTimeout(10000);const errors=[],payloads=[];page.on('pageerror',e=>errors.push(e.message));
 await page.route('**/*',route=>{const req=route.request(),u=new URL(req.url());requests.push({method:req.method(),path:u.pathname});
  if(req.method()==='POST'&&u.pathname.endsWith('/reports')){const p=req.postDataJSON();payloads.push(p);return route.fulfill({contentType:'application/json',body:JSON.stringify({status:'success',submission_id:p.submission_id,schema_version:p.schema_version,next_state:'moderation_pending'})});}
  assert.equal(req.method(),'GET','no unapproved outbound mutation');
  if(u.pathname==='/')return route.fulfill({contentType:'text/html',body:html});
  if(u.pathname.includes('/themes/astra/')){const file=u.pathname.endsWith('.css')?'evidence/astra-main.css':'evidence/astra-frontend.js';return route.fulfill({contentType:file.endsWith('.css')?'text/css':'application/javascript',body:fs.readFileSync(file)});}
  if(u.pathname.startsWith('/wp-content/themes/badaround-child/')){const file=path.join(root,'wordpress',u.pathname.slice('/wp-content/'.length));if(fs.existsSync(file))return route.fulfill({contentType:file.endsWith('.css')?'text/css':'application/javascript',body:fs.readFileSync(file)});}
  return route.abort();});
 async function geometry(name){const a=await page.evaluate(()=>{const shown=e=>!!e.getClientRects().length&&!e.closest('[hidden]');return {overflow:document.documentElement.scrollWidth>innerWidth+1,clipped:[...document.querySelectorAll('.ba-native input,.ba-native select,.ba-native textarea,.ba-native button')].filter(shown).filter(e=>{const r=e.getBoundingClientRect();return r.left< -1||r.right>innerWidth+1;}).map(e=>e.name||e.textContent)};});assert(!a.overflow,`${width}/${name} overflow`);assert.deepEqual(a.clipped,[],`${width}/${name} clipping`);results.push({width,state:name,result:'PASS'});}
 async function shot(name){if(name!=='keyboard-error')await page.evaluate(()=>window.scrollTo({top:0,behavior:'instant'}));await page.screenshot({path:path.join(out,`${width}-${name}.png`),fullPage:name!=='keyboard-error'});screens.push(`${width}-${name}.png`);}
 async function seed(subtype,step,extra={}){const row=contract.regression_oracle.find(r=>r.subtype===subtype);await page.evaluate(({row,step,extra})=>{const m=document.querySelector('[data-native-report]').nativeReport.model;m.values={...row.minimal_user_values,...extra,'media.availability':'no','reporter.social_visibility':'declined'};m.pruneInactive();m.step=step;const e=document.querySelector('[name="content.description"]');e.value=m.values['content.description']||'';e.dispatchEvent(new Event('input',{bubbles:true}));},{row,step,extra});}
 await page.goto('https://nf05c.invalid/');await page.waitForFunction(()=>!!document.querySelector('[data-native-report]')?.nativeReport).catch(async e=>{console.error(JSON.stringify({errors,body:await page.locator('body').innerText(),requests},null,2));await page.screenshot({path:path.join(out,'boot-failure.png'),fullPage:true});throw e;});
 await geometry('category');assert.equal(await page.locator('.ba-native-category-grid label').evaluateAll(es=>new Set(es.map(e=>Math.round(e.getBoundingClientRect().left))).size),width<=380?1:width<=900?2:3,'category density at each width');if(width===1440)await shot('category');
 const cards=page.locator('.ba-native-category-grid');assert.equal(await cards.locator('label:visible').count(),6);
 const radioColor=await cards.locator('label').first().evaluate(e=>({border:getComputedStyle(e,'::after').borderColor,bg:getComputedStyle(e).backgroundColor}));assert(contrast(radioColor.border,radioColor.bg)>=3,'unselected indicator contrast');
 await cards.locator('input[value=vehicle]').check();assert(await cards.locator('input[value=vehicle]').isChecked());
 await page.waitForFunction(()=>document.activeElement?.name==='ba-card-event.subtype');
 await geometry('subtype');if(width===320)assert.equal(await page.locator('[data-native-field="event.subtype"] label.ba-native-option-card:visible').evaluateAll(es=>new Set(es.map(e=>Math.round(e.getBoundingClientRect().left))).size),1,'long subtype names use full width at 320');if(width===320)await shot('category-subtype');
 await page.locator('[data-native-field="event.subtype"] input[value=vehicle_stolen]').check();await page.waitForFunction(()=>document.activeElement?.name==='ba-card-reporter.relationship');
 await page.locator('[data-native-field="reporter.relationship"] input[value=territory_observer]').check();await page.waitForFunction(()=>document.activeElement?.hasAttribute('data-native-next'));
 await page.locator('[data-native-next]').click();assert(await page.locator('[data-native-step="location"]').isVisible());await geometry('location');
 // Prepare a complex valid profile through the unchanged application model. Independently test every displayed screen.
 await seed('property_theft',2,{'property.access_method':'other','property.access_method_other_detail':'Accesso sintetico: attività, città e specifica Unicode.','property.stolen_item_categories':['other'],'property.stolen_item_categories_other_detail':'Oggetti esclusivamente sintetici del collaudo.','damage.status':'yes','damage.description':'Danno sintetico descritto solo per verificare il campo.'});
 const inputColors=await page.locator('[name="content.description"]').evaluate(e=>({border:getComputedStyle(e).borderColor,bg:getComputedStyle(e).backgroundColor}));assert(contrast(inputColors.border,inputColors.bg)>=3,'input boundary contrast');
 await geometry('complex-details');if([320,1440].includes(width))await shot('details');
 const danger=page.locator('[data-native-field="location.immediate_danger"]');assert(await danger.isVisible());
 // Real back/forward handlers, no SmartAddress bypass is required for details->media->contact.
 await page.locator('[data-native-next]').click();assert(await page.locator('[data-native-step="media"]').isVisible());await geometry('no-media');
 await page.locator('[data-native-next]').click();assert(await page.locator('[data-native-step="contact"]').isVisible());await geometry('contact');
 const pair=await page.evaluate(()=>{const box=p=>document.querySelector(`[data-native-field="${p}"]`).getBoundingClientRect().toJSON();return {email:box('reporter.email'),phone:box('reporter.phone')};});
 if(width>=768)assert(Math.abs(pair.email.y-pair.phone.y)<2,'email/phone same row');else assert(pair.phone.y>=pair.email.bottom-1,'mobile contacts stack');
 const linkColors=await page.locator('.ba-native a:visible').evaluateAll(es=>es.map(e=>({color:getComputedStyle(e).color})));for(const c of linkColors)assert(contrast(c.color,'rgb(242,248,249)')>=4.5,'privacy link contrast on pale panel');
 if(width<=390){for(const key of ['reporter.contact_preference','reporter.social_visibility']){const group=page.locator(`[data-native-field="${key}"] .ba-native-card-grid`);if(await group.isVisible())assert.equal(await group.locator('label:visible').evaluateAll(es=>new Set(es.map(e=>Math.round(e.getBoundingClientRect().left))).size),1,'long answers remain readable');}}

 if([390,1440].includes(width))await shot('contact');
 await page.locator('[data-native-next]').click();assert(await page.locator('[data-native-step="review"]').isVisible());await geometry('summary');
 assert(!/property\.access_method|badaround-report\/v2/.test(await page.locator('[data-native-review]').innerText()),'no internal keys in user review');
 if([320,1440].includes(width))await shot('summary');
 const semantic=await page.evaluate(()=>document.querySelector('[data-native-report]').nativeReport.model.payload());await page.locator('[data-native-submit]').click();await page.locator('[data-native-success]').waitFor({state:'visible'});assert.equal(payloads.length,1);assert.deepEqual(payloads[0],semantic);assert.equal(payloads[0].schema_version,'badaround-report/v2');assert.equal(payloads[0].reporter.social_visibility,'declined');assert.equal(Object.keys(payloads[0].consents).filter(k=>['truthfulness','media_rights','publication_rules','terms','privacy'].includes(k)&&payloads[0].consents[k]===true).length,5);
 await page.reload();await seed('animal_missing',2,{'animal.type':'dog'});
 await page.locator('[name="animal.type"]').focus();await page.locator('[name="animal.type"]').selectOption('other');await page.waitForFunction(()=>document.activeElement?.name==='animal.type_other_detail');
 const spec=page.locator('[name="animal.type_other_detail"]');await spec.fill('Animale sintetico — città e attività.');await geometry('altro');
 await seed('hazard_fire_or_smoke',2);const notice=page.locator('[data-native-emergency-notice]');assert(await notice.isVisible());assert.equal(await notice.count(),1);
 const dangerControl=page.locator('[data-native-field="location.immediate_danger"] input[value=unsure]');await dangerControl.check();await page.waitForFunction(()=>document.activeElement?.name!=='ba-card-location.immediate_danger');await page.waitForTimeout(200);
 const n=await notice.boundingBox(),header=await page.locator('.ba-site-header').evaluate(e=>e.getBoundingClientRect().bottom);assert(n.y>=header-1&&n.y+n.height<=1000,'emergency perceivable after autofocus');
 await geometry('emergency');if(width===390)await shot('emergency');
 // Altro/emergency representative screenshot, separate actual controls remain available.
 await seed('animal_missing',2,{'animal.type':'other','animal.type_other_detail':'Specifica sintetica Unicode: attività e città.','location.immediate_danger':'unsure'});await geometry('emergency-altro');if(width===390)await shot('emergency-altro');
 await seed('animal_missing',2,{'reward.status':'fixed','reward.amount':120,'reward.conditions':'Condizioni sintetiche, nessuna offerta reale.','reward.confirmed':true});await geometry('reward');
 assert(await page.locator('[data-native-topic="reward."]').isVisible());
 await seed('civic_waste',2,{'content.description':''});await page.locator('[data-native-next]').click();assert(await page.locator('[name="content.description"]').evaluate(e=>e===document.activeElement));assert.equal(await page.locator('[name="content.description"]').getAttribute('aria-invalid'),'true');assert(!(await page.locator('[data-native-errors]').isVisible()));
 await geometry('field-error');if(width===320)await shot('keyboard-error');
 const focus=await page.locator('[name="content.description"]').evaluate(e=>({r:e.getBoundingClientRect().toJSON(),header:document.querySelector('.ba-site-header').getBoundingClientRect().bottom,described:e.getAttribute('aria-describedby'),outline:getComputedStyle(e).outlineStyle,outlineWidth:parseFloat(getComputedStyle(e).outlineWidth),outlineColor:getComputedStyle(e).outlineColor,bg:getComputedStyle(e).backgroundColor}));assert.equal(focus.outline,'solid');assert(focus.outlineWidth>=3&&contrast(focus.outlineColor,focus.bg)>=3,'text input keyboard focus contrast');assert(focus.r.y>=focus.header,'error focus below header');assert(focus.described.includes('error'));
 await page.locator('[name="content.description"]').fill('Descrizione sintetica corretta.');assert.equal(await page.locator('[name="content.description"]').getAttribute('aria-invalid'),'false');
 const hidden=await page.locator('[data-native-step]:visible [data-native-field][hidden] input').evaluateAll(es=>es.every(e=>e.disabled||e.type==='hidden'));assert(hidden,'inactive controls excluded from keyboard');
 const c=await page.locator('[data-native-next]').evaluate(e=>({color:getComputedStyle(e).color,bg:getComputedStyle(e).backgroundColor}));assert(contrast(c.color,c.bg)>=4.5,'CTA contrast');
 assert.deepEqual(errors,[]);await page.close();console.log('PASS NF-FINAL-01',width,'all six screens, choice/edit, active details, focus, emergency, Altro, reward, errors, intercepted v2 payload');
 }
 fs.writeFileSync(path.join(out,'../responsive.json'),JSON.stringify({results,requests,screens,realSubmissions:0},null,2));assert.equal(results.filter(r=>r.state==='summary').length,5);
 }finally{await browser.close();}})().catch(e=>{console.error(e);process.exitCode=1;});
