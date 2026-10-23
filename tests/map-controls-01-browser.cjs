'use strict';
// Fresh Chromium, actual PHP templates and application assets. Offline provider seam only.
const {chromium}=require(process.env.BA_PLAYWRIGHT_MODULE||'playwright');
const installProvider=require('./map-controls-provider.cjs');
const fs=require('node:fs'),path=require('node:path'),cp=require('node:child_process'),assert=require('node:assert/strict');
const out=process.env.BA_EVIDENCE_DIR||'/tmp/map-controls-public';fs.mkdirSync(out,{recursive:true});
const items=[0,100,500,1000].map((r,i)=>({id:363-i,title:'Fixture sintetica pubblica '+r,event_type:{id:9},territory:{name:'Pomezia'},permalink:'/eventi/qa-'+r+'/',public_geo:{mode:r?'approximate_area':'exact_point',lat:41.66+i*.0001,lng:12.43,radius_meters:r}}));
const html=(context,radius)=>cp.execFileSync(process.env.BA_PHP||'php',[path.join(__dirname,'map-controls-01-render.php'),context,String(radius)],{encoding:'utf8'});
function provider(){
 window.testMaps=[];window.testMarkers=[];window.testCircles=[];
 class Layer{constructor(o){Object.assign(this,o);this.listeners={};}addListener(k,f){(this.listeners[k]||=[]).push(f);return{remove(){}};}trigger(k){(this.listeners[k]||[]).forEach(f=>f());}}
 class Marker extends Layer{
  constructor(o){super(o);testMarkers.push(this);if(o.clickable!==false){this.node=document.createElement('button');this.node.type='button';this.node.textContent=o.title;this.node.setAttribute('aria-label',o.title);this.node.style.cssText='position:absolute;left:43%;top:58%;width:44px;height:44px;overflow:hidden;font:8px system-ui;background:#0aa184;color:white;border-radius:50%;z-index:1';this.node.onclick=()=>this.trigger('click');}this.setMap(o.map);}
  setMap(m){this.map=m;if(this.node){this.node.hidden=!m;if(m)m.canvas.append(this.node);}}setIcon(i){this.icon=i;}setZIndex(z){this.zIndex=z;}
 }
 class Circle extends Layer{
  constructor(o){super(o);testCircles.push(this);this.node=document.createElementNS('http://www.w3.org/2000/svg','svg');this.node.style.cssText='position:absolute;inset:0;width:100%;height:100%;pointer-events:none';this.node.innerHTML='<circle cx="50%" cy="50%" fill="#087f8c22" stroke="#087f8c" stroke-width="2"/>';this.setMap(o.map);}
  draw(){if(this.map)this.node.querySelector('circle').setAttribute('r',String(this.radius/(156543.03392*Math.cos(this.center.lat*Math.PI/180)/2**this.map.zoom)));}
  setMap(m){this.map=m;this.node.style.display=m?'block':'none';if(m)m.canvas.append(this.node);this.draw();}getBounds(){return {radius:this.radius,center:this.center};}
 }
 class Bounds{extend(p){this.position=p;return this;}getCenter(){return this.position||{lat:41.66,lng:12.43};}}
 class GoogleMap extends Layer{
  constructor(n,o){super(o);this.canvas=n;testMaps.push(this);this.controls=mapControlsProvider(this,n,o);}
  getZoom(){return this.zoom;}setZoom(z){this.zoom=z;testCircles.forEach(c=>c.draw());this.trigger('idle');}setCenter(p){this.center=p;}
  fitBounds(b){this.setZoom(b.radius?Math.log2(156543.03392*Math.cos(b.center.lat*Math.PI/180)*Math.min(this.canvas.clientWidth,this.canvas.clientHeight)/(b.radius*2.5)):14);}
 }
 class Overlay{setMap(m){this.map=m;if(m)queueMicrotask(()=>this.draw());}getProjection(){return{fromLatLngToDivPixel:p=>({x:p.lat*100,y:p.lng*100})};}}
 window.google={maps:{ControlPosition:{RIGHT_TOP:'RIGHT_TOP'},Map:GoogleMap,Marker,Circle,LatLngBounds:Bounds,OverlayView:Overlay,LatLng:class{constructor(p){Object.assign(this,p);}},Size:class{constructor(w,h){this.width=w;this.height=h;}},Point:class{constructor(x,y){this.x=x;this.y=y;}},InfoWindow:class{setContent(){}open(){}close(){}}}};
 window.BadAroundMap={hasMaps:true,mapsUrl:'https://maps.googleapis.com/maps/api/js',endpoint:'/discovery',categoryByTerm:{9:'spazi-pubblici'}};
}
const snapshot=page=>page.evaluate(()=>JSON.stringify({markers:testMarkers.filter(m=>!m.title?.includes('segnalazioni')).map(m=>({p:m.position,title:m.title})),circles:testCircles.map(c=>({center:c.center,radius:c.radius}))}));
(async()=>{
 const browser=await chromium.launch({headless:true,args:['--no-sandbox']});const results=[];
 try{for(const width of [320,390,768,1024,1440])for(const [context,radius] of [['home',0],['full',0],['detail',0],['detail',100],['detail',500],['detail',1000]]){
  const page=await browser.newPage({viewport:{width,height:900},hasTouch:width<=768,reducedMotion:'reduce'});page.setDefaultTimeout(10000);const errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.addInitScript(installProvider);await page.addInitScript(provider);const body=html(context,radius);
  await page.route('**/*',route=>{const u=new URL(route.request().url());if(u.pathname==='/')return route.fulfill({contentType:'text/html',body});if(u.pathname==='/discovery')return route.fulfill({json:{items,total:items.length}});const p=path.join(__dirname,'..',u.pathname);if(fs.existsSync(p)&&fs.statSync(p).isFile())return route.fulfill({contentType:p.endsWith('.css')?'text/css':'text/javascript',body:fs.readFileSync(p)});return route.abort();});
  try{
   await page.goto('https://localhost/');await page.locator('.ba-map-pan').waitFor();assert.deepEqual(errors,[]);assert.equal(await page.evaluate(()=>testMaps.length),1);
   assert.deepEqual(await page.evaluate(()=>testCircles.map(c=>c.radius)),context==='detail'?(radius?[radius]:[]):[100,500,1000]);
   const policy=await page.evaluate(()=>testMaps[0].options);for(const k of ['zoomControl','fullscreenControl','draggable','keyboardShortcuts'])assert.equal(policy[k],true);assert.equal(policy.gestureHandling,'cooperative');
   const before=await snapshot(page),z=await page.evaluate(()=>testMaps[0].getZoom());
   await page.getByRole('button',{name:'Aumenta zoom (provider simulato)',exact:true}).click();assert.equal(await page.evaluate(()=>testMaps[0].zoom),z+1);
   await page.getByRole('button',{name:'Riduci zoom (provider simulato)',exact:true}).click();assert.equal(await page.evaluate(()=>testMaps[0].zoom),z);
   for(const d of ['nord','sud','ovest','est'])await page.getByRole('button',{name:'Sposta mappa verso '+d,exact:true}).click();
   assert.equal(await snapshot(page),before);assert.equal(await page.evaluate(()=>testMaps[0].panCalls.length),4);
   const region=page.getByRole('region',{name:'Mappa provider simulato',exact:true});await region.focus();await page.keyboard.press('ArrowRight');assert.equal(await page.evaluate(()=>testMaps[0].panCalls.length),5);
   await region.scrollIntoViewIfNeeded();const box=await region.boundingBox();await page.mouse.move(box.x+box.width*.8,box.y+box.height*.8);await page.mouse.down();await page.mouse.move(box.x+box.width*.8-20,box.y+box.height*.8-10);await page.mouse.up();assert.equal(await page.evaluate(()=>testMaps[0].panCalls.length),6);

   const north=page.getByRole('button',{name:'Sposta mappa verso nord',exact:true});await north.focus();await page.keyboard.press('Enter');await page.keyboard.press('Space');assert.equal(await page.evaluate(()=>testMaps[0].panCalls.length),8);
   assert.equal(await north.evaluate(e=>getComputedStyle(e).outlineStyle),'solid');for(let i=0;i<4;i++)await page.keyboard.press('Tab');assert(!await page.locator('.ba-map-pan').evaluate(e=>e.contains(document.activeElement)));
   await page.getByRole('button',{name:'Schermo intero (provider simulato)',exact:true}).click();await page.waitForFunction(()=>Boolean(document.fullscreenElement));assert.equal(await snapshot(page),before);
   await page.getByRole('button',{name:'Sposta mappa verso est',exact:true}).click();await page.getByRole('button',{name:'Schermo intero (provider simulato)',exact:true}).click();await page.waitForFunction(()=>!document.fullscreenElement);
   if(context!=='detail'){
    await page.locator('.ba-map-legend summary').click();assert.equal(await page.locator('.ba-map-legend').getAttribute('open'),null);await page.locator('.ba-map-legend summary').click();
    await page.getByRole('button',{name:'4 segnalazioni',exact:true}).click();await page.evaluate(()=>testMaps[0].setZoom(17));
    assert.equal(await page.evaluate(()=>testCircles.filter(c=>c.map).length),3);assert(await north.isVisible());
    await page.getByRole('button',{name:/raggio 1000 metri:/}).focus();await page.keyboard.press('Enter');
   }
   const layout=await page.evaluate(()=>{
    const rect=e=>{const r=e.getBoundingClientRect();return {l:r.left,t:r.top,r:r.right,b:r.bottom,w:r.width,h:r.height};},hit=(a,b)=>a.l<b.r&&a.r>b.l&&a.t<b.b&&a.b>b.t;
    const buttons=[...document.querySelectorAll('.ba-map-pan button,[data-test-controls]>button')].map(e=>({label:e.getAttribute('aria-label'),rect:rect(e),exposed:e.contains(document.elementFromPoint(e.getBoundingClientRect().x+e.clientWidth/2,e.getBoundingClientRect().y+e.clientHeight/2))}));
    const attrib=rect(document.querySelector('[data-test-attribution]')),avoid=[...document.querySelectorAll('.ba-map-legend,.ba-map-preview:not([hidden]),.ba-map-privacy-note,.ba-map-mobile-results')].filter(e=>getComputedStyle(e).display!=='none').map(rect);
    return {overflow:document.documentElement.scrollWidth>innerWidth+1,buttons,attributionOverlap:buttons.some(b=>hit(b.rect,attrib)),overlap:buttons.some(b=>avoid.some(r=>hit(b.rect,r))),buttonOverlap:buttons.some((a,i)=>buttons.some((b,j)=>j>i&&hit(a.rect,b.rect)))};
   });
   assert(!layout.overflow,JSON.stringify(layout));assert(!layout.overlap,JSON.stringify(layout));assert(!layout.attributionOverlap);assert(!layout.buttonOverlap);assert(layout.buttons.every(b=>b.rect.w>=40&&b.rect.h>=40&&b.exposed),JSON.stringify(layout));
   assert.equal(await snapshot(page),before);assert.deepEqual(errors,[]);
   if((context==='home'&&[320,1440].includes(width))||(context==='detail'&&radius===100&&width===390)||(context==='full'&&width===768))await page.screenshot({path:path.join(out,context+'-'+radius+'-'+width+'.png')});
   results.push({context,radius,width,result:'PASS',policy,layout});console.log('PASS',context,radius,width,'zoom/pan/fullscreen, geometry invariant, keyboard, legend/cluster and overlap');
  }catch(e){await page.screenshot({path:path.join(out,'FAIL-'+context+'-'+radius+'-'+width+'.png'),fullPage:true});throw e;}
  finally{await page.close();fs.writeFileSync(path.join(out,'results.json'),JSON.stringify(results,null,2));}
 }
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
