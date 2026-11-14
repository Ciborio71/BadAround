'use strict';
const {test}=require('node:test'),assert=require('node:assert/strict'),{JSDOM}=require('jsdom'),fs=require('node:fs');
const source=fs.readFileSync(__dirname+'/../wordpress/plugins/badaround-core/identity-dormant/identity-access.js','utf8');
const tick=()=>new Promise(r=>setImmediate(r));
const json=(data,status=200)=>({ok:status>=200&&status<300,status,json:async()=>data});
function setup(fetcher,label='Accedi'){
 const dom=new JSDOM('<a data-ba-account-link>Accedi</a><div id="entry"></div>',{url:'https://badaround.it/segnala-un-evento/',runScripts:'outside-only'}),w=dom.window;
 w.HTMLDialogElement.prototype.showModal=function(){this.open=true;};w.HTMLDialogElement.prototype.close=function(){this.open=false;};
 w.document.querySelector('[data-ba-account-link]').textContent=label;w.fetch=fetcher;w.eval(source);return dom;
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
 const dialog=w.document.querySelector('dialog');assert.match(dialog.textContent,/Invio del codice non confermato/);assert(!dialog.textContent.includes('Ti abbiamo inviato'));assert.equal(dialog.querySelector('[data-access-submit]').disabled,false);assert.equal(dialog.querySelector('[data-access-submit]').classList.contains('is-loading'),false);
 [...dialog.querySelectorAll('button')].find(b=>b.textContent==='Torna alla segnalazione').click();assert.equal(await waiting,false);assert.equal(startCalls,1);dom.window.close();
});
test('code retry stays on the same challenge and resend has a cooldown',async()=>{
 const calls=[];const dom=setup(async(url,opts)=>{const p=JSON.parse(opts.body);calls.push({url,p});
  if(url.endsWith('/session'))return json({authenticated:false});
  if(url.endsWith('/access/start'))return json({ok:true,state:'code_sent',challenge_id:'c'.repeat(32)});
  if(url.endsWith('/access/verify'))return json({ok:false},403);throw new Error();});const w=dom.window,host=w.document.querySelector('#entry');
 w.BadAroundAccess.mount(host);const f=host.querySelector('form');f.elements.email.value='retry@example.test';f.elements.privacy.checked=true;f.dispatchEvent(new w.Event('submit',{cancelable:true}));await tick();await tick();
 assert.equal(host.querySelector('[data-access-resend]').disabled,true);assert.match(host.querySelector('[data-access-resend]').textContent,/Nuovo codice tra/);host.querySelector('[data-access-resend]').click();await tick();assert.equal(calls.filter(c=>c.url.endsWith('/access/start')).length,1);
 f.elements.code.value='111111';f.dispatchEvent(new w.Event('submit',{cancelable:true}));await tick();await tick();assert.equal(f.elements.code.value,'111111');assert.equal(f.elements.email.value,'retry@example.test');
 f.elements.code.value='222222';f.dispatchEvent(new w.Event('submit',{cancelable:true}));await tick();await tick();
 assert.deepEqual(calls.filter(c=>c.url.endsWith('/access/verify')).map(c=>c.p.challenge_id),['c'.repeat(32),'c'.repeat(32)]);dom.window.close();
});


