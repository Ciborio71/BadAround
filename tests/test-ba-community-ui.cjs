'use strict';
const {test}=require('node:test'),assert=require('node:assert/strict');
const {JSDOM}=require('jsdom');
const fs=require('node:fs'),path=require('node:path');
const source=name=>fs.readFileSync(path.join(__dirname,'../wordpress/plugins/badaround-core/identity-dormant',name),'utf8');
const tick=()=>new Promise(resolve=>setImmediate(resolve));
const json=(body,status=200)=>({ok:status>=200&&status<300,status,json:async()=>body});
const portal=`<section data-ba-identity-portal><div data-ba-result></div>
<h2 data-ba-portal-title></h2><p data-ba-portal-intro></p><div data-ba-access-entry></div>
<button data-ba-logout>Esci</button><section data-ba-activity><button data-ba-refresh-activity>Aggiorna</button>
<ul data-ba-activity-list></ul><ul data-ba-reports-list></ul></section></section>`;
test('personal reports are rendered as text, foreign links are discarded and revision sends only intent data',async()=>{
 const dom=new JSDOM(portal,{url:'https://badaround.it/identity-qa/',runScripts:'outside-only'}),w=dom.window,calls=[];
 w.fetch=async(url,options)=>{calls.push({url,options});
  if(url.endsWith('/identity/session'))return json({authenticated:true,csrf:'fixture-csrf'});
  if(url.endsWith('/identity/my-activity'))return json({items:[]});
  if(url.endsWith('/identity/my-reports'))return json({items:[
   {event_id:392,title:'<img src=x onerror=alert(1)>',status:'pending_moderation',url:null,can_propose_revision:true},
   {event_id:393,title:'Titolo pubblico',status:'published',url:'https://example.invalid/foreign',can_propose_revision:false}]});
  if(url.endsWith('/community-revisions'))return json({status:'pending_moderation'});
  throw new Error('Unexpected endpoint');};
 w.BadAroundAccess={mount:()=>{},updateSession:()=>{}};
 w.eval(source('identity-portal.js'));await tick();await tick();
 const list=w.document.querySelector('[data-ba-reports-list]');
 assert.match(list.textContent,/<img/);assert.equal(list.querySelectorAll('img,a').length,0);
 const form=list.querySelector('form');assert(form);
 form.querySelector('[name=description]').value='Descrizione aggiornata di prova';form.querySelector('[name=reason]').value='Correzione';
 form.dispatchEvent(new w.Event('submit',{bubbles:true,cancelable:true}));
 form.dispatchEvent(new w.Event('submit',{bubbles:true,cancelable:true}));await tick();await tick();
 const requests=calls.filter(c=>c.url.endsWith('/community-revisions'));assert.equal(requests.length,1);
 assert.deepEqual(JSON.parse(requests[0].options.body),{event_id:392,description:'Descrizione aggiornata di prova',reason:'Correzione'});
 assert.equal(requests[0].options.credentials,'same-origin');assert.equal(requests[0].options.headers['X-Badaround-CSRF'],'fixture-csrf');
 assert.match(form.textContent,/Proposta ricevuta/);assert.equal(form.querySelector('button').disabled,false);
 dom.window.close();
});
test('comment reply preserves the selected approved parent and the explicit pseudonym without leaking private profile',async()=>{
 const id='b'.repeat(32),dom=new JSDOM(`<section data-ba-comment-form data-event-id="801">
 <button data-ba-reply="${id}">Rispondi</button><div data-ba-comment-status></div><form>
 <textarea name="body"></textarea><input name="parent_id"><p data-ba-reply-status></p><button type="button" data-ba-cancel-reply></button>
 <select name="display_mode"><option value="anonymous">Anonimo</option><option value="pseudonym">Pseudonimo</option></select>
 <label data-ba-alias-field><input name="alias"></label><button type="submit">Invia</button></form></section>`,{url:'https://badaround.it/eventi/fixture/',runScripts:'outside-only'}),w=dom.window,calls=[];
 w.fetch=async(url,options)=>{calls.push({url,options});return url.endsWith('/identity/session')?json({authenticated:true,csrf:'fixture-csrf'}):json({status:'pending_moderation'});};
 w.eval(source('identity-comment-form.js'));
 const form=w.document.querySelector('form'),mode=form.querySelector('select');
 assert.equal(form.querySelector('[name=alias]').disabled,true);
 w.document.querySelector('[data-ba-reply]').click();assert.equal(form.querySelector('[name=parent_id]').value,id);
 mode.value='pseudonym';mode.dispatchEvent(new w.Event('change'));form.querySelector('[name=alias]').value='Sentinella QA';form.querySelector('textarea').value='Risposta di prova';
 form.dispatchEvent(new w.Event('submit',{bubbles:true,cancelable:true}));form.dispatchEvent(new w.Event('submit',{bubbles:true,cancelable:true}));await tick();await tick();
 const requests=calls.filter(c=>c.url.endsWith('/community-comments'));assert.equal(requests.length,1);
 assert.deepEqual(JSON.parse(requests[0].options.body),{event_id:801,body:'Risposta di prova',display_mode:'pseudonym',alias:'Sentinella QA',parent_id:id});
 assert.equal(form.querySelector('[name=parent_id]').value,'');assert.equal(form.querySelector('[name=alias]').disabled,true);
 assert.match(w.document.querySelector('[data-ba-comment-status]').textContent,/Commento ricevuto/);
 dom.window.close();
});
