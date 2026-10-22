'use strict';
// Isolated Chromium with all network requests intercepted. No staging contact.
const {chromium}=require(process.env.BA_PLAYWRIGHT_MODULE||'playwright'),{execFileSync}=require('node:child_process'),fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const root=path.join(__dirname,'..'),contract=JSON.parse(fs.readFileSync(path.join(root,'wordpress/plugins/badaround-core/contracts/nf-conditional-v1.0.json')));
(async()=>{const html=execFileSync(process.env.BA_PHP||'php',[path.join(__dirname,'native-report-f16.php'),'--html'],{encoding:'utf8'}),browser=await chromium.launch();
try{for(const width of [320,390,1440]){const page=await browser.newPage({viewport:{width,height:1000},reducedMotion:'reduce'}),errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.route('**/*',route=>{const u=new URL(route.request().url());if(u.pathname==='/')return route.fulfill({contentType:'text/html',body:html.replaceAll('staging.badaround.it','localhost')});const file=path.join(root,u.pathname);if(fs.existsSync(file)&&fs.statSync(file).isFile())return route.fulfill({contentType:file.endsWith('.css')?'text/css':'application/javascript',body:fs.readFileSync(file)});return route.abort();});
 await page.goto('https://localhost/');await page.waitForFunction(()=>!!document.querySelector('[data-native-report]').nativeReport);
 for(const [key,spec] of Object.entries(contract.fields).filter(([,v])=>v.source_field)){
  const row=contract.regression_oracle.find(o=>contract.matrix.find(r=>r.subtype===o.subtype).fields[key].state!=='HIDDEN');
  await page.evaluate(({row,key})=>{const root=document.querySelector('[data-native-report]'),m=root.nativeReport.model;m.values={...row.minimal_user_values,'media.availability':'no'};
   function activate(expr){if(expr.all)expr.all.forEach(activate);else if(expr.any)activate(expr.any[0]);else if(expr.op!=='active'){const rule=m.rule(expr.field);if(rule?.condition)activate(rule.condition);m.set(expr.field,expr.op==='contains'?[expr.value]:expr.value??(expr.values.includes(m.effective(expr.field))?m.effective(expr.field):expr.values[0]));}}
   const rule=m.rule(key);activate(rule.condition);m.step=2;const description=document.querySelector('[name="content.description"]');description.value=m.values['content.description'];description.dispatchEvent(new Event('input',{bubbles:true}));
  },{row,key});
  const control=page.locator(`[name="${key}"]`);assert(await control.isVisible(),key+' visible');assert.equal(await control.getAttribute('aria-required'),'true');assert(await control.isEnabled());
  // Keyboard can enter and leave a dependent specification without trapping focus.
  await control.focus();await page.keyboard.type('Specifica da tastiera');await page.keyboard.press('Tab');assert(!(await control.evaluate(e=>e===document.activeElement)),key+' no focus trap');
  assert(await page.evaluate(key=>document.querySelector('[data-native-report]').nativeReport.model.payload()[key.split('.')[0]][key.split('.')[1]]==='Specifica da tastiera',key));
  assert.equal(await control.getAttribute('data-native-max-length'),String(spec.constraints.max_length));
  const r=await control.boundingBox();assert(r.x>=0&&r.x+r.width<=width+1,key+' fits width');
  assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'no horizontal overflow');
  await page.evaluate(key=>{const {model:m,config:c}=document.querySelector('[data-native-report]').nativeReport;const p=c.fields[key].sourceField,f=c.fields[p],v=(f.constraints.enum||f.constraints.item_enum).find(v=>v!=='other');m.set(p,f.type==='array'?[v]:v);document.querySelector('[name="content.description"]').dispatchEvent(new Event('input',{bubbles:true}));},key);
  assert(!(await control.isVisible()));assert(await control.isDisabled());assert.equal(await control.inputValue(),'');
 }
 // Auto-next with an actual choice must target the new dependent input.
 const row=contract.regression_oracle.find(o=>o.subtype==='vehicle_stolen');
 await page.evaluate(row=>{const m=document.querySelector('[data-native-report]').nativeReport.model;m.values={...row.minimal_user_values};m.step=2;delete m.values['authority.status'];delete m.values['authority.type'];document.querySelector('[name="content.description"]').dispatchEvent(new Event('change',{bubbles:true}));},row);
 await page.locator('[data-beta-authority]').selectOption('yes:other');
 await page.waitForFunction(()=>document.activeElement?.name==='authority.type_other_detail');assert(await page.locator('[name="authority.type_other_detail"]').isVisible());
 assert((await page.locator('[role=status]').allTextContents()).some(text=>text.includes('campo obbligatorio')),'dependent announcement');
 if(process.env.BA_SCREENSHOT_DIR)await page.screenshot({path:path.join(process.env.BA_SCREENSHOT_DIR,`nf04-${width}-specification.png`)});
 await page.locator('[data-native-back]').click();await page.locator('[data-native-next]').click();
 await page.reload();assert.equal(await page.evaluate(()=>document.querySelector('[data-native-report]').nativeReport.model.payload().schema_version),'badaround-report/v2');assert(!(await page.locator('[name="authority.type_other_detail"]').isVisible()));assert.deepEqual(errors,[]);
 await page.close();console.log('PASS NF-UX-04',width,'8 specifications, requiredness, keyboard, dynamic cleanup, auto-next, navigation, refresh, geometry');
}}finally{await browser.close();}})().catch(e=>{console.error(e);process.exitCode=1;});
