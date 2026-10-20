'use strict';
// Real staging page, real browser Geolocation API and live Google APIs.
// Coordinates are a synthetic public-place fixture, never a user's location.
const {chromium}=require(process.env.BA_PLAYWRIGHT_MODULE||'playwright');
const fs=require('node:fs'),assert=require('node:assert/strict');
const origin='https://staging.badaround.it';
(async()=>{
 fs.mkdirSync('geo-ux-evidence',{recursive:true});
 const browser=await chromium.launch({headless:true,args:['--no-sandbox']});
 const evidence=[];
 try{for(const mobile of [false,true]){
  const context=await browser.newContext({viewport:{width:mobile?390:1440,height:900},isMobile:mobile,hasTouch:mobile,permissions:['geolocation'],geolocation:{latitude:41.66934,longitude:12.50273,accuracy:5},reducedMotion:'reduce'});
  const page=await context.newPage();page.setDefaultTimeout(20000);
  const result={device:mobile?'mobile-emulation':'desktop',pageErrors:[]};evidence.push(result);
  page.on('pageerror',error=>result.pageErrors.push(error.message.replace(/key=[^&\s]+/g,'key=REDACTED')));
  try{
   await page.goto(origin+'/segnala-un-evento/',{waitUntil:'domcontentloaded'});
   await page.getByRole('radio',{name:/^Spazi pubblici/}).click();
   await page.getByRole('radio',{name:'Rifiuti abbandonati o discarica',exact:true}).click();
   await page.getByRole('radio',{name:'Ho osservato la situazione nella zona',exact:true}).click();
   await page.getByRole('button',{name:'Continua',exact:true}).click();
   result.browser=await page.evaluate(()=>({secure:isSecureContext,geolocation:!!navigator.geolocation,googleVersion:window.google?.maps?.version||null}));
   assert(result.browser.secure&&result.browser.geolocation);
   await page.getByRole('button',{name:'Usa la mia posizione',exact:true}).click();
   await page.waitForFunction(()=>document.querySelector('[name="location.exact_lat"]').value==='41.66934',{timeout:30000});
   await page.locator('[data-smart-map-panel]').waitFor({state:'visible'});
   result.deviceCoordinatesExact=await page.evaluate(()=>{const v=document.querySelector('[data-native-report]').nativeReport.model.values;return v['location.exact_lat']==='41.66934'&&v['location.exact_lng']==='12.50273';});
   assert(result.deviceCoordinatesExact);result.gps='PASS';
   const marker=page.locator('[data-smart-map] gmp-advanced-marker');
   await marker.waitFor({state:'visible'});await marker.focus();await marker.press('Alt+Enter');await marker.press('ArrowRight');await marker.press('ArrowRight');await marker.press('Enter');
   await page.waitForFunction(()=>{const v=document.querySelector('[data-native-report]').nativeReport.model.values;return v['location.exact_lat']&&v['location.exact_lng']&&v['location.exact_lng']!=='12.50273'&&!document.querySelector('[data-smart-geolocate]').disabled;});
   result.pinMoved='PASS';
   result.finalPoint=await page.evaluate(()=>{const {model}=document.querySelector('[data-native-report]').nativeReport;return {lat:Number(model.values['location.exact_lat']),lng:Number(model.values['location.exact_lng'])};});
   result.technicalFieldsHidden=await page.locator('[data-native-location-fields]').evaluate(el=>el.hidden);
   assert(result.technicalFieldsHidden);assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1),false);
   result.publicGeoUntouched=await page.evaluate(()=>!Object.keys(document.querySelector('[data-native-report]').nativeReport.model.values).some(k=>/public_lat|public_lng/.test(k)));assert(result.publicGeoUntouched);
   if(process.env.BA_POSTDEPLOY==='1'){
    const verified=await page.evaluate(()=>JSON.stringify(document.querySelector('[data-native-report]').nativeReport.model.values));
    await context.clearPermissions();await page.getByRole('button',{name:'Usa la mia posizione',exact:true}).click();
    await page.waitForFunction(()=>document.querySelector('[data-smart-map-status]').textContent.includes('negato'));
    assert.equal(await page.evaluate(()=>JSON.stringify(document.querySelector('[data-native-report]').nativeReport.model.values)),verified);
    assert(await page.locator('[data-smart-map-panel]').isVisible());result.deniedPreservesPosition='PASS';
    if(!mobile){
     // Seed only synthetic answers; the already acquired/moved location is retained.
     // Normal wizard validation, confirmation and submit handler remain in use.
     await page.evaluate(()=>{
      const {model,config}=document.querySelector('[data-native-report]').nativeReport;
      const answers={'time.mode':'unknown','location.place_type':'road_sidewalk','location.immediate_danger':'no','content.description':'GEO-UX-01: segnalazione sintetica di collaudo della posizione riservata. Nessun evento reale, nessuna richiesta di pubblicazione. Verifica delle coordinate dopo lo spostamento manuale del segnaposto.','reward.status':'none','reporter.public_identity_mode':'anonymous','reporter.first_name':'QA','reporter.last_name':'Geolocalizzazione','reporter.email':'geo-ux-01@example.invalid','reporter.contact_preference':'none','reporter.social_visibility':'declined'};
      for(const [path,value] of Object.entries(answers)){
       model.set(path,value);const input=document.querySelector(`[name="${path}"][data-native-input]`);if(input)input.value=value;
      }
      for(const [path,field] of Object.entries(config.fields)){
       if(path.startsWith('consents.')||!field.required||!model.active(path)||model.effective(path)!==undefined&&model.effective(path)!=='')continue;
       const choices=field.constraints?.enum;
       if(!choices)continue;
       const value=['none','no','unknown','not_contacted'].find(x=>choices.includes(x))||choices[0];
       model.set(path,value);const input=document.querySelector(`[name="${path}"][data-native-input]`);if(input)input.value=value;
      }
      document.querySelector('[name="time.mode"]').dispatchEvent(new Event('input',{bubbles:true}));
     });
     for(let i=0;i<7&&await page.getByRole('button',{name:'Continua',exact:true}).isVisible();i++){
      await page.getByRole('button',{name:'Continua',exact:true}).click();
      const errors=await page.locator('[data-native-error-summary]').isVisible().catch(()=>false);
      if(errors)throw new Error('Synthetic fixture has validation errors before submit');
     }
     await page.locator('[data-beta-confirm]').check();
     result.submissionId=await page.evaluate(()=>document.querySelector('[data-native-report]').nativeReport.model.submissionId);
     const responsePromise=page.waitForResponse(response=>response.request().method()==='POST'&&response.url().includes('/badaround/v1/reports'));
     await page.getByRole('button',{name:'Invia segnalazione',exact:true}).click();
     const response=await responsePromise;result.submissionStatus=response.status();
     const body=await response.json();result.persistedIds={};
     function ids(value){if(!value||typeof value!=='object')return;for(const [key,v]of Object.entries(value)){if(['report_id','event_id'].includes(key))result.persistedIds[key]=v;else if(typeof v==='object')ids(v);}}
     ids(body);assert.equal(result.submissionStatus,201);await page.locator('[data-native-success]').waitFor({state:'visible'});
     result.uiSubmit='PASS';
    }
   }
   await page.screenshot({path:`geo-ux-evidence/${result.device}.png`,fullPage:true});
   result.status='PASS';
  }catch(error){result.status='FAIL';result.error=error.message;result.statusText=await page.locator('[data-smart-map-status]').textContent().catch(()=>null);await page.screenshot({path:`geo-ux-evidence/${result.device}-failure.png`,fullPage:true}).catch(()=>{});}
  finally{await context.close();}
 }
 }finally{fs.writeFileSync('geo-ux-evidence/result.json',JSON.stringify(evidence,null,2));await browser.close();}
 console.log(JSON.stringify(evidence,null,2));assert(evidence.every(x=>x.status==='PASS'),'Real staging GPS/pin QA failed; inspect evidence');
})().catch(error=>{console.error(error.message);process.exitCode=1;});
