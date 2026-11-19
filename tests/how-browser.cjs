const {chromium}=require('playwright-core');const assert=require('node:assert/strict');
(async()=>{const b=await chromium.launch({executablePath:process.env.CHROME_BIN||'/usr/bin/google-chrome',headless:true,args:['--no-sandbox']});try{const p=await b.newPage();await p.goto('http://127.0.0.1:8765/how-fixture.html');
for(const width of [1440,1280,1024,768,390,320]){await p.setViewportSize({width,height:1000});await p.waitForTimeout(100);assert(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'Overflow '+width);
if(width>=1280){for(const sel of ['.how-step','.how-category']){const heights=await p.locator(sel).evaluateAll(es=>es.map(e=>e.getBoundingClientRect().height));assert(Math.max(...heights)-Math.min(...heights)<2,'Equal card heights '+sel);}}
console.log('HOW_LAYOUT_PASS',width);if(width===1440||width===390)await p.screenshot({path:'how-'+width+'.png',fullPage:true});}
}finally{await b.close();}})().catch(e=>{console.error(e);process.exit(1)});
