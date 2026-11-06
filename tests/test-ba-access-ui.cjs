'use strict';
const {test}=require('node:test'),assert=require('node:assert/strict'),{JSDOM}=require('jsdom'),fs=require('node:fs');
const source=fs.readFileSync(__dirname+'/../wordpress/plugins/badaround-core/identity-dormant/identity-access.js','utf8');
const tick=()=>new Promise(r=>setImmediate(r));
const json=(data,status=200)=>({ok:status>=200&&status<300,status,json:async()=>data});
function setup(fetcher){
 const dom=new JSDOM('<a data-ba-account-link>Accedi</a><div id="entry"></div>',{url:'https://badaround.it/segnala-un-evento/',runScripts:'outside-only'}),w=dom.window;
 w.HTMLDialogElement.prototype.showModal=function(){this.open=true;};w.HTMLDialogElement.prototype.close=function(){this.open=false;};
 w.fetch=fetcher;w.eval(source);return dom;
}
test('wizard authenticates at final confirmation with one six digit code, matching email and no storage',async()=>{
 const calls=[];let authenticated=false;
 const dom=setup(async(url,opts)=>{const payload=JSON.parse(opts.body);calls.push({url,opts,payload});
  if(url.endsWith('/session'))return json({authenticated:false});
  if(url.endsWith('/access/check'))return json({ok:true,matches:authenticated});
  if(url.endsWith('/access/start'))return json({ok:true,state:'code_sent',challenge_id:'a'.repeat(32)});
  if(url.endsWith('/access/verify')){assert.equal(payload.code,'000123');authenticated=true;return json({ok:true,state:'verified'});}
  throw new Error('Unexpected native submission');});
 const w=dom.window;w.Storage.prototype.setItem=function(){throw new Error('No credential storage allowed');};
 const profile={email:'tester@example.test',first_name:'First',last_name:'Tester',privacy_acknowledged:true};
 const result=w.BadAroundAccess.ensureIdentity(profile);await tick();await tick();
 const dialog=w.document.querySelector('dialog');assert(dialog);assert.match(dialog.textContent,/Ti abbiamo inviato/);assert(!dialog.textContent.includes('tester@example.test'));
 assert.equal(calls.find(c=>c.url.endsWith('/access/start')).payload.first_name,'First');
 const code=dialog.querySelector('[name=code]');assert.equal(code.maxLength,6);assert.equal(code.getAttribute('inputmode'),'numeric');assert.equal(code.autocomplete,'one-time-code');
 code.value='000123';dialog.querySelector('form').dispatchEvent(new w.Event('submit',{cancelable:true,bubbles:true}));await tick();await tick();
 assert.equal(await result,true);assert.equal(w.document.querySelector('dialog'),null);assert.equal(w.document.querySelector('[data-ba-account-link]').textContent,'Il mio profilo');
 assert(calls.every(c=>c.opts.credentials==='same-origin'&&c.opts.cache==='no-store'));
 assert.equal(calls.filter(c=>c.url.endsWith('/access/verify')).length,1);dom.window.close();
});
test('first navbar access requests names after code proof and reuses that code to complete session',async()=>{
 const calls=[];let verified=0;
 const dom=setup(async(url,opts)=>{const p=JSON.parse(opts.body);calls.push({url,p});
  if(url.endsWith('/session'))return json({authenticated:false});
  if(url.endsWith('/access/start'))return json({ok:true,state:'code_sent',challenge_id:'b'.repeat(32)});
  if(url.endsWith('/access/verify'))return json({ok:true,state:p.first_name?'verified':'profile_required'});
  throw new Error();});const w=dom.window,host=w.document.querySelector('#entry');
 w.BadAroundAccess.mount(host,{onVerified:()=>{verified++;}});
 const form=host.querySelector('form');form.elements.email.value='new@example.test';form.elements.privacy.checked=true;
 form.dispatchEvent(new w.Event('submit',{cancelable:true}));await tick();await tick();
 form.elements.code.value='123456';form.dispatchEvent(new w.Event('submit',{cancelable:true}));await tick();await tick();
 assert.equal(host.querySelector('[data-access-profile]').hidden,false);assert.equal(form.elements.code.value,'123456');assert.equal(verified,0);
 form.elements.first_name.value='New';form.elements.last_name.value='Tester';form.dispatchEvent(new w.Event('submit',{cancelable:true}));await tick();await tick();
 assert.equal(verified,1);assert.equal(calls.filter(c=>c.url.endsWith('/access/start')).length,1);
 assert.deepEqual(calls.filter(c=>c.url.endsWith('/access/verify')).map(c=>c.p.code),['123456','123456']);dom.window.close();
});
test('failed delivery never claims sent; cancellation and email correction do not dispatch a report',async()=>{
 let startCalls=0;
 const dom=setup(async(url)=>{
  if(url.endsWith('/session'))return json({authenticated:false});
  if(url.endsWith('/access/check'))return json({ok:true,matches:false});
  if(url.endsWith('/access/start')){startCalls++;return json({ok:false},503);}
  throw new Error('No report may be submitted');});const w=dom.window;
 const waiting=w.BadAroundAccess.ensureIdentity({email:'fail@example.test',first_name:'Test',last_name:'Tester',privacy_acknowledged:true});await tick();await tick();
 const dialog=w.document.querySelector('dialog');assert.match(dialog.textContent,/Operazione non confermata/);assert(!dialog.textContent.includes('Ti abbiamo inviato'));
 [...dialog.querySelectorAll('button')].find(b=>b.textContent==='Torna alla segnalazione').click();assert.equal(await waiting,false);assert.equal(startCalls,1);dom.window.close();
});
test('code retry stays on the same challenge and resend has a cooldown',async()=>{
 const calls=[];const dom=setup(async(url,opts)=>{const p=JSON.parse(opts.body);calls.push({url,p});
  if(url.endsWith('/session'))return json({authenticated:false});
  if(url.endsWith('/access/start'))return json({ok:true,state:'code_sent',challenge_id:'c'.repeat(32)});
  if(url.endsWith('/access/verify'))return json({ok:false},403);throw new Error();});const w=dom.window,host=w.document.querySelector('#entry');
 w.BadAroundAccess.mount(host);const f=host.querySelector('form');f.elements.email.value='retry@example.test';f.elements.privacy.checked=true;f.dispatchEvent(new w.Event('submit',{cancelable:true}));await tick();await tick();
 host.querySelector('[data-access-resend]').click();await tick();assert.match(host.textContent,/Attendi un minuto/);assert.equal(calls.filter(c=>c.url.endsWith('/access/start')).length,1);
 f.elements.code.value='111111';f.dispatchEvent(new w.Event('submit',{cancelable:true}));await tick();await tick();assert.equal(f.elements.code.value,'111111');assert.equal(f.elements.email.value,'retry@example.test');
 f.elements.code.value='222222';f.dispatchEvent(new w.Event('submit',{cancelable:true}));await tick();await tick();
 assert.deepEqual(calls.filter(c=>c.url.endsWith('/access/verify')).map(c=>c.p.challenge_id),['c'.repeat(32),'c'.repeat(32)]);dom.window.close();
});
