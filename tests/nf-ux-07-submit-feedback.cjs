'use strict';
const {test}=require('node:test'),assert=require('node:assert/strict');
const {create,tick}=require('./native-media-dom-harness.cjs');
function pendingHarness(){
 let finish;const h=create({fetch:async(url,request,body)=>new Promise(resolve=>{finish=resolve;})});
 h.fill();h.goReview();
 const timers=[],cleared=[],nativeSet=h.w.setTimeout.bind(h.w),nativeClear=h.w.clearTimeout.bind(h.w);
 h.w.setTimeout=(fn,delay,...args)=>{if(delay===6000){timers.push(fn);return 98765;}return nativeSet(fn,delay,...args);};
 h.w.clearTimeout=id=>{cleared.push(id);if(id!==98765)nativeClear(id);};
 return {...h,timers,cleared,feedback:h.doc.querySelector('[data-native-submit-feedback]'),button:h.doc.querySelector('[data-native-submit]'),finish:response=>finish(response)};
}
test('UX07: immediate and delayed submission feedback, duplicate guard, success cleanup',async()=>{
 const h=pendingHarness();assert(h.feedback.hidden);h.submit();
 assert(!h.feedback.hidden);assert.match(h.feedback.textContent,/Invio in corso/);assert(h.button.classList.contains('is-loading'));assert(h.button.disabled);assert.equal(h.doc.querySelector('form').getAttribute('aria-busy'),'true');
 assert(!h.feedback.closest('form'));assert.equal(h.feedback.getAttribute('role'),'status');
 h.submit();h.button.click();assert.equal(h.requests.length,1);assert.equal(h.timers.length,1);
 h.timers[0]();assert.match(h.feedback.textContent,/Stiamo elaborando/);
 const body=JSON.parse(h.requests[0].request.body);
 h.finish({ok:true,status:201,json:async()=>({status:'success',schema_version:body.schema_version,submission_id:body.submission_id,next_state:'moderation_pending'})});await tick();await tick();
 assert(h.feedback.hidden);assert(!h.button.classList.contains('is-loading'));assert(h.cleared.includes(98765));assert.equal(h.doc.querySelector('form').getAttribute('aria-busy'),'false');assert(h.model.completed);
 h.timers[0]();assert(h.feedback.hidden);assert.equal(h.doc.activeElement,h.doc.querySelector('[data-native-success] h2'));h.close();
});
test('UX07: failure clears progress and retry preserves the same submission snapshot',async()=>{
 const h=pendingHarness();h.submit();const body=h.requests[0].request.body;
 h.finish({ok:false,status:503,json:async()=>({error:{code:'service_unavailable',retryable:true}})});await tick();await tick();
 assert(h.feedback.hidden);assert(!h.button.classList.contains('is-loading'));assert(!h.button.disabled);assert.equal(h.button.textContent,'Riprova invio');assert(!h.doc.querySelector('[data-native-errors]').hidden);
 h.submit();assert(!h.feedback.hidden);assert.match(h.feedback.textContent,/Invio in corso/);assert.equal(h.requests.length,2);assert.equal(h.requests[1].request.body,body);h.close();assert(h.feedback.hidden);assert(h.cleared.includes(98765));
});
