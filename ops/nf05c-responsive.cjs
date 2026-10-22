'use strict';
// Explicitly authorized isolated responsive equivalence execution. No staging HTTP and no submissions.
const {chromium}=require(process.env.BA_PLAYWRIGHT_MODULE),fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict'),{execFileSync}=require('node:child_process');
const root=process.cwd(),out=path.join(root,'evidence'),contract=JSON.parse(fs.readFileSync('wordpress/plugins/badaround-core/contracts/nf-conditional-v1.0.json'));
fs.mkdirSync(out,{recursive:true});
const html=execFileSync('php',['ops/nf05c-render.php'],{encoding:'utf8'});fs.writeFileSync(path.join(out,'rendered.html'),html);
const profiles=[
 {name:'simple',subtype:'civic_waste',extra:{}},
 {name:'complex',subtype:'property_theft',extra:{'property.access_method':'other','property.access_method_other_detail':'Accesso sintetico descritto per verificare la leggibilità della specifica su uno schermo piccolo.','property.stolen_item_categories':['other'],'property.stolen_item_categories_other_detail':'Oggetti esclusivamente sintetici per il collaudo responsive.','damage.status':'yes','damage.description':'Descrizione sintetica del danno per verifica del controllo multilinea.'}},
 {name:'altro',subtype:'animal_missing',extra:{'animal.type':'dog'},activate:'animal'},
 {name:'emergency',subtype:'hazard_fire_or_smoke',extra:{'location.immediate_danger':'no','damage.status':'yes','damage.description':'Danno esclusivamente sintetico per collaudo responsive.'}},
 {name:'conditional',subtype:'vehicle_stolen',extra:{'reward.status':'fixed','reward.amount':123,'reward.expires_on':'2027-12-31','reward.confirmed':true,'reward.conditions':'Condizioni sintetiche per la verifica responsive, senza alcuna offerta reale.','authority.status':'no'},activate:'authority'}
];
const results=[],network=[];
(async()=>{const browser=await chromium.launch();try{
for(const width of [320,390,1440])for(const profile of profiles){
 const page=await browser.newPage({viewport:{width,height:1000}}),errors=[],checks=[];
 page.on('pageerror',e=>errors.push(e.message));
 await page.route('**/*',route=>{
  const req=route.request(),u=new URL(req.url());network.push({method:req.method(),path:u.pathname,local:true});
  assert.equal(req.method(),'GET','NO_SUBMISSIONS');
  if(u.hostname==='nf05c.invalid'&&u.pathname==='/')return route.fulfill({contentType:'text/html',body:html});
  if(u.pathname==='/wp-content/themes/astra/assets/js/minified/frontend.min.js')return route.fulfill({contentType:'application/javascript',body:fs.readFileSync('evidence/astra-frontend.js')});
  if(u.pathname==='/wp-content/themes/astra/assets/css/minified/main.min.css')return route.fulfill({contentType:'text/css',body:fs.readFileSync('evidence/astra-main.css')});
  if(u.pathname.startsWith('/wp-content/themes/badaround-child/')){
   const file=path.join(root,'wordpress',u.pathname.slice('/wp-content/'.length));
   if(fs.existsSync(file)&&fs.statSync(file).isFile())return route.fulfill({contentType:file.endsWith('.css')?'text/css':file.endsWith('.js')?'application/javascript':'application/octet-stream',body:fs.readFileSync(file)});
  }
  return route.abort();
 });
 try{
 await page.goto('https://nf05c.invalid/');await page.waitForFunction(()=>document.querySelector('[data-native-report]')?.nativeReport);
 assert.equal(await page.evaluate(()=>document.querySelector('[data-native-report]').nativeReport.config.schemaVersion),'badaround-report/v2');
 const oracle=contract.regression_oracle.find(r=>r.subtype===profile.subtype);
 // State preparation only: exact application model and render handlers remain unchanged. No custom CSS or markup.
 await page.evaluate(({oracle,extra})=>{const m=document.querySelector('[data-native-report]').nativeReport.model;m.values={...oracle.minimal_user_values,...extra,'media.availability':'no','reporter.social_visibility':'declined'};m.pruneInactive();m.step=2;document.querySelector('[name="content.description"]').value=m.values['content.description'];document.querySelector('[name="content.description"]').dispatchEvent(new Event('input',{bubbles:true}));},{oracle,extra:profile.extra});
 await page.waitForTimeout(450);
 async function geometry(label){
  const g=await page.evaluate(()=>{
   const visible=e=>!!e.getClientRects().length&&getComputedStyle(e).visibility!=='hidden';
   const controls=[...document.querySelectorAll('[data-native-report] input:not([type=hidden]),[data-native-report] select,[data-native-report] textarea,[data-native-report] button')].filter(visible);
   const clipped=controls.filter(e=>{const r=e.getBoundingClientRect();return r.width>0&&(r.left< -1||r.right>innerWidth+1);}).map(e=>({name:e.name||e.textContent.slice(0,60),rect:e.getBoundingClientRect().toJSON()}));
   const active=document.activeElement,r=active?.getBoundingClientRect();
   return {width:innerWidth,scrollWidth:document.documentElement.scrollWidth,clipped,focus:active?.name||active?.id,focusRect:r?.toJSON()};
  });
  assert(g.scrollWidth<=width+1,`${label}: horizontal overflow ${JSON.stringify(g)}`);assert.deepEqual(g.clipped,[],`${label}: clipped controls`);checks.push({label,...g});
 }
 await geometry('details-initial');
 if(profile.activate){
  if(profile.activate==='animal'){
   const edit=page.locator('[data-native-field="animal.type"] .ba-native-edit-choice');if(await edit.count()&&await edit.isVisible())await edit.click();
   await page.locator('[name="animal.type"]').selectOption('other');await page.locator('[name="animal.type"]').press('Tab');
  }else{
   const edit=page.locator('[data-native-field="authority.status"] .ba-native-edit-choice');if(await edit.count()&&await edit.isVisible())await edit.click();
   await page.locator('[data-beta-authority]').selectOption('yes:other');
  }
  const field=profile.activate==='animal'?'animal.type_other_detail':'authority.type_other_detail';
  await page.waitForFunction(field=>document.activeElement?.name===field,field);
  await page.waitForTimeout(500);
  const target=page.locator(`[name="${field}"]`);assert(await target.isVisible());
  let b=await target.boundingBox();assert(b.y>=0&&b.y+b.height<=1000,'auto-next dependent focus visible');
  const uncovered=await target.evaluate(e=>{const r=e.getBoundingClientRect();const at=document.elementFromPoint(r.left+r.width/2,r.top+Math.min(r.height/2,20));return at===e||e.contains(at);});assert(uncovered,'focus not covered by fixed elements');
  await target.fill('Specifica sintetica Unicode: città, attività e leggibilità su schermo piccolo.');await target.press('Tab');assert(!(await target.evaluate(e=>e===document.activeElement)),'keyboard exits dependent field');
  await geometry('dependent-after-auto-next');
 }
 if(profile.name==='emergency'){
  const edit=page.locator('[data-native-field="location.immediate_danger"] .ba-native-edit-choice');if(await edit.count()&&await edit.isVisible())await edit.click();
  await page.locator('[data-native-field="location.immediate_danger"] input[type=radio][value=unsure]').press('Space');await page.waitForTimeout(650);
  const autoNotice=await page.locator('[data-native-emergency-notice]').boundingBox();const headerBottom=await page.locator('.ba-site-header').evaluate(e=>e.getBoundingClientRect().bottom);
  assert(autoNotice.y>=headerBottom&&autoNotice.y+autoNotice.height<=1000,'emergency remains readable during existing auto-focus');checks.push({label:'emergency-auto-focus',rect:autoNotice,headerBottom});
  const notice=page.locator('[data-native-emergency-notice]');assert(await notice.isVisible());assert((await notice.innerText()).includes('112'));
  await notice.scrollIntoViewIfNeeded();await page.waitForTimeout(500);const b=await notice.boundingBox();assert(b.x>=0&&b.x+b.width<=width+1&&b.height>40,'emergency readable');
  checks.push({label:'emergency-notice',rect:b});
 }
 // Reach both CTAs by ordinary scroll/focus; their rectangles must not overlap.
 const next=page.locator('[data-native-next]');await next.scrollIntoViewIfNeeded();await next.focus();await page.waitForTimeout(400);
 const nb=await next.boundingBox(),bb=await page.locator('[data-native-back]').boundingBox();assert(nb.y>=0&&nb.y+nb.height<=1000&&nb.width>40,'CTA reachable');
 assert(!bb||nb.x+nb.width<=bb.x+1||bb.x+bb.width<=nb.x+1||nb.y+nb.height<=bb.y+1||bb.y+bb.height<=nb.y+1,'buttons do not overlap');
 await geometry('CTA');
 await page.keyboard.press('Shift+Tab');await page.keyboard.press('Tab');assert(await next.evaluate(e=>e===document.activeElement),'keyboard reaches CTA');
 // A visual panel includes the current detail, dependent field/notice and the real CTA.
 await page.screenshot({path:path.join(out,`${width}-${profile.name}.png`),fullPage:true});
 // Navigation real handlers: back->location, then details->media; Smart Address is deliberately not exercised here.
 await page.locator('[data-native-back]').click();assert.equal(await page.evaluate(()=>document.querySelector('[data-native-report]').nativeReport.model.step),1);
 await page.evaluate(()=>{const m=document.querySelector('[data-native-report]').nativeReport.model;m.step=2;document.querySelector('[name="content.description"]').dispatchEvent(new Event('input',{bubbles:true}));});
 await next.click();assert.equal(await page.evaluate(()=>document.querySelector('[data-native-report]').nativeReport.model.step),3,'forward navigation');
 await page.locator('[data-native-back]').click();assert.equal(await page.evaluate(()=>document.querySelector('[data-native-report]').nativeReport.model.step),2,'back navigation');
 await page.evaluate(()=>{const m=document.querySelector('[data-native-report]').nativeReport.model;m.step=5;document.querySelector('[name="content.description"]').dispatchEvent(new Event('input',{bubbles:true}));});
 await geometry('summary');assert(await page.locator('[data-native-review]').isVisible());
 assert(await page.locator('[data-native-submit]').isVisible());
 await page.locator('[data-native-submit]').scrollIntoViewIfNeeded();await page.locator('[data-native-submit]').focus();
 const sb=await page.locator('[data-native-submit]').boundingBox();assert(sb.y>=0&&sb.y+sb.height<=1000,'summary CTA reachable');
 assert.deepEqual(errors,[],'no runtime JS errors');
 results.push({width,profile:profile.name,subtype:profile.subtype,result:'PASS',checks});console.log('PASS',width,profile.name);
 }catch(e){results.push({width,profile:profile.name,subtype:profile.subtype,result:'FAIL',error:e.stack,errors,checks});await page.screenshot({path:path.join(out,`failure-${width}-${profile.name}.png`),fullPage:true});throw e;}
 finally{fs.writeFileSync(path.join(out,'responsive.json'),JSON.stringify({results,network,submissions:0},null,2));await page.close();}
}
assert.equal(results.length,15);console.log('NF05C_RESPONSIVE_PASS 15/15; zero submissions; no live staging HTTP');
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exitCode=1;});
