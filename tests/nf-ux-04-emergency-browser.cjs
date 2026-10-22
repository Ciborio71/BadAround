'use strict';
// Synthetic local browser only: intercept every request; never contact staging.
const {chromium}=require(process.env.BA_PLAYWRIGHT_MODULE||'playwright');
const {execFileSync}=require('node:child_process');
const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
(async()=>{
 const html=execFileSync(process.env.BA_PHP||'php',[path.join(__dirname,'native-report-f16.php'),'--html'],{encoding:'utf8'});
 const contract=JSON.parse(fs.readFileSync(path.join(__dirname,'../wordpress/plugins/badaround-core/contracts/nf-conditional-v1.0.json')));
 const auto=contract.matrix.filter(r=>r.emergency.automatic_subtype_notice);
 const browser=await chromium.launch({headless:true});
 try{for(const width of [320,390,1440]){
  const page=await browser.newPage({viewport:{width,height:900},reducedMotion:'reduce'});const errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.route('**/*',async route=>{const u=new URL(route.request().url());if(u.pathname==='/')return route.fulfill({contentType:'text/html',body:html.replaceAll('staging.badaround.it','localhost')});const file=path.join(__dirname,'..',u.pathname);if(fs.existsSync(file)&&fs.statSync(file).isFile())return route.fulfill({contentType:file.endsWith('.css')?'text/css':'application/javascript',body:fs.readFileSync(file)});return route.abort();});
  await page.goto('https://localhost/');assert(await page.evaluate(()=>!!document.querySelector('[data-native-report]').nativeReport),'local fixture boots with same-origin endpoints');
  const notice=page.locator('[data-native-emergency-notice]');
  const set=async data=>page.evaluate(data=>{for(const [key,value] of Object.entries(data)){const el=document.querySelector(`[name="${key}"]`);if(el.type==='checkbox')el.checked=value;else el.value=value;el.dispatchEvent(new Event('change',{bubbles:true}));}},data);
  assert(!(await notice.isVisible()));
  for(const row of auto){await set({'event.category':row.category,'event.subtype':row.subtype,'location.immediate_danger':'no'});assert(await notice.isVisible(),row.subtype);assert.equal(await notice.count(),1);}
  await set({'event.category':'vehicle','event.subtype':'vehicle_stolen','location.immediate_danger':'no'});
  await page.locator('[data-native-field="event.subtype"] input[type=radio][value=vehicle_hit_and_run]').focus();await page.keyboard.press('Space');
  await page.waitForFunction(()=>document.activeElement?.name==='ba-card-reporter.relationship');
  const liveRect=await notice.boundingBox();assert(liveRect.y>=0&&liveRect.y+liveRect.height<=900,'auto-focus keeps emergency notice in view');
  await notice.scrollIntoViewIfNeeded();
  assert.equal(await notice.getAttribute('role'),'status');
  const metrics=await notice.evaluate(el=>{const r=el.getBoundingClientRect(),s=getComputedStyle(el);return {left:r.left,right:r.right,width:innerWidth,color:s.color,bg:s.backgroundColor,overflow:document.documentElement.scrollWidth>innerWidth+1};});
  assert(metrics.left>=0&&metrics.right<=width+1&&!metrics.overflow,'notice fits viewport');
  const luminance=color=>{const rgb=color.match(/[\d.]+/g).slice(0,3).map(Number).map(x=>{x/=255;return x<=.04045?x/12.92:((x+.055)/1.055)**2.4;});return .2126*rgb[0]+.7152*rgb[1]+.0722*rgb[2];};
  const l=[luminance(metrics.color),luminance(metrics.bg)].sort((a,b)=>a-b);assert((l[1]+.05)/(l[0]+.05)>=4.5,'WCAG normal text contrast');
  if(process.env.BA_SCREENSHOT_DIR)await page.screenshot({path:path.join(process.env.BA_SCREENSHOT_DIR,`nf03-${width}-subtype.png`)});
  await set({'event.category':'vehicle','event.subtype':'vehicle_stolen','reporter.relationship':'territory_observer','location.region':'Lazio','location.province':'Roma','location.municipality':'Pomezia','location.exact_address':'Via Test 1, Pomezia','location.place_type':'road_sidewalk','location.immediate_danger':'no','time.mode':'unknown'});
  assert(!(await notice.isVisible()));await page.locator('[data-native-next]').click();await page.locator('[data-native-next]').click();
  assert.equal(await page.locator('[data-native-step="details"]:visible').count(),1);
  // Use actual keyboard control: re-open the answer cards and choose yes.
  const danger=page.locator('[data-native-field="location.immediate_danger"]');
  const edit=danger.getByRole('button',{name:/Modifica:/});if(await edit.isVisible())await edit.click();
  await danger.locator('input[type=radio][value=yes]').focus();await page.keyboard.press('Space');
  await notice.waitFor({state:'visible'});assert(!(await notice.evaluate(el=>el.contains(document.activeElement))),'notice does not steal focus');
  assert.equal(await notice.evaluate(el=>el.previousElementSibling.dataset.nativeField),'location.immediate_danger');
  await notice.scrollIntoViewIfNeeded();if(process.env.BA_SCREENSHOT_DIR)await page.screenshot({path:path.join(process.env.BA_SCREENSHOT_DIR,`nf03-${width}-danger.png`)});
  const rect=await notice.boundingBox();const nav=await page.locator('[data-native-navigation]').boundingBox();assert(rect.y+rect.height<=nav.y||rect.y>=nav.y+nav.height,'no navigation overlay');
  await page.locator('[data-native-back]').click();assert(await notice.isVisible());await page.locator('[data-native-next]').click();assert(await notice.isVisible());
  await set({'location.immediate_danger':'unsure'});assert(await notice.isVisible());await set({'location.immediate_danger':'no'});assert(!(await notice.isVisible()));
  await page.reload();assert(!(await notice.isVisible()),'refresh starts clean; no persistent draft storage');assert.equal(await page.evaluate(()=>document.querySelector('[data-native-report]').nativeReport.model.payload().schema_version),'badaround-report/v2');
  assert.deepEqual(errors,[]);await page.close();console.log(`PASS NF-UX-03 ${width}px: nine triggers, global state, contrast, geometry, keyboard, navigation and refresh`);
 }}finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
