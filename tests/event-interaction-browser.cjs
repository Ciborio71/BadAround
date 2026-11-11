const {chromium}=require(process.env.BA_PLAYWRIGHT_MODULE||'playwright-core'),{execFileSync}=require('node:child_process'),fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
(async()=>{
 const root=path.join(__dirname,'..'),html=execFileSync(process.env.BA_PHP||'php',[path.join(__dirname,'event-interaction-render.php')],{encoding:'utf8'});
 const browser=await chromium.launch({executablePath:process.env.BA_CHROMIUM||'/usr/bin/chromium',headless:true,args:['--no-sandbox']});
 try{
  const page=await browser.newPage();await page.route('**/*',r=>r.abort());
  for(const width of [1440,1024,768,390,320]){
   await page.setViewportSize({width,height:1000});await page.setContent(html);
   for(const file of ['wordpress/themes/badaround-child/assets/css/base.css','wordpress/themes/badaround-child/assets/css/components.css','wordpress/themes/badaround-child/assets/css/pages.css','wordpress/plugins/badaround-core/assets/css/contributions.css'])await page.addStyleTag({content:fs.readFileSync(path.join(root,file),'utf8')});
   for(const file of ['wordpress/plugins/badaround-core/assets/js/contributions.js','wordpress/plugins/badaround-core/identity-dormant/identity-comment-form.js'])await page.addScriptTag({content:fs.readFileSync(path.join(root,file),'utf8')});
   assert.equal(await page.locator('.ba-contribution-cta h2 .ba-witness-icon').count(),1);
   assert.equal(await page.locator('.ba-contribution-cta h2 .ba-witness-icon').getAttribute('aria-hidden'),'true');
   const pub=page.locator('[data-ba-comment-panel]'),priv=page.locator('[data-ba-contribution-panel]');
   assert.equal(await pub.isVisible(),false);assert.equal(await priv.isVisible(),false);
   await page.locator('[data-ba-comment-open]').click();assert(await pub.isVisible());assert.equal(await priv.isVisible(),false);
   await pub.locator('[name=body]').fill('Commento di prova conservato');
   await page.locator('[data-ba-contribution-open]').click();assert(await priv.isVisible());assert.equal(await pub.isVisible(),false);
   assert.equal(await priv.locator('[name=visibility_requested]').inputValue(),'reserved');
   await page.locator('[data-ba-comment-open]').click();assert(await pub.isVisible());assert.equal(await priv.isVisible(),false);assert.equal(await pub.locator('[name=body]').inputValue(),'Commento di prova conservato');
   await page.locator('[data-ba-comment-open]').click();assert.equal(await pub.isVisible(),false);
   const overflow=await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1);assert(!overflow,'interaction overflow '+width);
   console.log('PASS event interaction browser',width);
  }
  await page.evaluate(()=>{const root=document.querySelector('main');root.insertAdjacentHTML('afterbegin','<p class="ba-event-witness-badge"><a href="#contribuisci">Si cercano testimoni</a></p><aside class="ba-event-aside"><section class="ba-card ba-event-safety"><h2>Serve aiuto immediato?</h2><p>Contatta il 112</p></section></aside>');});
  const colors=await page.evaluate(()=>{const w=document.querySelector('.ba-event-witness-badge'),e=document.querySelector('.ba-event-safety');return {w:getComputedStyle(w).backgroundColor,wc:getComputedStyle(w.querySelector('a')).color,e:getComputedStyle(e).backgroundColor,ec:getComputedStyle(e.querySelector('p')).color};});
  assert.equal(colors.w,'rgb(255, 253, 244)');assert.equal(colors.wc,'rgb(23, 23, 23)');assert.equal(colors.e,'rgb(181, 31, 43)');assert.equal(colors.ec,'rgb(255, 255, 255)');
  console.log('PASS witness/emergency palette');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1);});
