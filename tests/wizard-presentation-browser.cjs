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
 class MarkerFixture{constructor(options){this.position=options.position;window.fixtureMarker=this;this.node=document.createElement('div');options.map.canvas.append(this.node);}replaceChildren(...nodes){this.node.replaceChildren(...nodes);}addEventListener(){}removeEventListener(){}}
 class CircleFixture{constructor(options){this.options=options;window.fixtureCircle=this;}setOptions(options){Object.assign(this.options,options);}setCenter(center){this.center=center;}setRadius(radius){this.radius=radius;}setMap(map){this.map=map;}}
 window.google={maps:{Circle:CircleFixture,importLibrary:async name=>name==='maps'?{Map:MapFixture}:{AdvancedMarkerElement:MarkerFixture}}};
 const bridge={ticket:()=>1,current:()=>true,load:async()=>{}};
 const controller=window.BadAroundNative.LocationMap.mount(root,wizard.config,wizard.model,bridge);await controller.show({lat:41.9,lng:12.5});window.fixtureMapController=controller;
 });
 const marker=await page.evaluate(()=>{const svg=document.querySelector('[data-smart-map] .ba-native-location-pin svg'),gradient=svg?.querySelector('linearGradient'),path=svg?.querySelector('path[fill^="url"]');return {present:!!svg,id:gradient?.id,fill:path?.getAttribute('fill'),unique:gradient?document.querySelectorAll('[id="'+gradient.id+'"]').length:0};});
 assert(marker.present,'complete SVG marker');assert(marker.id.startsWith('ba-location-'));assert.equal(marker.fill,'url(#'+marker.id+')');assert.equal(marker.unique,1);
 const modes=await page.evaluate(()=>{
  const wizard=document.querySelector('[data-native-report]').nativeReport,results=[];
  for(const category of ['vehicle','property','hazard','public_space','animal','item_document']){
   wizard.model.values['event.category']=category;wizard.model.publicLocationRequest='approximate_area';wizard.model.publicLocationRadius=100;window.fixtureMapController.sync();
   const svg=document.querySelector('[data-smart-map] .ba-native-location-pin svg');
   results.push({category,round:svg.getAttribute('viewBox')==='0 -1 48 48'&&!svg.querySelector(':scope > path'),width:svg.getAttribute('width'),height:svg.getAttribute('height'),anchor:window.fixtureMarker.anchorTop,color:window.fixtureCircle.options.strokeColor,fill:window.fixtureCircle.options.fillColor,radius:window.fixtureCircle.radius});
  }
  wizard.model.publicLocationRadius=250;window.fixtureMapController.sync();const radius=window.fixtureCircle.radius;
  wizard.model.publicLocationRequest='exact_point';window.fixtureMapController.sync();
  const svg=document.querySelector('[data-smart-map] .ba-native-location-pin svg');
  return {results,radius,exact:!!svg.querySelector(':scope > path'),height:svg.getAttribute('height'),anchor:window.fixtureMarker.anchorTop,circleHidden:window.fixtureCircle.map===null};
 });
 const colors=['#1677E8','#C9153D','#F57C00','#0AA184','#7040C8','#E3A415'];
 modes.results.forEach((r,i)=>{assert(r.round);assert.equal(r.width,'80');assert.equal(r.height,'80');assert.equal(r.anchor,'-50%');assert.equal(r.color,colors[i]);assert.equal(r.fill,colors[i]);assert.equal(r.radius,100);});
 assert.equal(modes.radius,250);assert(modes.exact);assert.equal(modes.height,'100');assert.equal(modes.anchor,'-100%');assert(modes.circleHidden);

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
 const addressStyle=getComputedStyle(document.querySelector('[data-smart-address-label]')),titleStyle=getComputedStyle(document.querySelector('[data-smart-confirmation]>strong'));
 return {addressSize:addressStyle.fontSize,titleSize:titleStyle.fontSize,addressColor:addressStyle.color,overflow:document.documentElement.scrollWidth>innerWidth+1,zoneAbsent:!side.querySelector('.ba-native-neighborhood'),ring:slotStyle.outlineStyle,bg:locationStyle.backgroundColor,blue:nextStyle.backgroundColor,white:nextStyle.color,inline:Math.abs(document.querySelector('[data-smart-confirmation]>strong').getBoundingClientRect().top-document.querySelector('[data-smart-address-label]').getBoundingClientRect().top)<8,center:Math.abs((rect.left+rect.right)/2-innerWidth/2)<2,placeholder:input.placeholder};
 });
 assert.equal(result.addressSize,'17.6px');assert.equal(result.addressSize,result.titleSize);assert.equal(result.addressColor,'rgb(5, 107, 210)');assert(!result.overflow,'overflow '+width);assert(result.zoneAbsent);if(width>=768)assert(result.inline,'inline address '+width);assert.equal(result.ring,'none');assert.equal(result.bg,'rgba(0, 0, 0, 0)');assert.equal(result.blue,'rgb(26, 115, 232)');assert.equal(result.white,'rgb(255, 255, 255)');assert(result.center);assert.equal(result.placeholder,'Cerca indirizzo, comune o quartiere…');
 const cta=page.locator('.ba-site-header a');await cta.focus();assert.equal(await cta.evaluate(el=>getComputedStyle(el).color),'rgb(255, 255, 255)');await cta.hover();assert.equal(await cta.evaluate(el=>getComputedStyle(el).color),'rgb(255, 255, 255)');
 console.log('PASS wizard presentation viewport',width);
 }
 await page.evaluate(()=>{
 const root=document.querySelector('[data-native-report]');root.nativeReport.model.step=4;
 const answers={'event.category':'animal','event.subtype':'animal_missing','reporter.public_identity_mode':'anonymous','reporter.first_name':'Marco','reporter.last_name':'Rossi','reporter.email':'qa@example.invalid','reporter.contact_preference':'community','reporter.social_visibility':'declined'};
 for(const [key,value]of Object.entries(answers)){const input=document.querySelector('[name="'+key+'"]');input.value=value;input.dispatchEvent(new Event('input',{bubbles:true}));}
 });
 for(const width of [1440,1024,768,390,320]){
 await page.setViewportSize({width,height:1100});
 const result=await page.evaluate(()=>{
 const box=document.querySelector('[data-native-field="reporter.contact_preference"]'),radios=[...box.querySelectorAll('.ba-native-card-grid input[type="radio"]')],footer=box.querySelector(':scope>.ba-native-help');
 return {visible:!box.hidden&&!box.closest('[data-native-step]').hidden,count:radios.length,checked:radios.find(r=>r.checked)?.value,radioVisible:radios.every(r=>getComputedStyle(r).opacity==='1'&&r.getBoundingClientRect().width>=20),expanded:!box.querySelector('.ba-native-card-grid').classList.contains('is-collapsed'),footer:footer.textContent.includes('Privacy garantita'),social:(()=>{const social=document.querySelector('.ba-native-engagement--social'),check=social.querySelector('[data-native-social-consent]'),icons=[...social.querySelectorAll('.ba-native-social-icon')];return !social.hidden&&!check.checked&&!check.disabled&&check.getBoundingClientRect().width>=20&&getComputedStyle(social).backgroundColor!==getComputedStyle(box).backgroundColor&&icons.length===5&&icons.every(icon=>icon.getBoundingClientRect().width===16)&&social.querySelector('.ba-native-engagement-icon--social')&&box.querySelector('.ba-native-engagement-icon--network');})(),overflow:document.documentElement.scrollWidth>innerWidth+1};
 });
 assert(result.visible,'network box visible '+width);assert.equal(result.count,2);assert.equal(result.checked,'community');assert(result.radioVisible);assert(result.expanded);assert(result.footer);assert(result.social,'unchecked compact social box '+width);assert(!result.overflow,'network box overflow '+width);console.log('PASS network choices viewport',width);
 }
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1);});
