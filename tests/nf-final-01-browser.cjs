'use strict';
// Local-only browser smoke. No requests reach WordPress or Google; no DB writes.
const {chromium}=require(process.env.BA_PLAYWRIGHT_MODULE || 'playwright');
const {execFileSync}=require('node:child_process');
const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
(async()=>{
 const html=execFileSync(process.env.BA_PHP || 'php',[path.join(__dirname,'native-report-f16.php'),'--html'],{encoding:'utf8'});
 const browser=await chromium.launch({headless:true,...(process.env.BA_CHROMIUM?{executablePath:process.env.BA_CHROMIUM}:{}),timeout:20000,args:['--no-sandbox','--disable-dev-shm-usage']});
 try{
 for(const width of [320,390,768,1440]){
  const page=await browser.newPage({viewport:{width,height:850},reducedMotion:'reduce'});
  page.on('pageerror',error=>console.error('Browser:',error.message));
  page.on('console',msg=>{if(msg.type()==='error')console.error(msg.text());});
  await page.route('**/*',async route=>{
   const url=new URL(route.request().url());
   if(url.pathname==='/')return route.fulfill({contentType:'text/html',body:html.replaceAll('staging.badaround.it','localhost')});
   const file=path.join(__dirname,'..',url.pathname);
   if(fs.existsSync(file)&&fs.statSync(file).isFile())return route.fulfill({contentType:file.endsWith('.css')?'text/css':'application/javascript',body:fs.readFileSync(file)});
   return route.abort();
  });
  page.setDefaultTimeout(8000);
  await page.goto('https://localhost/',{waitUntil:'domcontentloaded',timeout:15000});
  // Reproduce real first-time selection: do not prefill canonical values here.
  await page.locator('.ba-native-category-grid label').filter({hasText:'Veicoli'}).click();
  await page.waitForTimeout(30);
  assert.equal(await page.locator('.ba-native-category-grid label:visible').count(),1);
  assert(await page.locator('[data-native-field="event.subtype"] input:focus').count()===1);
  await page.locator('[data-native-field="event.subtype"] label').filter({hasText:'Veicolo rubato'}).click();
  await page.waitForTimeout(30);
  assert.equal(await page.locator('[data-native-field="event.subtype"] .ba-native-card-grid label:visible').count(),1);
  assert(await page.locator('[data-native-field="reporter.relationship"] input:focus').count()===1);
  await page.locator('[data-native-field="reporter.relationship"] label').filter({hasText:'Sono coinvolto direttamente'}).click();
  await page.waitForTimeout(30);
  assert(await page.locator('[data-native-next]').evaluate(e=>document.activeElement===e));
  assert.equal(await page.locator('[data-native-step="event"]:visible').count(),1);
  // Start a fresh page for the independent validation/keyboard smoke.
  await page.reload({waitUntil:'domcontentloaded'});
  await page.getByRole('button',{name:'Continua',exact:true}).click();
  assert(await page.locator('[data-native-errors]').evaluate(e=>document.activeElement===e));
  await page.locator('[data-native-errors] button').first().click();
  assert(await page.locator('[data-native-field="event.category"] .ba-native-card-grid input').first().evaluate(e=>document.activeElement===e));
  await page.keyboard.press('ArrowRight');
  assert(await page.locator('[data-native-field="event.category"] .ba-native-card-grid input:checked').count()===1);
  await page.keyboard.press('Tab');
  assert(await page.locator('[data-native-field="event.subtype"] .ba-native-card-grid input').first().evaluate(e=>document.activeElement===e));
  await page.evaluate(()=>{
   const root=document.querySelector('[data-native-report]');
   const data={'event.category':'hazard','event.subtype':'hazard_road_obstruction','reporter.relationship':'territory_observer','location.region':'Lazio','location.province':'Roma','location.municipality':'Pomezia','location.exact_address':'Via Test 1, Pomezia','location.place_type':'road_sidewalk','location.immediate_danger':'no','time.mode':'unknown','content.description':'Un ostacolo sulla strada da verificare.','damage.status':'no','authority.status':'no','media.availability':'no','reporter.first_name':'QA','reporter.last_name':'Synthetic','reporter.email':'qa@example.invalid','reporter.contact_preference':'none','consents.truthfulness':true,'consents.media_rights':true,'consents.publication_rules':true,'consents.terms':true,'consents.privacy':true};
   for(const [key,value] of Object.entries(data)){
    const input=root.querySelector(`[name="${key}"]`);
    if(input.type==='checkbox')input.checked=value;else input.value=value;
    input.dispatchEvent(new Event('input',{bubbles:true}));
   }
  });
  for(let step=0;step<7;step++){
   assert.equal(await page.locator('[data-native-step]:visible').count(),1);
   if(step===3 && width>=600){
    const cards=await page.locator('[data-native-field="location.immediate_danger"] .ba-native-card-grid label').all();
    const boxes=await Promise.all(cards.map(c=>c.boundingBox()));
    assert.equal(boxes.length,3);assert(boxes.every(b=>Math.abs(b.y-boxes[0].y)<2),'danger choices share one row');
   }
   for(const group of await page.locator('[data-native-step]:visible .ba-native-card-grid').all()){
    const selected=group.locator('label:has(input:checked)');
    if(await selected.count()){
     await selected.click();
     await page.waitForFunction(el=>el.classList.contains('is-collapsed'),await group.elementHandle());
     assert(await group.evaluate(e=>e.classList.contains('is-collapsed')));
     assert.equal(await group.locator('label:visible').count(),1);
     const edit=group.locator('..').getByRole('button',{name:/Modifica:/});
     await edit.click();
     assert(!(await group.evaluate(e=>e.classList.contains('is-collapsed'))),await group.evaluate(e=>e.closest('[data-native-field]').dataset.nativeField));
     await group.locator('label:has(input:checked)').click();
     await page.waitForFunction(el=>el.classList.contains('is-collapsed'),await group.elementHandle());
    }
   }
   if(step===5){
    const email=await page.locator('[data-native-field="reporter.email"]').boundingBox();
    const phone=await page.locator('[data-native-field="reporter.phone"]').boundingBox();
    if(width>=768)assert(Math.abs(email.y-phone.y)<2,'desktop email and phone share row');
    else assert(phone.y>=email.y+email.height-1,'mobile contacts stack');
    const first=await page.locator('[data-native-field="reporter.first_name"]').boundingBox();
    const last=await page.locator('[data-native-field="reporter.last_name"]').boundingBox();
    if(width>=768)assert(Math.abs(first.y-last.y)<2,'paired desktop names must share row');
    else assert(last.y>=first.y+first.height-1,'mobile names must stack');
   }
   const overflow=await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1);
   assert(!overflow,`horizontal overflow width ${width} step ${step+1}`);
   const controls=await page.locator('[data-native-navigation] button:visible').all();
   for(const control of controls){const rect=await control.boundingBox();assert(rect.height>=44&&rect.width>=44);}
   if(step===5){
    const checkbox=page.locator('[name="consents.privacy"]');await checkbox.focus();
    await page.keyboard.press('Space');assert.equal(await checkbox.isChecked(),false);
    await page.keyboard.press('Space');assert.equal(await checkbox.isChecked(),true);
   }
   if(process.env.BA_SCREENSHOT_DIR)await page.screenshot({path:path.join(process.env.BA_SCREENSHOT_DIR,`nf-${width}-${step+1}.png`),fullPage:true});
   if(step<6){
    await page.getByRole('button',{name:'Continua',exact:true}).click();
    assert(await page.locator('[data-native-step]:visible h2').evaluate(e=>document.activeElement===e));
   }
  }
  await page.close();console.log(`PASS ${width}px: 7 steps, overflow, touch targets, keyboard, focus, validation`);
 }
 }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
