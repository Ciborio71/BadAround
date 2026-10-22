'use strict';
const {chromium}=require(process.env.BA_PLAYWRIGHT_MODULE),fs=require('node:fs'),assert=require('node:assert/strict');
const origin='https://staging.badaround.it',contract=JSON.parse(fs.readFileSync('wordpress/plugins/badaround-core/contracts/nf-conditional-v1.0.json')),paths=Object.keys(contract.matrix[0].fields),phase=process.argv[2]||'qa';
fs.mkdirSync('evidence/screenshots',{recursive:true});const checks=[];function ck(ok,label){checks.push({label,pass:!!ok});assert(ok,label);}
const position={latitude:41.6198,longitude:12.4616};
async function boot(browser,width,real=false){
 const context=await browser.newContext({viewport:{width,height:1000},reducedMotion:'reduce',geolocation:position,permissions:['geolocation']});const page=await context.newPage();page.setDefaultTimeout(30000);
 await context.route('**/wp-json/badaround/v1/**',route=>{const req=route.request();if(!['GET','HEAD'].includes(req.method())&&!(real&&new URL(req.url()).pathname.endsWith('/reports')))throw Error('Unexpected staging write blocked: '+req.method()+' '+new URL(req.url()).pathname);return route.continue();});
 const response=await page.goto(origin+'/segnala-un-evento/',{waitUntil:'domcontentloaded',timeout:60000});if(response.status()!==200){fs.writeFileSync('evidence/http-diagnostic.json',JSON.stringify({status:response.status(),url:page.url(),title:await page.title(),body:(await page.locator('body').innerText()).slice(0,3000)},null,2));await page.screenshot({path:'evidence/screenshots/http-diagnostic.png'});}ck(response.status()===200,'Native HTTP '+response.status()+' '+width);await page.waitForFunction(()=>!!document.querySelector('[data-native-report]')?.nativeReport);ck(await page.evaluate(()=>document.querySelector('[data-native-report]').nativeReport.config.schemaVersion==='badaround-report/v2'),'explicit v2 '+width);
 return {context,page};
}
async function set(page,values){await page.evaluate(values=>{for(const [p,v] of Object.entries(values)){const el=document.querySelector(`[name="${p}"]`);if(el.type==='checkbox')el.checked=v;else el.value=v;el.dispatchEvent(new Event('change',{bubbles:true}));}},values);}
async function seed(page,row,step=2){await page.evaluate(({row,step})=>{const {model:m}=document.querySelector('[data-native-report]').nativeReport;m.values={...row.minimal_user_values,'media.availability':'no'};m.pruneInactive();m.step=step;const el=document.querySelector('[name="content.description"]');el.value=m.values['content.description'];el.dispatchEvent(new Event('input',{bubbles:true}));},{row,step});}
async function geography(page,search){
 await seed(page,contract.regression_oracle.find(r=>r.subtype==='animal_missing'),1);
 if(search){
  await page.locator('[data-smart-start]').click();const input=page.locator('gmp-place-autocomplete');await input.waitFor({state:'visible'});await input.click();await page.keyboard.type('Torvaianica RM Italia');
  await page.waitForTimeout(1800);await input.press('ArrowDown');await input.press('Enter');
  await page.waitForFunction(()=>document.querySelector('[data-smart-confirmation]')?.hidden===false,{},{timeout:30000});
  ck(await page.evaluate(()=>document.querySelector('[data-native-report]').nativeReport.model.effective('location.municipality')==='Pomezia'),'Smart Address canonical Pomezia');
 }
 await page.locator('[data-smart-geolocate]').click();
 await page.waitForFunction(pos=>{const m=document.querySelector('[data-native-report]').nativeReport.model;return Math.abs(Number(m.effective('location.exact_lat'))-pos.latitude)<1e-7&&Math.abs(Number(m.effective('location.exact_lng'))-pos.longitude)<1e-7&&!document.querySelector('[data-smart-map-panel]').hidden;},position,{timeout:45000});
 const geo=await page.evaluate(()=>{const m=document.querySelector('[data-native-report]').nativeReport.model;return Object.fromEntries(Object.entries(m.values).filter(([p])=>p.startsWith('location.')));});
 ck(geo['location.region']==='Lazio'&&geo['location.province']==='Roma'&&geo['location.municipality']==='Pomezia','device geolocation canonical territory');ck(geo['location.exact_address']&&Number(geo['location.exact_lat'])===position.latitude&&Number(geo['location.exact_lng'])===position.longitude,'exact private point preserved');
 for(const mode of ['exact_point','approximate_area']){await page.locator(`[data-native-public-location-choice] input[value=${mode}]`).check();ck(await page.evaluate(mode=>document.querySelector('[data-native-report]').nativeReport.model.payload().location.public_location_request===mode,mode),'public request '+mode);}
 await page.screenshot({path:'evidence/screenshots/live-geo-'+phase+'.png'});return geo;
}
module.exports={chromium,fs,assert,contract,paths,ck,checks,boot,set,seed,geography};
