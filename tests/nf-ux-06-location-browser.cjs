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
  const page=await browser.newPage({viewport:{width,height:850},reducedMotion:'reduce'});page.setDefaultTimeout(8000);const failures=[];page.on('pageerror',e=>failures.push(e.message));
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
   Object.defineProperty(navigator,'geolocation',{value:{getCurrentPosition(ok,fail){gpsCalls++;if(window.denyGPS)fail({code:1});else ok({coords:{latitude:41.665,longitude:12.435}});}}});
  });
  await page.route('**/*',route=>{
   const url=new URL(route.request().url());if(url.pathname==='/')return route.fulfill({contentType:'text/html',body:html.replaceAll('staging.badaround.it','localhost')});
   const file=path.join(__dirname,'..',url.pathname);if(fs.existsSync(file))return route.fulfill({contentType:file.endsWith('.css')?'text/css':'application/javascript',body:file.endsWith('/wizard.js')?fs.readFileSync(file,'utf8').replace('try { boot(container); } catch (_) {','try { boot(container); } catch (_) { throw _;'):fs.readFileSync(file)});return route.abort();
  });
  await page.goto('https://localhost/');assert.deepEqual(failures,[]);assert.equal(await page.evaluate(()=>providerCalls.length),0);
  await page.evaluate(()=>{
   const header=document.createElement('header');header.className='ba-site-header';header.style.cssText='position:fixed;top:0;left:0;right:0;height:88px;z-index:999;background:white';document.body.append(header);
   const spacer=document.createElement('div');spacer.style.height='1100px';document.querySelector('[data-smart-map-panel]').before(spacer);
  });
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
  assert.equal(await page.locator('[name="location.municipality"]').inputValue(),'Pomezia');assert.equal(await page.evaluate(()=>gpsCalls),0);
  await page.locator('[data-smart-geolocate]').click();await page.waitForFunction(()=>document.querySelector('[name="location.exact_lat"]').value==='41.665');
  await page.waitForFunction(()=>{
   const address=document.querySelector('[data-smart-confirmation]'),map=document.querySelector('[data-smart-map-panel]'),rect=address.getBoundingClientRect();
   return document.activeElement===address&&map.nextElementSibling===address&&rect.top>=88&&rect.bottom<innerHeight;
  });
  assert.match(await page.locator('[data-smart-address-label]').textContent(),/Via verificata/);
  assert(await choice.getByRole('radio',{name:/Area approssimata/}).isChecked());
  assert.equal(await page.evaluate(()=>gpsCalls),1);assert.equal(await page.evaluate(()=>testMarker.content.style.backgroundColor),'rgb(22, 119, 232)');assert(await page.evaluate(()=>testMarker.content.querySelector('svg').outerHTML===document.querySelector('.ba-native-category-grid input:checked').closest('label').querySelector('svg').outerHTML));
  await page.evaluate(()=>{testMarker.position={lat:41.667,lng:12.437};testMarker.dispatchEvent(new Event('gmp-dragend'));});await page.waitForFunction(()=>document.querySelector('[name="location.exact_lat"]').value==='41.667');
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
  console.log(`PASS ${width}px: selection-only address, private map, explicit GPS, category marker, drag reconciliation`);await page.close();
 }}finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
