'use strict';
// Compare actual computed home/form pin styles, not just matching SVG paths.
const {chromium}=require(process.env.BA_PLAYWRIGHT_MODULE||'playwright');
const {execFileSync}=require('node:child_process'),fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
(async()=>{
 const html=execFileSync(process.env.BA_PHP||'php',[path.join(__dirname,'native-report-f16.php'),'--html'],{encoding:'utf8'}).replace('<body','<link rel="stylesheet" href="/wordpress/themes/badaround-child/assets/css/home.css"><body');
 const browser=await chromium.launch({headless:true,...(process.env.BA_CHROMIUM?{executablePath:process.env.BA_CHROMIUM}:{}),args:['--no-sandbox','--disable-dev-shm-usage']});
 try{for(const width of [320,390,768,1440]){
  const page=await browser.newPage({viewport:{width,height:850},reducedMotion:'reduce'});
  await page.route('**/*',route=>{
   const url=new URL(route.request().url());if(url.pathname==='/')return route.fulfill({contentType:'text/html',body:html.replaceAll('staging.badaround.it','localhost')});
   const file=path.join(__dirname,'..',url.pathname);if(fs.existsSync(file))return route.fulfill({contentType:file.endsWith('.css')?'text/css':'application/javascript',body:fs.readFileSync(file)});return route.abort();
  });
  await page.goto('https://localhost/');
  const compare=()=>page.evaluate(()=>{
   const result=[],classes=['is-vehicles','is-property','is-hazard','is-public','is-animal','is-object'];
   const icons=[...document.querySelectorAll('.ba-native-category-grid .ba-native-card-icon')];
   for(const [index,icon] of icons.entries()){
    if(icon.closest('label').hidden)continue;
    const home=document.createElement('span');home.className='ba-home-category__pin '+classes[index];home.append(icon.querySelector('svg').cloneNode(true));document.body.append(home);
    const a=getComputedStyle(icon),b=getComputedStyle(home),aSvg=getComputedStyle(icon.querySelector('svg')),bSvg=getComputedStyle(home.querySelector('svg'));
    for(const prop of ['backgroundColor','color','width','height','borderRadius','borderTopWidth','borderTopColor','transform','boxShadow'])if(a[prop]!==b[prop])result.push(`${classes[index]} ${prop}: ${a[prop]} != ${b[prop]}`);
    for(const prop of ['width','height','transform','fill','stroke','strokeWidth','strokeLinecap','strokeLinejoin'])if(aSvg[prop]!==bSvg[prop])result.push(`${classes[index]} svg ${prop}: ${aSvg[prop]} != ${bSvg[prop]}`);
    home.remove();
   }return result;
  });
  assert.deepEqual(await compare(),[]);
  await page.locator('.ba-native-category-grid label').filter({hasText:'Veicoli'}).click();
  await page.waitForFunction(()=>document.querySelector('.ba-native-category-grid').classList.contains('is-collapsed'));
  assert.deepEqual(await compare(),[]);
  assert(!(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1)));
  if(process.env.BA_SCREENSHOT_DIR)await page.screenshot({path:path.join(process.env.BA_SCREENSHOT_DIR,`pin-${width}.png`),fullPage:false});
  console.log(`PASS ${width}px: six home/form pins match computed styles, including collapsed state`);await page.close();
 }}finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
