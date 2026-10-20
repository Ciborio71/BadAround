'use strict';
const fs=require('node:fs'),assert=require('node:assert/strict');
const {webkit}=require(process.env.BA_PLAYWRIGHT_MODULE);
const origin='https://staging.badaround.it',out='qa-artifacts/geo-public-06/webkit';
fs.mkdirSync(out,{recursive:true});
(async()=>{
 const browser=await webkit.launch();
 const context=await browser.newContext(),page=await context.newPage();
 const errors=[],network=[],results=[];
 page.on('pageerror',e=>errors.push(e.message));
 page.on('requestfailed',r=>{if(r.url().includes('maps.'))network.push({url:r.url().replace(/key=[^&]+/,'key=REDACTED'),failure:r.failure()});});
 for(const width of [320,390,768,1440]){
  await page.setViewportSize({width,height:900});
  for(const [kind,path] of [['home','/'],['main','/mappa/'],['exact','/eventi/rifiuti-abbandonati-o-discarica-a-roma-2/'],['approximate','/eventi/rifiuti-abbandonati-o-discarica-a-roma-3/']]){
   const before=errors.length;
   await page.goto(origin+path,{waitUntil:'domcontentloaded'});
   await page.waitForSelector('.gm-style',{timeout:45000});
   await page.locator('[data-ba-map]').scrollIntoViewIfNeeded();
   await page.waitForTimeout(1800);
   const state=await page.evaluate(()=>{
    const node=document.querySelector('[data-ba-map]'),rect=node.getBoundingClientRect();
    const svgs=[...node.querySelectorAll('img')].map(i=>i.src).filter(s=>s.startsWith('data:image/svg+xml')).map(s=>decodeURIComponent(s.split(',').slice(1).join(',')));
    return {mode:node.dataset.baEventMode,rect:{x:rect.x,width:rect.width},svgs,errors:document.querySelectorAll('.gm-err-container').length};
   });
   assert.equal(state.errors,0);
   assert.ok(state.rect.width>0&&state.rect.x>=-1&&state.rect.x+state.rect.width<=width+1);
   if(kind==='exact'){
    assert.equal(state.mode,'exact_point');assert.ok(state.svgs.some(s=>s.includes('M28 64C')));assert.ok(state.svgs.some(s=>s.includes('<ellipse')));
   }
   if(kind==='approximate'){
    assert.equal(state.mode,'approximate_area');assert.ok(state.svgs.some(s=>s.includes('cx="24" cy="24" r="18"')));
    assert.ok(state.svgs.every(s=>!s.includes('M28 64C')));
   }
   await page.screenshot({path:out+'/uninstrumented-'+kind+'-'+width+'.png'});
   const entry={kind,width,errors:errors.slice(before),mode:state.mode,svgCount:state.svgs.length};results.push(entry);
   console.log('NORMAL '+JSON.stringify(entry));
  }
 }
 fs.writeFileSync(out+'/uninstrumented-results.json',JSON.stringify({results,errors,network},null,2));
 assert.deepEqual(errors,[]);
 console.log('UNINSTRUMENTED WEBKIT REAL STAGING PASS');
 await browser.close();
})().catch(e=>{console.error(e);process.exit(1);});
