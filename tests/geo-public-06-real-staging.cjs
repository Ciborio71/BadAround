'use strict';
// Read-only real staging browser QA. Constructor observation does not replace Google Maps.
const fs=require('node:fs'),assert=require('node:assert/strict'),crypto=require('node:crypto');
const {chromium,webkit}=require(process.env.BA_PLAYWRIGHT_MODULE);
const origin='https://staging.badaround.it';
const engine=process.env.BA_BROWSER_ENGINE||'chromium';
const out='qa-artifacts/geo-public-06/'+engine;fs.mkdirSync(out,{recursive:true});
const results=[];
(async()=>{
 const browser=await (engine==='webkit'?webkit:chromium).launch({headless:true});
 const context=await browser.newContext();
 const response=await context.request.get(origin+'/wp-json/badaround/v1/discovery?per_page=100');
 assert.equal(response.status(),200);const payload=await response.json();
 const event=id=>payload.items.find(e=>e.id===id);
 const e=event(303),a=event(304),t=event(269),l=event(290);
 assert.equal(e.public_geo.mode,'exact_point');assert.equal(a.public_geo.mode,'approximate_area');
 assert.equal(a.public_geo.radius_meters,1000);assert.equal(t.public_geo,null);
 assert.equal(l.public_geo.radius_m,1000);assert.notEqual(l.public_geo.mode,'exact_point');
 assert.notDeepEqual([a.public_geo.lat,a.public_geo.lng],[e.public_geo.lat,e.public_geo.lng]);
 assert.doesNotMatch(JSON.stringify(payload),/exact_lat|exact_lng|exact_address|private_geo|reporter_email/);
 const again=await(await context.request.get(origin+'/wp-json/badaround/v1/discovery?per_page=100')).json();
 assert.deepEqual(again.items.find(e=>e.id===304).public_geo,a.public_geo);
 fs.writeFileSync(out+'/discovery.json',JSON.stringify({items:[e,a,t,l],stable:true},null,2));
 const page=await context.newPage();const errors=[];page.on('pageerror',err=>errors.push(err.message));
 await page.addInitScript(()=>{
  window.__baQA={maps:[],markers:[],circles:[],installed:false,install(){
   if(this.installed || !window.google?.maps)return;this.installed=true;
   for(const [name,key] of [['Map','maps'],['Marker','markers'],['Circle','circles']]){
    const original=google.maps[name],state=this;
    google.maps[name]=new Proxy(original,{construct(target,args,newTarget){
     const instance=Reflect.construct(target,args,newTarget);
     state[key].push({instance,options:args[name==='Map'?1:0],node:name==='Map'?args[0]:null});return instance;
    }});
   }
  }};
 });
 const local=fs.readFileSync('wordpress/themes/badaround-child/assets/js/map.js','utf8');
 await page.route('**/assets/js/map.js?*',async route=>{
  const response=await route.fetch(),body=await response.text();assert.equal(body,local,'remote map.js must equal certified candidate');
  await route.fulfill({response,body:body.replace("const initMap = (node, items) => {","const initMap = (node, items) => {\n window.__baQA.install();")});
 });
 async function snapshot(name){await page.screenshot({path:out+'/'+name+'.png',fullPage:false});}
 async function visit(path,detail=false){
  const count=errors.length;
  await page.goto(origin+path,{waitUntil:'domcontentloaded'});
  if(detail==='territory'){
   await page.waitForSelector('.ba-event-territory');
   assert.equal(await page.locator('[data-ba-map]').count(),0);
   assert.equal(await page.locator('.ba-event-territory').getByText('Zona territoriale',{exact:true}).count(),1);
  }else{
   await page.waitForFunction(()=>window.__baQA.maps.length>0&&window.__baQA.markers.length>0,{},{timeout:45000});
   await page.waitForSelector('.gm-style',{timeout:45000});
   assert.equal(await page.locator('.gm-err-container:visible').count(),0);
   await page.locator('[data-ba-map]').scrollIntoViewIfNeeded();
   await page.waitForTimeout(1500);
  }
  assert.deepEqual(errors.slice(count),[],'no map JS runtime errors');
  const html=await page.content();assert.doesNotMatch(html,/data-[^=]*(?:exact_lat|exact_lng|exact_address)|Piazza Navona/i);
 }
 async function mapState(){
  return page.evaluate(()=>{
   const q=window.__baQA;
   const icon=m=>decodeURIComponent(m.instance.getIcon().url.split(',').slice(1).join(','));
   return {
    maps:q.maps.length,
    zoom:q.maps[0]?.instance.getZoom(),
    allMarkers:q.markers.map(m=>({title:m.instance.getTitle(),visible:Boolean(m.instance.getMap()),position:m.instance.getPosition()?.toJSON()})),
    markers:q.markers.filter(m=>m.instance.getMap()).map(m=>{
     const i=m.instance.getIcon(),p=m.instance.getPosition();
     return {title:m.instance.getTitle()||'',lat:p.lat(),lng:p.lng(),anchor:{x:i.anchor.x,y:i.anchor.y},svg:icon(m)};
    }),
    circles:q.circles.filter(c=>c.instance.getMap()).map(c=>({lat:c.instance.getCenter().lat(),lng:c.instance.getCenter().lng(),radius:c.instance.getRadius()})),
   };
  });
 }
 async function zoomTo(id,zoom){
  const g=event(id).public_geo;
  await page.evaluate(({g,zoom})=>{const map=window.__baQA.maps[0].instance;map.setCenter({lat:g.lat,lng:g.lng});map.setZoom(zoom);},{g,zoom});
  await page.waitForTimeout(900);
 }
 function validate(state,detail){
  const pin=state.markers.find(m=>m.title.startsWith('Posizione esatta'));
  const badge=state.markers.find(m=>m.title.startsWith('Area approssimativa'));
  if(!detail||detail===303){
   assert.ok(pin);assert.ok(Math.abs(pin.lat-e.public_geo.lat)<1e-9);assert.ok(Math.abs(pin.lng-e.public_geo.lng)<1e-9);
   assert.deepEqual(pin.anchor,{x:28,y:64});assert.match(pin.svg,/M28 64C/);assert.match(pin.svg,/#0AA184/);
   const shadow=state.markers.find(m=>m.svg.includes('<ellipse'));assert.ok(shadow);assert.equal(shadow.lat,pin.lat);assert.equal(shadow.lng,pin.lng);
   assert.ok(!state.circles.some(c=>Math.abs(c.lat-pin.lat)<1e-9&&Math.abs(c.lng-pin.lng)<1e-9));
  }
  if(!detail||detail===304){
   assert.ok(badge);assert.ok(Math.abs(badge.lat-a.public_geo.lat)<1e-9);assert.ok(Math.abs(badge.lng-a.public_geo.lng)<1e-9);
   assert.deepEqual(badge.anchor,{x:24,y:24});assert.doesNotMatch(badge.svg,/M28 64C|M24 58C/);
   assert.ok(state.circles.some(c=>Math.abs(c.lat-badge.lat)<1e-9&&Math.abs(c.lng-badge.lng)<1e-9&&c.radius===1000));
  }
 }
 for(const width of [320,390,768,1440]){
  await page.setViewportSize({width,height:900});
  for(const path of ['/','/mappa/']){
   await visit(path);await zoomTo(303,17);
   const s=await mapState();console.log('OBSERVED '+path+' '+width+' '+JSON.stringify(s));fs.writeFileSync(out+'/observed-'+(path==='/'?'home':'main')+'-'+width+'.json',JSON.stringify(s,null,2));await snapshot('observed-'+(path==='/'?'home':'main')+'-'+width);validate(s);
   const box=await page.locator('[data-ba-map]').boundingBox();
   assert.ok(box.width>0&&box.x>=-1&&box.x+box.width<=width+1,'map within viewport');
   await snapshot((path==='/'?'home':'main')+'-'+width+'-exact');
   await zoomTo(304,15);
   validate(await mapState());
   await snapshot((path==='/'?'home':'main')+'-'+width+'-approximate');
   // Circle uses Google Maps real-meter geometry; zoom changes its projected size.
   const radii=await page.evaluate(()=>window.__baQA.circles.map(c=>c.instance.getRadius()));
   assert.ok(radii.includes(1000));
   results.push({path,width,exact:true,approximate:true,viewport:true});
  }
  for(const id of [303,304]){
   await visit(new URL(event(id).permalink).pathname,true);
   const s=await mapState();validate(s,id);assert.equal(s.maps,1);
   const box=await page.locator('[data-ba-map]').boundingBox();
   assert.ok(box.width>0&&box.x>=-1&&box.x+box.width<=width+1);
   await snapshot('detail-'+id+'-'+width);
   results.push({detail:id,width,pass:true});
  }
 }
 await visit(new URL(t.permalink).pathname,'territory');await page.locator('.ba-event-territory').scrollIntoViewIfNeeded();
 await snapshot('territory-269');
 await visit(new URL(l.permalink).pathname,true);const legacyState=await mapState();
 assert.equal(legacyState.circles[0].radius,1000);assert.ok(legacyState.markers.every(m=>!m.title.startsWith('Posizione esatta')));
 await snapshot('legacy-290');
 fs.writeFileSync(out+'/results.json',JSON.stringify({engine,results,territory:true,legacy:true,errors,observation:'Real Google Maps constructors observed in browser only; server assets and data unchanged.'},null,2));
 console.log('GEO-PUBLIC-06 REAL STAGING '+engine+' PASS '+results.length+' responsive map/detail checks; Discovery, territory, legacy, privacy PASS');
 await browser.close();
})().catch(e=>{console.error(e);process.exit(1);});