test('slow send and verify show progress, preserve one primary action and prevent duplicate verification',async()=>{
 let deliver,verify,authenticated=false;const calls=[],phases=[];
 const dom=setup(async(url,opts)=>{
  calls.push(url);
  if(url.endsWith('/session'))return json({authenticated:false});
  if(url.endsWith('/access/check'))return json({matches:authenticated});
  if(url.endsWith('/access/start'))return new Promise(resolve=>{deliver=resolve;});
  if(url.endsWith('/access/verify'))return new Promise(resolve=>{verify=data=>{authenticated=true;resolve(data);};});
  throw new Error('unexpected request');
 });const w=dom.window;
 try{
  const waiting=w.BadAroundAccess.ensureIdentity({email:'tester@example.test',first_name:'Test',last_name:'Tester',privacy_acknowledged:true},{onPhase:p=>phases.push(p)});
  await tick();await tick();const dialog=w.document.querySelector('dialog'),button=dialog.querySelector('[data-access-submit]'),status=dialog.querySelector('[data-access-result]'),codeBox=dialog.querySelector('[data-access-code]');
  assert.match(dialog.querySelector('.ba-access-intro').textContent,/ultimo passaggio/);assert(button.classList.contains('is-loading'));assert(button.disabled);assert.equal(button.getAttribute('aria-busy'),'true');assert.match(status.textContent,/Invio del codice/);assert(!status.textContent.includes('Ti abbiamo inviato'));assert(codeBox.hidden);
  deliver(json({state:'code_sent',challenge_id:'d'.repeat(32)}));await tick();await tick();
  assert(!button.classList.contains('is-loading'));assert(!button.disabled);assert.equal(button.textContent,'Conferma e invia');assert.match(status.textContent,/Ti abbiamo inviato/);assert.equal(status.nextElementSibling,codeBox);
  const code=dialog.querySelector('[name=code]');assert(w.document.getElementById(code.getAttribute('aria-describedby')));code.value='123456';
  const submit=()=>dialog.querySelector('form').dispatchEvent(new w.Event('submit',{bubbles:true,cancelable:true}));submit();submit();
  assert(button.classList.contains('is-loading'));assert.match(status.textContent,/Verifica del codice/);assert.equal(calls.filter(u=>u.endsWith('/access/verify')).length,1);
  verify(json({state:'verified'}));await tick();await tick();assert.equal(await waiting,true);assert.deepEqual(phases,['checking','confirming','verified']);assert.equal(w.document.querySelector('dialog'),null);
 }finally{dom.window.close();}
});


test('resend cooldown expires, shows its own spinner and clears the previous code after acknowledged delivery',async()=>{
 let deliveries=0,deliver;const dom=setup(async url=>{
  if(url.endsWith('/session'))return json({authenticated:false});
  if(url.endsWith('/access/start')){deliveries++;if(deliveries===1)return json({state:'code_sent',challenge_id:'a'.repeat(32)});return new Promise(resolve=>{deliver=resolve;});}
  throw new Error('unexpected');
 });const w=dom.window;let now=w.Date.now();w.Date.now=()=>now;
 try{
  const host=w.document.querySelector('#entry');w.BadAroundAccess.mount(host);const form=host.querySelector('form');form.elements.email.value='test@example.test';form.elements.privacy.checked=true;
  form.dispatchEvent(new w.Event('submit',{cancelable:true}));await tick();await tick();const resend=host.querySelector('[data-access-resend]');assert(resend.disabled);
  now+=61000;await new Promise(resolve=>w.setTimeout(resolve,1050));assert.equal(resend.disabled,false);
  form.elements.code.value='123456';resend.click();assert(resend.classList.contains('is-loading'));assert.equal(resend.textContent,'Invio del nuovo codice…');assert.equal(deliveries,2);
  deliver(json({state:'code_sent',challenge_id:'b'.repeat(32)}));await tick();await tick();assert(!resend.classList.contains('is-loading'));assert(resend.disabled);assert.match(resend.textContent,/Nuovo codice tra/);assert.equal(form.elements.code.value,'');
 }finally{dom.window.close();}
});

test('server-rendered profile stays visible during delayed session check and expired sessions reconcile',async()=>{
 let resolve;const dom=setup(()=>new Promise(r=>{resolve=r;}),'Il mio profilo'),w=dom.window;
 assert.equal(w.document.querySelector('a').textContent,'Il mio profilo');await tick();
 assert.equal(w.document.querySelector('a').textContent,'Il mio profilo');
 resolve(json({authenticated:false}));await tick();
 assert.equal(w.document.querySelector('a').textContent,'Accedi');
 assert.equal(w.document.querySelector('a').dataset.baAccountState,'anonymous');dom.window.close();
});
test('delayed initial anonymous response cannot overwrite newer verified authentication',async()=>{
 let resolve;const dom=setup(()=>new Promise(r=>{resolve=r;})),w=dom.window;
 w.BadAroundAccess.updateSession(true);resolve(json({authenticated:false}));await tick();
 assert.equal(w.document.querySelector('a').textContent,'Il mio profilo');
 assert.equal(w.document.querySelector('a').dataset.baAccountState,'authenticated');dom.window.close();
});
