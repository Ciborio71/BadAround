'use strict';
const {chromium}=require('playwright-core'),{execFileSync}=require('node:child_process'),fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
(async()=>{
 const root=path.join(__dirname,'..'),assets=path.join(root,'wordpress/themes/badaround-child/assets');
 let html=execFileSync('php',[path.join(__dirname,'native-report-f16.php'),'--html'],{encoding:'utf8'}).replace(/<script src=[^>]*><\/script>/g,'').replace(/<link[^>]*>/g,'');
 html='<header class="ba-site-header"><a href="#report" class="ba-button ba-button--urgent">Segnala un evento</a></header>'+html;
 const browser=await chromium.launch({executablePath:process.env.BA_CHROMIUM||'/usr/bin/google-chrome',headless:true,args:['--no-sandbox']});
 try{
 const page=await browser.newPage();await page.route('**/*',r=>r.request().url()==='https://staging.badaround.it/'?r.fulfill({contentType:'text/html',body:html}):r.abort());await page.goto('https://staging.badaround.it/');
 for(const name of ['base','components','report','native-report'])await page.addStyleTag({content:fs.readFileSync(path.join(assets,'css',name+'.css'),'utf8')});
 for(const name of ['model','api','errors','media','media-view','wizard','location-map'])await page.addScriptTag({content:fs.readFileSync(path.join(assets,'js/native-report',name+'.js'),'utf8')});
 await page.evaluate(async()=>{
 const root=document.querySelector('[data-native-report]'),wizard=root.nativeReport;wizard.model.set('event.category','animal');
 window.BadAroundMapNavigation={options:()=>({})};
 class MapFixture{constructor(canvas){this.canvas=canvas;}setCenter(){}}
 class MarkerFixture{constructor(options){this.position=options.position;this.node=document.createElement('div');options.map.canvas.append(this.node);}replaceChildren(...nodes){this.node.replaceChildren(...nodes);}addEventListener(){}removeEventListener(){}}
 window.google={maps:{importLibrary:async name=>name==='maps'?{Map:MapFixture}:{AdvancedMarkerElement:MarkerFixture}}};
 const bridge={ticket:()=>1,current:()=>true,load:async()=>{}};
 const controller=window.BadAroundNative.LocationMap.mount(root,wizard.config,wizard.model,bridge);await controller.show({lat:41.9,lng:12.5});window.fixtureMapController=controller;
 });
 const marker=await page.evaluate(()=>{const svg=document.querySelector('[data-smart-map] .ba-native-location-pin svg'),gradient=svg?.querySelector('linearGradient'),path=svg?.querySelector('path[fill^="url"]');return {present:!!svg,id:gradient?.id,fill:path?.getAttribute('fill'),unique:gradient?document.querySelectorAll('[id="'+gradient.id+'"]').length:0};});
 assert(marker.present,'complete SVG marker');assert(marker.id.startsWith('ba-location-'));assert.equal(marker.fill,'url(#'+marker.id+')');assert.equal(marker.unique,1);
 for(const width of [1440,1024,768,390,320]){
 await page.setViewportSize({width,height:1100});
 const result=await page.evaluate(()=>{
 const root=document.querySelector('[data-native-report]'),wizard=root.nativeReport;wizard.model.step=1;wizard.render?.();
 const step=root.querySelector('[data-native-smart-address]').closest('[data-native-step]');document.querySelectorAll('[data-native-step]').forEach(el=>el.hidden=el!==step);
 const layout=document.querySelector('[data-smart-location-layout]');layout.hidden=false;layout.classList.add('has-map');document.querySelector('[data-smart-location-card]').hidden=false;document.querySelector('[data-smart-confirmation]').hidden=false;document.querySelector('[data-smart-map-panel]').hidden=false;document.querySelector('[data-smart-address-label]').textContent='Via di prova 123, Roma';
 const choice=document.querySelector('[data-native-public-location-choice]');choice.hidden=false;
 const side=document.querySelector('[data-smart-location-side]');
 const input=document.querySelector('[data-smart-start]');input.focus();const slotStyle=getComputedStyle(input.parentElement),locationStyle=getComputedStyle(document.querySelector('[data-smart-location-card]'));
 const next=document.querySelector('[data-native-next]');next.hidden=false;next.disabled=false;const nextStyle=getComputedStyle(next);
 const overlay=document.querySelector('[data-native-submit-feedback]');overlay.hidden=false;const panel=overlay.firstElementChild,rect=panel.getBoundingClientRect();overlay.hidden=true;
 return {overflow:document.documentElement.scrollWidth>innerWidth+1,zoneAbsent:!side.querySelector('.ba-native-neighborhood'),ring:slotStyle.outlineStyle,bg:locationStyle.backgroundColor,blue:nextStyle.backgroundColor,white:nextStyle.color,inline:Math.abs(document.querySelector('[data-smart-confirmation]>strong').getBoundingClientRect().top-document.querySelector('[data-smart-address-label]').getBoundingClientRect().top)<8,center:Math.abs((rect.left+rect.right)/2-innerWidth/2)<2,placeholder:input.placeholder};
 });
 assert(!result.overflow,'overflow '+width);assert(result.zoneAbsent);if(width>=768)assert(result.inline,'inline address '+width);assert.equal(result.ring,'none');assert.equal(result.bg,'rgba(0, 0, 0, 0)');assert.equal(result.blue,'rgb(26, 115, 232)');assert.equal(result.white,'rgb(255, 255, 255)');assert(result.center);assert.equal(result.placeholder,'Cerca indirizzo, comune o quartiere…');
 const cta=page.locator('.ba-site-header a');await cta.focus();assert.equal(await cta.evaluate(el=>getComputedStyle(el).color),'rgb(255, 255, 255)');await cta.hover();assert.equal(await cta.evaluate(el=>getComputedStyle(el).color),'rgb(255, 255, 255)');
 console.log('PASS wizard presentation viewport',width);
 }
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1);});
