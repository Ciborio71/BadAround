'use strict';
const {chromium,fs,ck,checks,boot,set,seed,contract}=require('./nf05-helpers.cjs');
(async()=>{const browser=await chromium.launch();try{for(const width of [320,390,1440]){const {context,page}=await boot(browser,width);
for(const subtype of ['vehicle_stolen','civic_waste','property_theft','hazard_fire_or_smoke','animal_missing']){
 await seed(page,contract.regression_oracle.find(o=>o.subtype===subtype),2);if(subtype==='property_theft')await page.evaluate(()=>{const m=document.querySelector('[data-native-report]').nativeReport.model;m.set('property.stolen_item_categories',['other']);m.set('property.stolen_item_categories_other_detail','Specifica sintetica NF05');document.querySelector('[name="content.description"]').dispatchEvent(new Event('change',{bubbles:true}));});
 const state=await page.evaluate(()=>{const root=document.querySelector('[data-native-report]'),m=root.nativeReport.model;return {overflow:document.documentElement.scrollWidth>innerWidth+1,hiddenFocus:[...root.querySelectorAll('[data-native-field][hidden] input')].filter(e=>!e.disabled).length,step:m.step};});ck(!state.overflow&&!state.hiddenFocus,'profile layout '+subtype+' '+width);
 const input=page.locator('[name="content.description"]');await input.focus();await page.keyboard.press('Tab');ck(await input.evaluate(e=>document.activeElement!==e),'keyboard no trap '+subtype+' '+width);await page.locator('[data-native-next]').scrollIntoViewIfNeeded();ck(await page.locator('[data-native-next]').isVisible(),'CTA reachable '+subtype+' '+width);
 await page.screenshot({path:`evidence/screenshots/nf05-${width}-${subtype}.png`});
}
 await context.close();console.log('NF05_RESPONSIVE_PASS',width);
}fs.writeFileSync('evidence/responsive05.json',JSON.stringify({pass:true,checks},null,2));}finally{await browser.close();}})().catch(e=>{fs.writeFileSync('evidence/nf05-responsive-failure.json',JSON.stringify({message:e.message,stack:e.stack},null,2));console.error(e);process.exitCode=1;});
