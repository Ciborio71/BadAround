const {chromium}=require('playwright-core'),assert=require('node:assert/strict');
(async()=>{const b=await chromium.launch({executablePath:process.env.CHROME_BIN||'/usr/bin/google-chrome',headless:true,args:['--no-sandbox']});try{const p=await b.newPage();const iso=new Date().toISOString().slice(0,10),it=iso.split('-').reverse().join('/');
const make=(id,date)=>({id,title:'Segnalazione '+id,permalink:'/eventi/'+id,event_type:{name:'Veicolo rubato',slug:'veicolo-rubato'},territory:{id:1},occurred_date:date,public_geo:{version:'public-location/v1',source:'event_public',mode:'exact_point',lat:42,lng:12}});
await p.route('**/wp-json/badaround/v1/discovery**',r=>r.fulfill({contentType:'application/json',body:JSON.stringify({items:[make(1,iso),make(2,it),make(3,'31/02/2026'),make(4,'')],pages:1})}));
await p.goto('http://127.0.0.1:8765/radar-fixture.html');await p.locator('.ba-observatory-kpi strong').first().waitFor();assert.equal(await p.locator('.ba-observatory-kpi strong').allTextContents().then(x=>x.join(',')),'4,1,0,2');assert(await p.locator('#rad-radius').isDisabled());

for(const width of [1440,1280,1024,768,390,320]){await p.setViewportSize({width,height:1000});await p.waitForTimeout(100);assert(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'Overflow '+width);if(width===1440||width===390)await p.screenshot({path:'radar-'+width+'.png',fullPage:true});console.log('RADAR_LAYOUT_PASS',width);}
await p.locator('#rad-period').selectOption('30');assert.equal(await p.locator('.ba-observatory-kpi strong').first().innerText(),'2');
await p.locator('#rad-reset').click();assert.equal(await p.locator('.ba-observatory-kpi strong').first().innerText(),'4');console.log('RADAR_DATES_FILTER_RESET_PASS');
}finally{await b.close();}})().catch(e=>{console.error(e);process.exit(1)});
