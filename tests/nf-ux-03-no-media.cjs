'use strict';
const {test}=require('node:test'),assert=require('node:assert/strict');
const {create,tick}=require('./native-media-dom-harness.cjs');
test('NF-UX-03 no-media differential against certified C.2 for all availability variants and response flows',async()=>{
 for(const availability of ['', 'no','yes','possible','surveillance'])for(const flow of ['success','network-retry','validation','blocked']){
  const results=[];
  for(const runtimeRef of ['0d1db0d7fab7f2facbe6b07ccff002b9e884cade',null]){
   let n=0;const h=create({runtimeRef,fetch(url,request,body){if(url.endsWith('/reports')){n++;if(flow==='network-retry'&&n===1)throw Error('lost');if(flow==='validation')return {ok:false,status:422,json:async()=>({status:'error',error:{code:'invalid_email',field:'reporter.email',retryable:false}})};if(flow==='blocked')return {ok:false,status:409,json:async()=>({status:'error',error:{code:'duplicate_submission',retryable:false}})};}}});
   h.fill();h.set('media.availability',availability);h.goReview();h.submit();h.submit();await tick();if(flow==='network-retry'){h.submit();await tick();}
   const normalized=h.requests.map(r=>({method:r.request.method,headers:r.request.headers,body:{...JSON.parse(r.request.body),submission_id:'<variable UUID>'}}));
   results.push(JSON.parse(JSON.stringify({requests:normalized,step:h.model.step,completed:h.model.completed,blocked:!!h.model.blocked,snapshot:!!h.model.snapshot,error:h.model.lastError?.code || null,focus:h.doc.activeElement.id,uploads:h.uploads.length})));h.close();
  }
  assert.deepEqual(results[1],results[0],availability+' / '+flow);assert.equal(results[1].uploads,0);
 }
});
