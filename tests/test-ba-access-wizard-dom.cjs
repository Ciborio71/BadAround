'use strict';
const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {execFileSync} = require('node:child_process');
const {JSDOM} = require('jsdom');
const html = execFileSync(process.env.BA_PHP || 'php',[path.join(__dirname,'native-report-f16.php'),'--html'],{encoding:'utf8'});
function create(responseFactory) {
  const dom = new JSDOM(html,{url:'https://staging.badaround.it/segnala-un-evento/?native_report=1',runScripts:'outside-only'});
  const {window} = dom, requests = [];
  window.AbortController = AbortController;window.scrollTo=()=>{};
  window.fetch = async (url,options) => { requests.push({url,options});return responseFactory(JSON.parse(options.body),requests.length); };
  const configNode=window.document.querySelector('[data-native-config]');const cfg=JSON.parse(configNode.textContent);cfg.identityQa=true;cfg.media={endpoint:'https://staging.badaround.it/wp-json/badaround/v1/report-media-sessions'};configNode.textContent=JSON.stringify(cfg);
  let freezes=0;
  for (const file of ['model','api','errors']) window.eval(fs.readFileSync(path.join(__dirname,'../wordpress/themes/badaround-child/assets/js/native-report',file+'.js'),'utf8'));
  window.BadAroundNative.mountMedia=()=>({media:{count:0,items:[],errors:()=>[],freeze:()=>{freezes++;return [];},reportCapability:()=>null},render:()=>{},dispose:()=>{}});
  window.eval(fs.readFileSync(path.join(__dirname,'../wordpress/themes/badaround-child/assets/js/native-report/wizard.js'),'utf8'));
  window.BadAroundNative.send=async(config,payload)=>{requests.push(payload);return {ok:true};};
  const doc=window.document, root=doc.querySelector('[data-native-report]');
  const set=(key,value)=>{
    const input=doc.querySelector(`[name="${key}"]`);
    if(input.type==='checkbox') input.checked=value;else input.value=value;
    input.dispatchEvent(new window.Event('input',{bubbles:true}));
  };
  const click=key=>doc.querySelector(`[data-native-${key}]`).click();
  const submit=()=>doc.querySelector('form').dispatchEvent(new window.Event('submit',{bubbles:true,cancelable:true}));
  return {dom,window,doc,root,requests,set,click,submit,model:root.nativeReport.model,freezes:()=>freezes};
}
const completed=payload=>({ok:true,status:201,json:async()=>({status:'success',submission_id:payload.submission_id,schema_version:payload.schema_version,next_state:'moderation_pending',duplicate:false})});
function populate(h) {
  const data={'event.category':'hazard','event.subtype':'hazard_road_obstruction','reporter.relationship':'territory_observer',
    'location.region':'Lazio','location.province':'Roma','location.municipality':'Pomezia','location.locality':'Torvaianica',
    'location.exact_address':'Via Test 1, Pomezia','location.place_type':'road_sidewalk','location.immediate_danger':'no',
    'time.mode':'exact','time.date':'2026-10-01','time.knowledge':'unknown','content.description':'Segnalazione sintetica F1.6.',
    'damage.status':'no','authority.status':'no','media.availability':'no','reporter.first_name':'QA','reporter.last_name':'Synthetic',
    'reporter.email':'qa@example.invalid','reward.status':'none','reporter.public_identity_mode':'anonymous','reporter.contact_preference':'none','consents.truthfulness':true,'consents.media_rights':true,
    'consents.publication_rules':true,'consents.terms':true,'consents.privacy':true};
  Object.entries(data).forEach(([key,value])=>h.set(key,value));
}
const tick=()=>new Promise(resolve=>setImmediate(resolve));

test('final access cancellation keeps wizard values and media editable; authentication precedes snapshot and submission',async()=>{
 const h=create(completed);populate(h);for(let i=0;i<5;i++)h.click('next');assert.equal(h.model.step,5);
 const original=JSON.stringify(h.model.values),id=h.model.submissionId;let complete,checks=0;
 h.window.BadAroundAccess={ensureIdentity:()=>{checks++;return new Promise(r=>{complete=r;});}};
 h.submit();h.submit();await tick();assert.equal(checks,1);assert.equal(h.requests.length,0);assert.equal(h.freezes(),0);assert.equal(h.model.snapshot,null);
 complete(false);await tick();assert.equal(h.model.busy,false);assert.equal(h.model.snapshot,null);assert.equal(JSON.stringify(h.model.values),original);assert.equal(h.model.submissionId,id);
 h.window.BadAroundAccess.ensureIdentity=async profile=>{assert.equal(profile.email,'qa@example.invalid');assert.equal(profile.privacy_acknowledged,true);return true;};
 h.submit();await tick();await tick();assert.equal(h.freezes(),1);assert.equal(h.requests.length,1);assert.equal(h.model.completed,true);h.dom.window.close();
});
