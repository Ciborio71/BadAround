'use strict';
const {chromium}=require('playwright-core'),{execFileSync}=require('node:child_process'),fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
(async()=>{
 const theme=path.join(__dirname,'../wordpress/themes/badaround-child');
 const browser=await chromium.launch({executablePath:process.env.BA_CHROMIUM||'/usr/bin/google-chrome',headless:true,args:['--no-sandbox']});
 try{
 for(const context of ['index','category','single','home']){
 const html=execFileSync('php',[path.join(__dirname,'blog-render.php'),context],{encoding:'utf8'});
 const page=await browser.newPage();await page.route('**/*',r=>r.request().url()==='https://badaround.it/'?r.fulfill({contentType:'text/html',body:html}):r.abort());await page.goto('https://badaround.it/');
 for(const name of ['base','components','shell','blog'])await page.addStyleTag({content:fs.readFileSync(path.join(theme,'assets/css',name+'.css'),'utf8')});
 for(const width of [1440,1281,1280,1181,1180,1024,900,768,390,320]){
 await page.setViewportSize({width,height:1000});
 const v=await page.evaluate(()=>({overflow:document.documentElement.scrollWidth>innerWidth+1,h1:document.querySelectorAll('main h1').length,cards:document.querySelectorAll('.ba-blog-card').length,nav:getComputedStyle(document.querySelector('.ba-primary-nav')).display,headings:[...document.querySelectorAll('.ba-blog-card__title')].every(x=>x.getBoundingClientRect().width>0),main:document.querySelectorAll('#main-content').length}));
 assert(!v.overflow,context+' overflow '+width);assert.equal(v.main,1);assert.equal(v.h1,context==='home'?0:1);if(width<=1280)assert.equal(v.nav,'none');if(context!=='single'){assert.equal(v.cards,3);assert(v.headings);}console.log('PASS blog',context,width);
 }
 assert.equal(await page.locator('meta[name="description"]').count(),context==='home'?0:1);
 await page.close();
 }
 const empty=execFileSync('php',[path.join(__dirname,'blog-render.php'),'index','--empty'],{encoding:'utf8'});assert(empty.includes('Le prime storie stanno arrivando'));assert(!empty.includes('class="ba-blog-card '));
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1);});
