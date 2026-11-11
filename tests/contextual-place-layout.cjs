const {chromium}=require(process.env.BA_PLAYWRIGHT_MODULE||'playwright-core');
const {execFileSync}=require('node:child_process'),fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
(async()=>{
 const root=path.join(__dirname,'..');
 const html=execFileSync(process.env.BA_PHP||'php',[path.join(__dirname,'native-report-f16.php'),'--html'],{encoding:'utf8'}).replace(/<script src=[^>]*><\/script>/g,'').replace(/<link[^>]*>/g,'');
 const browser=await chromium.launch({executablePath:process.env.BA_CHROMIUM||'/usr/bin/chromium',headless:true,args:['--no-sandbox']});
 try{
 const page=await browser.newPage();await page.route('**/*',r=>r.abort());await page.setContent(html);
 for(const name of ['base','components','report','native-report','map-navigation'])await page.addStyleTag({content:fs.readFileSync(path.join(root,'wordpress/themes/badaround-child/assets/css',name+'.css'),'utf8')});
 for(const name of ['model','api','errors','media','media-view','wizard'])await page.addScriptTag({content:fs.readFileSync(path.join(root,'wordpress/themes/badaround-child/assets/js/native-report',name+'.js'),'utf8')});
 await page.evaluate(()=>{const r=document.querySelector('[data-native-report]'),m=r.nativeReport.model;m.set('event.category','item_document');m.set('event.subtype','wallet_docs');m.step=2;const input=r.querySelector('[name="content.description"]');input.value='Contenuto sintetico';input.dispatchEvent(new Event('input',{bubbles:true}));});
 for(const width of [2048,1440,1280,1100,1025,1024,768,390]){
  await page.setViewportSize({width,height:1000});await page.waitForTimeout(60);
  if(width>=1025){
   // Force a long contextual question to reproduce multi-line labels independently of copy.
   await page.evaluate(()=>{const r=document.querySelector('[data-native-report]');r.nativeReport.config.conditional.profiles.wallet_docs.placeQuestion='In quale tipo di luogo è stato smarrito o trovato l’oggetto?';r.querySelector('[name="content.description"]').dispatchEvent(new Event('input',{bubbles:true}));});
  }
  const result=await page.evaluate(()=>{
   const r=document.querySelector('.ba-native-place-danger-row'),a=r.children[0],b=r.children[1],first=a.querySelector('select'),second=b.querySelector('.ba-native-choice-row');
   return {left:first.getBoundingClientRect().top,right:second.getBoundingClientRect().top,overflow:r.scrollWidth>r.clientWidth+1,first:a.getBoundingClientRect().width,second:b.getBoundingClientRect().width,row:r.getBoundingClientRect().width};
  });
  assert(!result.overflow,'row overflow '+width+JSON.stringify(result));
  if(width>=1025)assert(Math.abs(result.left-result.right)<=2,'controls aligned '+width+JSON.stringify(result));
  else assert(result.first<=result.row+1&&result.second<=result.row+1,'stacked widths '+width);
  console.log('PASS contextual place geometry',width);
 }
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1);});
