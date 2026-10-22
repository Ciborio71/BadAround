'use strict';
const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict'),{execFileSync}=require('node:child_process'),{chromium}=require(process.env.BA_PLAYWRIGHT_MODULE);
const root=process.cwd(),base='d0ac50bf4866b326943f3f4487b49fbe3d878d7b',front='wordpress/themes/badaround-child/front-page.php',css='wordpress/themes/badaround-child/assets/css/home.css',out='evidence';
const copy=JSON.parse(fs.readFileSync('ops/nfhome01-copy.json')),baseline=execFileSync('git',['show',base+':'+front],{encoding:'utf8'}),source=fs.readFileSync(front,'utf8');
let expected=baseline;for(const [a,b]of copy)expected=expected.replace("'subtitle' => '"+a+"'","'subtitle' => '"+b+"'");assert.equal(source,expected,'Home changes are exactly six descriptions; links/map/legend untouched');
const files=execFileSync('git',['diff','--name-only',base,'HEAD','--','wordpress'],{encoding:'utf8'}).trim().split('\n').sort();assert.deepEqual(files,[css,front].sort(),'only two runtime files changed');
const current=execFileSync('php',['ops/nfhome01-render.php'],{encoding:'utf8'}),before=execFileSync('php',['ops/nfhome01-render.php','--baseline'],{encoding:'utf8'});
fs.mkdirSync(out,{recursive:true});fs.writeFileSync(out+'/home-candidate.html',current);
const native=JSON.parse(execFileSync('php',['tests/native-report-f16.php','--config'],{encoding:'utf8'}));assert.equal(native.schemaVersion,'badaround-report/v2');
const labels=['Veicoli','Case e attività','Pericoli','Spazi pubblici','Animali','Oggetti e documenti'],slugs=['veicoli','case-e-attivita','pericoli','spazi-pubblici','animali','oggetti-e-documenti'];
const results=[],errors=[],requests=[];
function ratio(a,b){function l(c){let x=c.match(/[\d.]+/g).slice(0,3).map(Number).map(v=>{v/=255;return v<=.04045?v/12.92:((v+.055)/1.055)**2.4;});return x[0]*.2126+x[1]*.7152+x[2]*.0722;}let x=l(a),y=l(b);return (Math.max(x,y)+.05)/(Math.min(x,y)+.05);}
(async()=>{const browser=await chromium.launch();try{
for(const width of [1440,1280,1024,768,390,320]){
 const page=await browser.newPage({viewport:{width,height:1000},reducedMotion:'reduce'});page.on('pageerror',e=>errors.push({width,message:e.message}));
 await page.route('**/*',route=>{const req=route.request(),u=new URL(req.url());requests.push({method:req.method(),path:u.pathname});assert.equal(req.method(),'GET');
 if(u.pathname==='/')return route.fulfill({contentType:'text/html',body:current});
 if(u.pathname==='/segnalazioni/')return route.fulfill({contentType:'text/html',body:'<!doctype html><title>QA routing destination</title><p>Read-only route capture</p>'});
 if(u.pathname.includes('/themes/astra/')&&u.pathname.endsWith('main.min.css'))return route.fulfill({contentType:'text/css',body:fs.readFileSync(out+'/astra-main.css')});
 if(u.pathname.includes('/themes/astra/')&&u.pathname.endsWith('frontend.min.js'))return route.fulfill({contentType:'application/javascript',body:fs.readFileSync(out+'/astra-frontend.js')});
 if(u.pathname.startsWith('/wp-content/themes/badaround-child/')){const f=path.join(root,'wordpress',u.pathname.slice('/wp-content/'.length));if(fs.existsSync(f)&&fs.statSync(f).isFile())return route.fulfill({contentType:f.endsWith('.css')?'text/css':'application/javascript',body:fs.readFileSync(f)});}
 return route.abort();});
 try{
 await page.goto('https://home-qa.invalid/');const cards=page.locator('.ba-home-category-card');assert.equal(await cards.count(),6);
 const data=await cards.evaluateAll(es=>es.map(e=>({label:e.querySelector('strong').textContent,description:e.querySelector('small').textContent,href:e.getAttribute('href'),rect:e.getBoundingClientRect().toJSON(),svg:e.querySelector('svg').outerHTML,nested:e.querySelectorAll('a,button,input,select').length,titleColor:getComputedStyle(e.querySelector('strong')).color,copyColor:getComputedStyle(e.querySelector('small')).color,background:getComputedStyle(e).backgroundColor,iconRect:e.querySelector('[aria-hidden=true]').getBoundingClientRect().toJSON()})));
 assert.deepEqual(data.map(x=>x.label),labels);assert.deepEqual(data.map(x=>new URL(x.href).searchParams.get('categoria')),slugs);assert(data.every(x=>new URL(x.href).pathname==='/segnalazioni/'&&x.nested===0));
 assert.deepEqual(data.map(x=>x.description),copy.map(x=>x[1]));
 const columns=await page.locator('.ba-home-categories').evaluate(e=>getComputedStyle(e).gridTemplateColumns.split(' ').length),expectedColumns=width>=1024?3:width>=768?2:1;assert.equal(columns,expectedColumns);
 assert.equal(new Set(data.map(x=>Math.round(x.rect.height))).size,1,'equal card heights');
 assert(data.every(x=>x.rect.width>=200&&x.rect.height>=44&&x.rect.left>=0&&x.rect.right<=width+1),'comfortable fitting card geometry');
 for(const d of data){assert(ratio(d.titleColor,d.background)>=4.5);assert(ratio(d.copyColor,d.background)>=4.5);assert(d.iconRect.width>=32&&d.iconRect.left>=d.rect.left&&d.iconRect.right<=d.rect.right);}
 const over=await page.evaluate(()=>({width:innerWidth,document:document.documentElement.scrollWidth,cards:document.querySelector('.ba-home-categories').scrollWidth}));assert(over.document<=width+1,'no document overflow');assert(over.cards<=await page.locator('.ba-home-categories').evaluate(e=>e.clientWidth)+1,'no card carousel or clipping');
 // Icon shapes/labels/links and all non-category markup match baseline rendering.
 const baselinePage=await browser.newPage();await baselinePage.route('**/*',r=>r.request().url()==='https://baseline.invalid/'?r.fulfill({contentType:'text/html',body:before}):r.abort());await baselinePage.goto('https://baseline.invalid/');
 const original=await baselinePage.locator('.ba-home-category-card').evaluateAll(es=>es.map(e=>({label:e.querySelector('strong').textContent,href:e.getAttribute('href'),svg:e.querySelector('svg').outerHTML})));assert.deepEqual(data.map(({label,href,svg})=>({label,href,svg})),original);
 await baselinePage.close();
 await cards.first().focus();for(let i=0;i<6;i++){assert(await cards.nth(i).evaluate(e=>e===document.activeElement));await page.waitForTimeout(700);const exposed=await cards.nth(i).evaluate(e=>{const t=e.querySelector('strong').getBoundingClientRect(),h=document.querySelector('.ba-site-header').getBoundingClientRect();return t.top>=h.bottom&&t.bottom<=innerHeight;});assert(exposed,'focused card title exposed below fixed header');const focus=await cards.nth(i).evaluate(e=>({style:getComputedStyle(e).outlineStyle,width:parseFloat(getComputedStyle(e).outlineWidth),color:getComputedStyle(e).outlineColor,background:getComputedStyle(e).backgroundColor}));assert.equal(focus.style,'solid');assert(focus.width>=3&&ratio(focus.color,focus.background)>=3);if(i<5)await page.keyboard.press('Tab');}
 const legend=page.locator('.ba-map-legend');assert(await legend.isVisible());await legend.locator('summary').click();assert.equal(await legend.getAttribute('open'),null);await legend.locator('summary').click();assert.notEqual(await legend.getAttribute('open'),null);
 if(width<=768){const toggle=page.locator('.ba-menu-toggle');assert(await toggle.isVisible());await toggle.click();assert.equal(await toggle.getAttribute('aria-expanded'),'true');assert(await page.locator('#ba-mobile-menu').isVisible());await toggle.click();assert.equal(await toggle.getAttribute('aria-expanded'),'false');}
 for(let i=0;i<6;i++){await cards.nth(i).click();const u=new URL(page.url());assert.equal(u.pathname,'/segnalazioni/');assert.equal(u.searchParams.get('categoria'),slugs[i]);await page.goBack();}
 await page.locator('.ba-home-categories').scrollIntoViewIfNeeded();await cards.first().focus();await page.keyboard.press('Tab');await page.keyboard.press('Shift+Tab');
 await page.waitForTimeout(700);const titleExposed=await cards.first().evaluate(e=>{const r=e.querySelector('strong').getBoundingClientRect();return {top:r.top,bottom:r.bottom,headerBottom:document.querySelector('.ba-site-header').getBoundingClientRect().bottom};});assert(titleExposed.top>=titleExposed.headerBottom,'return focus does not hide title');
 if([1440,768,390,320].includes(width)){
   // Capture the document-space category region without locator auto-scrolling under the sticky header.
   await page.evaluate(()=>window.scrollTo({top:0,behavior:'instant'}));
   const region=await page.locator('.ba-home-categories').evaluate(e=>{const r=e.getBoundingClientRect();return {x:Math.max(0,r.left+scrollX-8),y:Math.max(0,r.top+scrollY-8),width:r.width+16,height:r.height+16,scale:1};});
   const cdp=await page.context().newCDPSession(page);const shot=await cdp.send('Page.captureScreenshot',{format:'png',clip:region,captureBeyondViewport:true,fromSurface:true});await cdp.detach();fs.writeFileSync(out+'/cards-'+width+'.png',Buffer.from(shot.data,'base64'));
   await cards.nth(1).focus();await page.keyboard.press('Shift+Tab');await page.waitForTimeout(700);
 }
 if(width===1440)await page.screenshot({path:out+'/keyboard-focus-1440.png'});
 results.push({width,columns,result:'PASS',cards:data,overflow:over,keyboard:true,focus:true,legend:true,mobileNavigation:width<=768?'PASS':'not applicable'});console.log('PASS NF-HOME-01',width,columns,'columns, six links, focus, contrast, legend, menu');
 }catch(e){await page.screenshot({path:out+'/failure-'+width+'.png',fullPage:true});throw e;}
 finally{await page.close();fs.writeFileSync(out+'/results.json',JSON.stringify({results,errors,requests},null,2));}
}
assert.deepEqual(errors,[]);assert.equal(results.length,6);console.log('NF_HOME_01_PASS: 6 widths, 6 categories, zero app JS changes or submissions');
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exitCode=1;});
