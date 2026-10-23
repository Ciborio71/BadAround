'use strict';
// Source-backed browser fixture with mocked provider APIs. No external requests.
const {chromium,webkit}=require(process.env.BA_PLAYWRIGHT_MODULE||'playwright');
const {execFileSync}=require('node:child_process'),fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
(async()=>{
 let html=execFileSync(process.env.BA_PHP||'php',[path.join(__dirname,'native-report-f16.php'),'--html'],{encoding:'utf8'});
 const prefix='/wordpress/themes/badaround-child/assets/js/native-report/';
 html=html.replace(`<script src="${prefix}wizard.js">`,`<script src="${prefix}location-map.js"></script><script src="${prefix}smart-address.js"></script><script src="${prefix}wizard.js">`);
 html=html.replace('"smartAddress":null','"smartAddress":{"key":"TEST","territories":[[2,0,"Lazio"],[3,2,"Roma"],[4,3,"Pomezia"]]}');
 const browser=process.env.BA_BROWSER_ENGINE==='webkit'?await webkit.launch({headless:true}):await chromium.launch({headless:true,...(process.env.BA_CHROMIUM?{executablePath:process.env.BA_CHROMIUM}:{}),args:['--no-sandbox','--disable-dev-shm-usage']});
 try{for(const width of [320,390,768,1440]){
  const page=await browser.newPage({viewport:{width,height:850},reducedMotion:'reduce'});page.setDefaultTimeout(12000);const failures=[];page.on('pageerror',e=>failures.push(e.message));
  await page.addInitScript(()=>{
   window.providerCalls=[];window.gpsCalls=0;
   window.circles=[];
   class Circle {
    constructor(o){Object.assign(this,o);circles.push(this);this.node=document.createElementNS('http://www.w3.org/2000/svg','svg');this.node.setAttribute('aria-label','Anteprima provider simulato: cerchio in metri');this.node.style.cssText='display:block;width:100%;height:100%;position:absolute;inset:0;pointer-events:none';this.node.innerHTML='<circle cx="50%" cy="50%" fill="#087f8c22" stroke="#087f8c" stroke-width="2"/>';o.map.canvas.append(this.node);this.draw();}
    draw(){if(!this.node||!this.map)return;const metersPerPixel=156543.03392*Math.cos(this.center.lat*Math.PI/180)/2**this.map.zoom;this.node.querySelector('circle').setAttribute('r',String(this.radius/metersPerPixel));}
    setCenter(c){this.center=c;this.draw();}setRadius(r){this.radius=r;this.draw();}setMap(m){this.map=m;this.node.hidden=!m;this.node.style.display=m?'block':'none';this.draw();}getBounds(){return {radius:this.radius,center:this.center};}
   }

   const components=[['country','Italia','IT'],['administrative_area_level_1','Lazio','Lazio'],['administrative_area_level_2','Roma','RM'],['locality','Pomezia','Pomezia']].map(([type,longText,shortText])=>({types:[type],longText,shortText}));
   class Place{constructor(){this.addressComponents=components;this.formattedAddress='Via verificata, Pomezia RM, Italia';this.location={lat:()=>41.66,lng:()=>12.43};}async fetchFields(){providerCalls.push('details');}}
   class Widget extends HTMLElement{connectedCallback(){
    this.innerHTML='<input aria-label="Ricerca Google simulata" autocomplete="off"><button type="button">Via verificata, Pomezia</button>';
    this.querySelector('button').onclick=()=>{const event=new Event('gmp-select');event.placePrediction={toPlace:()=>new Place()};this.dispatchEvent(event);};
   }}customElements.define('test-google-autocomplete',Widget);
   window.google={maps:{Circle,importLibrary:async name=>{
    providerCalls.push(name);
    if(name==='places')return {Place,PlaceAutocompleteElement:class{constructor(){return document.createElement('test-google-autocomplete');}}};
    if(name==='maps')return {Map:class{constructor(canvas,o){this.canvas=canvas;this.zoom=o.zoom;canvas.style.position='relative';canvas.textContent='Provider Google simulato — nessuna richiesta esterna';window.testMap=this;}setCenter(p){this.center=p;}setZoom(z){this.zoom=z;circles.forEach(c=>c.draw());}fitBounds(b){this.zoom=Math.log2(156543.03392*Math.cos(b.center.lat*Math.PI/180)*Math.min(this.canvas.clientWidth,this.canvas.clientHeight)/(b.radius*2.5));this.setZoom(this.zoom);}}};
    if(name==='marker')return {AdvancedMarkerElement:class extends EventTarget{constructor(o){super();Object.assign(this,o);window.testMarker=this;}replaceChildren(node){this.content=node;}}};
    if(name==='geocoding')return {Geocoder:class{async geocode(){return {results:[{place_id:'private-google-id'}]};}}};
   }}};
   Object.defineProperty(navigator,'geolocation',{value:{getCurrentPosition(ok,fail){gpsCalls++;if(window.denyGPS)fail({code:1});else ok({coords:{latitude:41.665,longitude:12.435}});}}});
  });
  await page.route('**/*',route=>{
   const url=new URL(route.request().url());if(url.pathname==='/')return route.fulfill({contentType:'text/html',body:html.replaceAll('staging.badaround.it','localhost')});
   const file=path.join(__dirname,'..',url.pathname);if(fs.existsSync(file))return route.fulfill({contentType:file.endsWith('.css')?'text/css':'application/javascript',body:fs.readFileSync(file)});return route.abort();
  });
  await page.goto('https://localhost/');assert.deepEqual(failures,[]);assert.equal(await page.evaluate(()=>providerCalls.length),0);
  for(const [field,value] of [['event.category','vehicle'],['event.subtype','vehicle_stolen'],['reporter.relationship','directly_involved']])await page.evaluate(([name,value])=>{const el=document.querySelector(`[name="${name}"]`);el.value=value;el.dispatchEvent(new Event('input',{bubbles:true}));},[field,value]);
  // The model's actual step validation remains active; pick the known relationship option.
  await page.evaluate(()=>{const el=document.querySelector('[name="reporter.relationship"]');el.value=[...el.options].find(o=>o.value)?.value;el.dispatchEvent(new Event('input',{bubbles:true}));});
  await page.locator('[data-native-next]').click();await page.locator('[data-smart-start]').click();
  await page.getByLabel('Ricerca Google simulata').fill('testo non selezionato');await page.getByLabel('Ricerca Google simulata').press('Enter');
  await page.locator('[data-native-next]').click();assert(await page.locator('[data-native-step="location"]').isVisible());assert.equal(await page.locator('[name="location.exact_address"]').inputValue(),'');
  await page.locator('test-google-autocomplete button').click();await page.locator('[data-smart-map-panel]').waitFor({state:'visible'});
  // GEO-PUBLIC-03: one authoritative choice, keyboard-operable, independent of private point.
  const choice=page.locator('[data-native-public-location-choice]');
  assert(await choice.isVisible());
  assert(await choice.getByRole('radio',{name:/Punto esatto/}).isChecked());
  await choice.getByRole('radio',{name:/Area approssimata/}).focus();
  await page.keyboard.press('Space');
  assert(await choice.getByRole('radio',{name:/Area approssimata/}).isChecked());
  assert.equal(await page.evaluate(()=>document.querySelector('[data-native-public-location-choice] input:checked').value),'approximate_area');
  assert.equal(await page.evaluate(()=>document.querySelector('[data-native-config]').textContent.includes('public-location-request-v2')),true);
  assert(!(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1)));
  const radius=page.locator('#ba-public-location-radius');assert.equal(await radius.inputValue(),'500');assert(await radius.getAttribute('required')!==null);
  for(const r of [50,100,250,500,1000]){
   await radius.selectOption(String(r));
   assert.deepEqual(await page.evaluate(()=>({radius:circles.at(-1).radius,center:circles.at(-1).center,payload:document.querySelector('[data-native-report]').nativeReport.model.payload().location.public_location_request})),{radius:r,center:{lat:41.66,lng:12.43},payload:{mode:'approximate_area',radius_m:r}});
   const zoomBefore=await page.evaluate(()=>testMap.zoom);await page.evaluate(()=>testMap.setZoom(testMap.zoom+1));assert.equal(await page.evaluate(()=>circles.at(-1).radius),r);await page.evaluate(z=>testMap.setZoom(z),zoomBefore);
  }
  await radius.selectOption('500');await radius.focus();await page.keyboard.press('ArrowUp');await page.keyboard.press('Tab');assert.equal(await radius.inputValue(),'250');
  for(const r of [50,1000]){await radius.selectOption(String(r));await choice.getByRole('radio',{name:/Punto esatto/}).check();assert(!(await radius.isVisible()));assert(await radius.isDisabled());assert.equal(await page.evaluate(()=>circles.at(-1).map),null);assert.deepEqual(await page.evaluate(()=>document.querySelector('[data-native-report]').nativeReport.model.payload().location.public_location_request),{mode:'exact_point'});await choice.getByRole('radio',{name:/Area approssimata/}).check();assert.equal(await radius.inputValue(),'500');}
  await radius.selectOption('100');assert.equal(await page.evaluate(()=>circles.at(-1).radius),100);await radius.selectOption('500');
  await radius.focus();assert(await radius.evaluate(el=>document.activeElement===el));
  assert(!(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1)));
  const evidence=process.env.BA_EVIDENCE_DIR||'/tmp/geo-area-01-browser';fs.mkdirSync(evidence,{recursive:true});await page.screenshot({path:path.join(evidence,`radius-${width}.png`),fullPage:true});
  assert.equal(await page.locator('[name="location.municipality"]').inputValue(),'Pomezia');assert.equal(await page.evaluate(()=>gpsCalls),0);
  await page.locator('[data-smart-geolocate]').click();await page.waitForFunction(()=>document.querySelector('[name="location.exact_lat"]').value==='41.665');
  await page.waitForFunction(()=>{
   const address=document.querySelector('[data-smart-confirmation]'),map=document.querySelector('[data-smart-map-panel]'),rect=address.getBoundingClientRect();
   return document.activeElement===address&&map.nextElementSibling===address&&rect.top>=15&&rect.bottom<innerHeight;
  }).catch(async e=>{console.error(await page.evaluate(()=>({focus:document.activeElement.outerHTML,rect:document.querySelector('[data-smart-confirmation]').getBoundingClientRect().toJSON(),height:innerHeight,errors:document.querySelector('[data-smart-map-status]').textContent})));throw e;});
  assert.match(await page.locator('[data-smart-address-label]').textContent(),/Via verificata/);
  assert(await choice.getByRole('radio',{name:/Area approssimata/}).isChecked());
  assert.equal(await page.evaluate(()=>gpsCalls),1);assert.equal(await page.evaluate(()=>testMarker.content.style.backgroundColor),'rgb(22, 119, 232)');assert(await page.evaluate(()=>testMarker.content.querySelector('svg').outerHTML===document.querySelector('.ba-native-category-grid input:checked').closest('label').querySelector('svg').outerHTML));
  await page.evaluate(()=>{testMarker.position={lat:41.667,lng:12.437};testMarker.dispatchEvent(new Event('gmp-dragend'));});await page.waitForFunction(()=>document.querySelector('[name="location.exact_lat"]').value==='41.667');
  assert.deepEqual(await page.evaluate(()=>circles.at(-1).center),{lat:41.667,lng:12.437});
  const verifiedBeforeDenial=await page.locator('[name="location.exact_lat"]').inputValue();
  await page.evaluate(()=>{window.denyGPS=true;});await page.locator('[data-smart-geolocate]').click();
  await page.waitForFunction(()=>document.querySelector('[data-smart-map-status]').textContent.includes('negato'));
  assert(await choice.getByRole('radio',{name:/Area approssimata/}).isChecked());
  assert.equal(await page.locator('[data-smart-confirmation]').isVisible(),true);assert.equal(await page.locator('[name="location.exact_lat"]').inputValue(),verifiedBeforeDenial);assert(await page.locator('[data-smart-map-panel]').isVisible());
  await page.evaluate(()=>{window.denyGPS=false;});await page.locator('[data-smart-geolocate]').click();await page.waitForFunction(()=>document.querySelector('[name="location.exact_lat"]').value==='41.665');
  assert(!(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1)));assert.deepEqual(failures,[]);
  await page.evaluate(()=>{const el=document.querySelector('[name="time.mode"]');el.value='unknown';el.dispatchEvent(new Event('input',{bubbles:true}));});
  await page.locator('[data-native-next]').click();assert(await page.locator('[data-native-step="details"]').isVisible());
  await page.locator('[data-native-back]').click();assert(await choice.isVisible());
  assert(await choice.getByRole('radio',{name:/Area approssimata/}).isChecked());
  await choice.getByRole('radio',{name:/Punto esatto/}).check();
  assert(await choice.getByRole('radio',{name:/Punto esatto/}).isChecked());
  await choice.getByRole('radio',{name:/Area approssimata/}).check();
  assert(await choice.getByRole('radio',{name:/Area approssimata/}).isChecked());
  console.log(`PASS ${width}px: all five circles, zoom invariance, live radius, stale clear, keyboard, center follow, GPS denial and responsive rendering`);await page.close();
 }}finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
