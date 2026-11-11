const {chromium}=require(process.env.BA_PLAYWRIGHT_MODULE||'playwright-core'),{execFileSync}=require('node:child_process'),fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
(async()=>{
 const root=path.join(__dirname,'..'),catalog=JSON.parse(fs.readFileSync(path.join(root,'wordpress/themes/badaround-child/assets/pin-designs.json'),'utf8'));
 const keys=['veicoli','case-e-attivita','pericoli','spazi-pubblici','animali','oggetti-e-documenti'];
 let html=execFileSync(process.env.BA_PHP||'php',[path.join(__dirname,'native-report-f16.php'),'--html'],{encoding:'utf8'}).replace(/<script src=[^>]*><\/script>/g,'').replace(/<link[^>]*>/g,'');
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
   return {count:icons.length,clear:icons.every(svg=>getComputedStyle(svg).transform==='none'&&!!svg.querySelector('circle[fill="#fff"]')&&getComputedStyle(svg.closest('.ba-native-card-icon')).transform==='none'),home:[...document.querySelectorAll('.ba-home-category__pin svg')].every(svg=>getComputedStyle(svg).transform==='none'),overflow:document.documentElement.scrollWidth>innerWidth+1};
  });
  assert.equal(result.count,6);assert(result.clear,'unrotated SVG with white center '+width);assert(result.home);assert(!result.overflow,'pin layout overflow '+width);
  console.log('PASS pin system layout',width);
 }
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1);});
