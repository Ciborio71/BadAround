'use strict';
const {chromium,webkit}=require(process.env.BA_PLAYWRIGHT_MODULE||'playwright');
const {execFileSync}=require('node:child_process'),fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
(async()=>{
 let html=execFileSync(process.env.BA_PHP||'php',[path.join(__dirname,'native-report-f16.php'),'--html'],{encoding:'utf8'});
 const prefix='/wordpress/themes/badaround-child/assets/js/native-report/';
 html=html.replace(`<script src="${prefix}wizard.js">`,`<script src="${prefix}location-map.js"></script><script src="${prefix}smart-address.js"></script><script src="${prefix}wizard.js">`).replace('"smartAddress":null','"smartAddress":{"key":"TEST","territories":[[2,0,"Lazio"],[3,2,"Roma"],[4,3,"Pomezia"]]}');
 const browser=process.env.BA_BROWSER_ENGINE==='webkit'?await webkit.launch({headless:true}):await chromium.launch({headless:true,...(process.env.BA_CHROMIUM?{executablePath:process.env.BA_CHROMIUM}:{}),args:['--no-sandbox','--disable-dev-shm-usage']});
 try{for(const width of [390,1440])for(const scenario of ['vehicle_112_anonymous','vehicle_pseudonym','property_theft']){
  const page=await browser.newPage({viewport:{width,height:900},reducedMotion:'reduce'});page.setDefaultTimeout(10000);const errors=[],requests=[];page.on('pageerror',e=>errors.push(e.message));
  await page.addInitScript(()=>{
   window.providerCalls=[];window.gpsCalls=0;
   const components=[['country','Italia','IT'],['administrative_area_level_1','Lazio','Lazio'],['administrative_area_level_2','Roma','RM'],['locality','Pomezia','Pomezia']].map(([type,longText,shortText])=>({types:[type],longText,shortText}));
   class Place{constructor(){this.addressComponents=components;this.formattedAddress='Via verificata, Pomezia RM, Italia';this.location={lat:()=>41.66,lng:()=>12.43};}async fetchFields(){providerCalls.push('details');}}
   class Widget extends HTMLElement{connectedCallback(){
    this.innerHTML='<input aria-label="Ricerca Google simulata" autocomplete="off"><button type="button">Via verificata, Pomezia</button>';
    this.querySelector('button').onclick=()=>{const event=new Event('gmp-select');event.placePrediction={toPlace:()=>new Place()};this.dispatchEvent(event);};
   }}customElements.define('test-google-autocomplete',Widget);
   window.google={maps:{importLibrary:async name=>{
    providerCalls.push(name);
    if(name==='places')return {Place,PlaceAutocompleteElement:class{constructor(){return document.createElement('test-google-autocomplete');}}};
    if(name==='maps')return {Map:class{constructor(canvas){canvas.textContent='Anteprima mappa Google simulata';}setCenter(){}}};
    if(name==='marker')return {AdvancedMarkerElement:class extends EventTarget{constructor(o){super();Object.assign(this,o);window.testMarker=this;}replaceChildren(node){this.content=node;}}};
    if(name==='geocoding')return {Geocoder:class{async geocode(){return {results:[{place_id:'private-google-id'}]};}}};
   }}};
   Object.defineProperty(navigator,'geolocation',{value:{getCurrentPosition(ok){gpsCalls++;ok({coords:{latitude:41.665,longitude:12.435}});}}});
  });
  await page.route('**/*',route=>{
   const url=new URL(route.request().url());
   if(route.request().method()==='POST'){
    const payload=route.request().postDataJSON();requests.push(payload);
    return route.fulfill({status:201,contentType:'application/json',body:JSON.stringify({status:'success',submission_id:payload.submission_id,schema_version:payload.schema_version,next_state:'moderation_pending',duplicate:false})});
   }
   if(url.pathname==='/')return route.fulfill({contentType:'text/html',body:html.replaceAll('staging.badaround.it','localhost')});
   const file=path.join(__dirname,'..',url.pathname);if(fs.existsSync(file))return route.fulfill({contentType:file.endsWith('.css')?'text/css':'application/javascript',body:fs.readFileSync(file)});return route.abort();
  });
  await page.goto('https://localhost/');
  await page.evaluate(()=>{const h=document.createElement('header');h.className='ba-site-header';h.style.cssText='position:fixed;top:0;left:0;right:0;height:88px;background:white;z-index:999';document.body.append(h);});
  const answers={'event.category':scenario==='property_theft'?'property':'vehicle','event.subtype':scenario==='property_theft'?'property_theft':'vehicle_stolen','reporter.relationship':'directly_involved','time.mode':'unknown','location.place_type':'road_sidewalk','location.immediate_danger':'no','vehicle.role':'involved','vehicle.type':'car','vehicle.plate_knowledge':'unknown','property.stolen_item_categories':['documents'],'property.access_method':'door_forced','content.description':'Test QA sintetico di compilazione, nessun evento reale.','damage.status':'no','witness.status':'none','authority.status':scenario==='vehicle_112_anonymous'?'yes':'no','media.availability':'no','reporter.public_identity_mode':scenario==='vehicle_pseudonym'?'pseudonym':'anonymous','reporter.pseudonym':'Sentinella QA','reporter.first_name':'QA','reporter.last_name':'BadAround','reporter.email':'qa@example.invalid','reporter.contact_preference':'none','consents.publication_rules':true,'consents.terms':true,'consents.privacy':true};
  for(let step=0;step<7;step++){
   assert.equal(await page.locator('[data-native-step]:visible').count(),1);
   if(step===1){await page.locator('[data-smart-start]').click();await page.locator('test-google-autocomplete button').click();await page.locator('[data-smart-confirmation]').waitFor({state:'visible'});}
   else if(step<6){
    for(let guard=0;guard<50;guard++){
     const next=await page.evaluate(answers=>{
      const root=document.querySelector('[data-native-report]'),model=root.nativeReport.model,section=root.querySelector('[data-native-step]:not([hidden])');
      for(const unit of section.querySelectorAll('[data-native-field],[data-beta-declarations]')){
       if(unit.closest('[hidden]'))continue;
       if(unit.hasAttribute('data-beta-declarations')){if(!unit.querySelector('input').checked)return {bundle:true};continue;}
       const path=unit.dataset.nativeField;if(answers[path]!==undefined&&JSON.stringify(model.effective(path))!==JSON.stringify(answers[path]))return {path,value:answers[path]};
      }return null;
     },answers);
     if(!next)break;
     if(next.bundle){await page.locator('[data-beta-confirm]').check();continue;}
     if(next.path==='authority.status'){await page.locator('[data-beta-authority]').selectOption(next.value==='yes'?'yes:public_service':next.value);continue;}
     const wrapper=page.locator(`[data-native-field="${next.path}"]`),control=wrapper.locator('[data-native-input]').first();
     const type=await control.evaluate(el=>({tag:el.tagName,type:el.type,hidden:el.hidden}));
     if(Array.isArray(next.value)){await wrapper.locator(`input[value="${next.value[0]}"]`).check();}
     else if(type.tag==='SELECT'&&type.hidden){await wrapper.locator(`.ba-native-card-grid input[value="${next.value}"]`).press('Enter');}
     else if(type.tag==='SELECT')await control.selectOption(next.value);
     else if(type.type==='checkbox')await control.setChecked(next.value);
     else await control.fill(next.value);
    }
   }
   const state=await page.evaluate(()=>{
    const section=document.querySelector('[data-native-step]:not([hidden])');
    return {hiddenRequired:[...document.querySelectorAll('input[required],select[required],textarea[required]')].filter(el=>el.hidden||el.closest('[hidden]')).map(el=>el.name||el.id),numbers:[...section.querySelectorAll('[data-native-question-number]')].filter(el=>!el.closest('[hidden]')).map(el=>Number(el.textContent.replace('.',''))),overflow:document.documentElement.scrollWidth>innerWidth+1};
   });
   assert.deepEqual(state.hiddenRequired,[]);assert(!state.overflow);assert.deepEqual(state.numbers,state.numbers.map((_,i)=>state.numbers[0]+i));
   if(step===3){const fits=await page.locator('[data-native-field="location.place_type"] select').evaluate(el=>{const s=getComputedStyle(el);return el.clientHeight-parseFloat(s.paddingTop)-parseFloat(s.paddingBottom)>=parseFloat(s.fontSize)*1.4;});assert(fits,'select has enough vertical text space');}
   if(step===5){assert.equal(await page.locator('.ba-native-final-statements input[type=checkbox]:visible').count(),1);assert.equal(await page.locator('[data-beta-confirm]').isChecked(),true);assert.equal(await page.locator('[data-beta-declarations] [data-native-question-number]').count(),0);const full=await page.locator('#ba-native-declarations-detail').evaluate(el=>{const r=el.getBoundingClientRect(),p=el.parentElement.getBoundingClientRect();return Math.abs(r.width-p.width)<2&&getComputedStyle(el).textAlign==='justify';});assert(full);}
   if(step===0){const bg=await page.locator('.ba-native-category-grid label:has(input:checked)').evaluate(el=>getComputedStyle(el).backgroundColor);assert.equal(bg,'rgb(243, 251, 252)');}
   if(process.env.BA_SCREENSHOT_DIR&&[0,3,5,6].includes(step)&&scenario==='vehicle_112_anonymous')await page.screenshot({path:path.join(process.env.BA_SCREENSHOT_DIR,`beta-${width}-${scenario}-${step}.png`),fullPage:true});
   if(step<6){await page.locator('[data-native-next]').click();assert.equal(await page.evaluate(()=>document.querySelector('[data-native-report]').nativeReport.model.step),step+1,scenario+' step '+step+' '+await page.evaluate(()=>{const r=document.querySelector('[data-native-report]');return JSON.stringify(r.nativeReport.model.validate(Object.keys(r.nativeReport.config.steps)[r.nativeReport.model.step]));}));}
  }
  await page.locator('[data-native-submit]').click();await page.locator('[data-native-success]').waitFor({state:'visible'});assert.equal(requests.length,1);assert.equal(requests[0].authority.reference,undefined);assert.equal(requests[0].reporter.public_identity_mode,scenario==='vehicle_pseudonym'?'pseudonym':'anonymous');
  const confirmation=page.locator('[data-native-success] h2');
  await page.waitForFunction(()=>{const h=document.querySelector('[data-native-success] h2'),r=h.getBoundingClientRect();return document.activeElement===h&&r.top>=88&&r.bottom<innerHeight;});
  assert.equal(await confirmation.textContent(),'Segnalazione inviata');assert(Number(await confirmation.evaluate(el=>getComputedStyle(el).fontWeight))>=700);
  if(process.env.BA_SCREENSHOT_DIR&&scenario==='vehicle_112_anonymous')await page.screenshot({path:path.join(process.env.BA_SCREENSHOT_DIR,`success-${width}.png`)});
  for(const key of ['truthfulness','media_rights','publication_rules','terms','privacy'])assert.equal(requests[0].consents[key],true,key);
  if(scenario==='vehicle_112_anonymous')assert.equal(requests[0].authority.type,'public_service');
  if(process.env.BA_PAYLOAD_DIR)fs.writeFileSync(path.join(process.env.BA_PAYLOAD_DIR,`beta-${width}-${scenario}.json`),JSON.stringify(requests[0],null,2));
  assert.deepEqual(errors,[]);console.log(`PASS ${width}px ${scenario}: visible flow, numbering, keyboard, conditional fields, anonymous/pseudonym, no media, synthetic 201`);await page.close();
 }}finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
