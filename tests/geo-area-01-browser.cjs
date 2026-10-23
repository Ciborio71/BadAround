'use strict';
// Source-backed browser fixture with mocked provider APIs. No external requests.
const installControlsProvider=require('./map-controls-provider.cjs');
const {chromium,webkit}=require(process.env.BA_PLAYWRIGHT_MODULE||'playwright');
const {execFileSync}=require('node:child_process'),fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
(async()=>{
 let html=execFileSync(process.env.BA_PHP||'php',[path.join(__dirname,'native-report-f16.php'),'--html'],{encoding:'utf8'});
 const prefix='/wordpress/themes/badaround-child/assets/js/native-report/';
 html=html.replace(`<script src="${prefix}wizard.js">`,`<script src="${prefix}location-map.js"></script><script src="${prefix}smart-address.js"></script><script src="${prefix}wizard.js">`);
 html=html.replace('"smartAddress":null','"smartAddress":{"key":"TEST","territories":[[2,0,"Lazio"],[3,2,"Roma"],[4,3,"Pomezia"]]}');
 const browser=process.env.BA_BROWSER_ENGINE==='webkit'?await webkit.launch({headless:true}):await chromium.launch({headless:true,...(process.env.BA_CHROMIUM?{executablePath:process.env.BA_CHROMIUM}:{}),args:['--no-sandbox','--disable-dev-shm-usage']});
 try{for(const width of [305,320,375,390,768,1024,1440]){
  const page=await browser.newPage({viewport:{width,height:850},reducedMotion:'reduce'});page.setDefaultTimeout(12000);const failures=[];page.on('pageerror',e=>failures.push(e.message));
  await page.addInitScript(installControlsProvider);
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
    this.style.cssText='display:block;width:100%;max-width:100%;box-sizing:border-box;overflow:hidden';
    this.innerHTML='<input style="width:406px" aria-label="Ricerca Google simulata" autocomplete="off"><button type="button">Via verificata, Pomezia</button>';
    this.querySelector('button').onclick=()=>{const event=new Event('gmp-select');event.placePrediction={toPlace:()=>new Place()};this.dispatchEvent(event);};
   }}customElements.define('test-google-autocomplete',Widget);
   window.google={maps:{ControlPosition:{RIGHT_TOP:"RIGHT_TOP"},Circle,importLibrary:async name=>{
    providerCalls.push(name);
    if(name==='places')return {Place,PlaceAutocompleteElement:class{constructor(){return document.createElement('test-google-autocomplete');}}};
    if(name==='maps')return {Map:class{constructor(canvas,o){window.testMapCount=(window.testMapCount||0)+1;this.canvas=canvas;this.options=o;this.controls=mapControlsProvider(this,canvas,o);this.zoom=o.zoom;canvas.style.position='relative';window.testMap=this;}getZoom(){return this.zoom;}setCenter(p){this.center=p;}setZoom(z){this.zoom=z;circles.forEach(c=>c.draw());}fitBounds(b){this.zoom=Math.log2(156543.03392*Math.cos(b.center.lat*Math.PI/180)*Math.min(this.canvas.clientWidth,this.canvas.clientHeight)/(b.radius*2.5));this.setZoom(this.zoom);}}};
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
  // Reproduce the live Places widget's 406px intrinsic contribution. Root overflow
  // alone misses a clipped grid track; inspect each control and clipping ancestor.
  const geometry=await page.evaluate(()=>{
   const map=document.querySelector('[data-smart-map]'),m=map.getBoundingClientRect();
   const nodes=[...map.querySelectorAll('[data-test-controls] button'),map.querySelector('.ba-map-pan')];
   const bounds=nodes.map(el=>{const r=el.getBoundingClientRect();let left=0,right=innerWidth;
    for(let p=el.parentElement;p;p=p.parentElement){if(/hidden|clip|auto|scroll/.test(getComputedStyle(p).overflowX)){const b=p.getBoundingClientRect();left=Math.max(left,b.left);right=Math.min(right,b.right);}}
    return {label:el.getAttribute('aria-label')||'pan pad',left:r.left,right:r.right,width:r.width,height:r.height,visible:r.left>=left&&r.right<=right,inset:m.right-r.right};});
   return {width:innerWidth,map:{left:m.left,right:m.right,width:m.width},bounds};
  });
  assert(geometry.bounds.every(b=>b.visible&&b.left>=geometry.map.left&&b.inset>=8),JSON.stringify(geometry));
  assert(geometry.bounds.filter(b=>b.label.startsWith('Sposta')).every(b=>b.width>=44&&b.height>=44));
  await page.locator('[data-smart-geolocate]').focus();
  const visited=[];
  for(let i=0;i<16;i++){await page.keyboard.press('Tab');const f=await page.evaluate(()=>{const e=document.activeElement,r=e.getBoundingClientRect(),m=document.querySelector('[data-smart-map]').getBoundingClientRect();return {label:e.getAttribute('aria-label')||'',left:r.left,right:r.right,mapLeft:m.left,mapRight:m.right};});if(f.label.startsWith('Sposta')||f.label.includes('zoom (provider')){assert(f.left>=f.mapLeft&&f.right<=Math.min(geometry.width,f.mapRight));visited.push(f.label);}}
  assert.equal(new Set(visited.filter(l=>l.startsWith('Sposta'))).size,4);
  const geometryDir=process.env.BA_EVIDENCE_DIR||'/tmp/geo-area-01-browser';fs.mkdirSync(geometryDir,{recursive:true});fs.writeFileSync(path.join(geometryDir,`geometry-${width}.json`),JSON.stringify(geometry,null,2));
  // GEO-PUBLIC-03: one authoritative choice, keyboard-operable, independent of private point.
  const exactSnapshot=await page.evaluate(()=>JSON.stringify(document.querySelector('[data-native-report]').nativeReport.model.payload()));
  assert.equal(await page.evaluate(()=>circles.length),0);await page.getByRole('button',{name:'Aumenta zoom (provider simulato)',exact:true}).click();await page.getByRole('button',{name:'Riduci zoom (provider simulato)',exact:true}).click();await page.getByRole('button',{name:'Sposta mappa verso est',exact:true}).click();
  assert.equal(await page.evaluate(()=>JSON.stringify(document.querySelector('[data-native-report]').nativeReport.model.payload())),exactSnapshot);
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
   const unchanged=await page.evaluate(()=>JSON.stringify({payload:document.querySelector('[data-native-report]').nativeReport.model.payload(),point:testMarker.position,center:circles.at(-1).center,radius:circles.at(-1).radius}));
   const z=await page.evaluate(()=>testMap.zoom);await page.getByRole('button',{name:'Aumenta zoom (provider simulato)',exact:true}).click();assert.equal(await page.evaluate(()=>testMap.zoom),z+1);await page.getByRole('button',{name:'Riduci zoom (provider simulato)',exact:true}).click();assert.equal(await page.evaluate(()=>testMap.zoom),z);
   for(const direction of ['nord','sud','ovest','est'])await page.getByRole('button',{name:'Sposta mappa verso '+direction,exact:true}).click();
   assert.equal(await page.evaluate(()=>JSON.stringify({payload:document.querySelector('[data-native-report]').nativeReport.model.payload(),point:testMarker.position,center:circles.at(-1).center,radius:circles.at(-1).radius})),unchanged);
   const zoomBefore=await page.evaluate(()=>testMap.zoom);await page.evaluate(()=>testMap.setZoom(testMap.zoom+1));assert.equal(await page.evaluate(()=>circles.at(-1).radius),r);await page.evaluate(z=>testMap.setZoom(z),zoomBefore);
  }
  await page.getByRole('button',{name:'Schermo intero (provider simulato)',exact:true}).click();await page.waitForFunction(()=>Boolean(document.fullscreenElement));
  assert.equal(await page.evaluate(()=>circles.at(-1).radius),1000);await page.getByRole('button',{name:'Sposta mappa verso est',exact:true}).click();
  await page.getByRole('button',{name:'Schermo intero (provider simulato)',exact:true}).click();await page.waitForFunction(()=>!document.fullscreenElement);
  const navRects=await page.locator('.ba-map-pan button').evaluateAll(bs=>bs.map(b=>({width:b.getBoundingClientRect().width,height:b.getBoundingClientRect().height})));assert(navRects.every(r=>r.width>=44&&r.height>=44));
  await page.getByRole('button',{name:'Sposta mappa verso nord',exact:true}).focus();await page.keyboard.press('Enter');assert.equal(await page.locator('.ba-map-pan button').first().evaluate(e=>getComputedStyle(e).outlineStyle),'solid');
  await page.keyboard.press('Tab');await page.keyboard.press('Tab');await page.keyboard.press('Tab');await page.keyboard.press('Tab');assert(!await page.locator('.ba-map-pan').evaluate(e=>e.contains(document.activeElement)));
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
  assert.equal(await page.evaluate(()=>testMapCount),1);
  console.log(`PASS ${width}px: map controls/fullscreen/viewport isolation, all five circles, zoom invariance, live radius, stale clear, keyboard, center follow, GPS denial and responsive rendering`);await page.close();
 }}finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
