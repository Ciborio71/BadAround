const {chromium}=require(process.env.BA_PLAYWRIGHT_MODULE||'playwright-core'),{execFileSync}=require('node:child_process'),fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
(async()=>{
 const root=path.join(__dirname,'..'),catalog=JSON.parse(fs.readFileSync(path.join(root,'wordpress/themes/badaround-child/assets/pin-designs.json'),'utf8'));
 const keys=['veicoli','case-e-attivita','pericoli','spazi-pubblici','animali','oggetti-e-documenti'];
 let html=execFileSync(process.env.BA_PHP||'php',[path.join(__dirname,'native-report-f16.php'),'--html'],{encoding:'utf8'}).replace(/<script src=[^>]*><\/script>/g,'').replace(/<link[^>]*>/g,'');
 html+=execFileSync(process.env.BA_PHP||'php',[path.join(__dirname,'event-area-artwork.php'),'--html'],{encoding:'utf8'});
 const legend=execFileSync(process.env.BA_PHP||'php',[path.join(__dirname,'precision-legend.php'),'--html'],{encoding:'utf8'});
 html+='<section class="ba-container"><div class="ba-home-sectionhead" data-test-precision-header><h2 class="ba-home-display-heading">Ultime Segnalazioni</h2>'+legend+'</div><aside class="ba-map-commandbar__summary">'+legend+'</aside></section>';
 html+='<section class="ba-home-categories">'+keys.map(k=>'<a class="ba-home-category-card"><span class="ba-home-category__pin">'+catalog[k].svg+'</span><span>Categoria '+k+'</span></a>').join('')+'</section><details class="ba-map-legend" open>'+keys.map(k=>'<span class="ba-map-legend__pin">'+catalog[k].svg+'</span>').join('')+'</details>';
 const browser=await chromium.launch({executablePath:process.env.BA_CHROMIUM||'/usr/bin/chromium',headless:true,args:['--no-sandbox']});
 try{
 const page=await browser.newPage();await page.route('**/*',r=>r.request().url()==='https://staging.badaround.it/'?r.fulfill({contentType:'text/html',body:html}):r.abort());await page.goto('https://staging.badaround.it/');
 for(const name of ['base','components','report','native-report','map','home','how-it-works','sentinels'])await page.addStyleTag({content:fs.readFileSync(path.join(root,'wordpress/themes/badaround-child/assets/css',name+'.css'),'utf8')});
 for(const name of ['model','api','errors','media','media-view','wizard'])await page.addScriptTag({content:fs.readFileSync(path.join(root,'wordpress/themes/badaround-child/assets/js/native-report',name+'.js'),'utf8')});
 for(const width of [1440,1024,768,390,320]){
  await page.setViewportSize({width,height:1100});
  const result=await page.evaluate(()=>{
   const icons=[...document.querySelectorAll('.ba-native-category-grid .ba-native-card-icon svg')];
   return {count:icons.length,large:icons.every(svg=>{const pin=svg.getBoundingClientRect(),card=svg.closest('.ba-native-option-card').getBoundingClientRect();return pin.height>=80&&pin.height/card.height>=.6&&pin.height<card.height&&pin.right<=card.right&&pin.left>=card.left}),clear:icons.every(svg=>getComputedStyle(svg).transform==='none'&&!!svg.querySelector('circle[fill="#fff"]')&&getComputedStyle(svg.closest('.ba-native-card-icon')).transform==='none'),home:[...document.querySelectorAll('.ba-home-category__pin svg')].every(svg=>getComputedStyle(svg).transform==='none'),overflow:document.documentElement.scrollWidth>innerWidth+1};
  });
  const areas=await page.evaluate(()=>[...document.querySelectorAll('.ba-event-card--approximate')].map(card=>{const svg=card.querySelector('.ba-pin-svg--area'),rect=svg?.getBoundingClientRect(),photo=card.querySelector('img').getAttribute('src').includes('/uploads/');return {photo,badge:!!card.querySelector('.ba-event-card__media-category'),square:!rect||Math.abs(rect.width-rect.height)<1,white:!svg||!!svg.querySelector('circle[fill="#fff"]'),radius:card.textContent.includes('raggio 100 m')};}));
  assert.equal(areas.length,2);areas.forEach(area=>{assert.equal(area.badge,area.photo);assert(area.square);assert(area.white);assert(area.radius);});
  assert.equal(result.count,6);assert(result.large,'large contained category pins '+width);assert(result.clear,'unrotated SVG with white center '+width);assert(result.home);assert(!result.overflow,'pin layout overflow '+width);
  const precision=await page.evaluate(()=>{const row=document.querySelector('[data-test-precision-header]'),title=row.querySelector('h2').getBoundingClientRect(),key=row.querySelector('.ba-precision-legend'),rect=key.getBoundingClientRect();return {below:rect.top>=title.bottom-1,aligned:Math.abs((rect.top+rect.bottom-title.top-title.bottom)/2)<3,labels:key.textContent.replace(/\s+/g,' ').trim(),interactive:!!key.querySelector('a,button,input,select'),inside:rect.left>=row.getBoundingClientRect().left&&rect.right<=row.getBoundingClientRect().right+1};});
  assert.equal(precision.labels,'Punto esatto Area approssimata');assert(!precision.interactive);assert(precision.inside);if(width<=767)assert(precision.below);if(width>=1440)assert(precision.aligned);
  console.log('PASS pin system layout',width);
 }
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1);});
