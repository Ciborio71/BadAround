'use strict';
const {test}=require('node:test'),assert=require('node:assert/strict');
const send=require('../wordpress/themes/badaround-child/assets/js/native-report/api.js');
const origin='https://badaround.it',payload={submission_id:'fixture-submission',schema_version:'badaround-report/v2',media:{items:[]}};
const config={endpoint:origin+'/wp-json/badaround-identity-qa/v1/reports',identityQa:true,markerHeader:'X-Badaround-Intake',markerValue:'badaround-report/v1'};
const response=(status,body)=>({status,ok:status>=200&&status<300,json:async()=>body});
test('QA requires an authenticated same-origin session and never submits anonymously',async()=>{
 const calls=[];const result=await send(config,payload,async(u,o)=>{calls.push({u,o});return response(200,{authenticated:false});},origin);
 assert.equal(result.error.code,'identity_required');assert.equal(calls.length,1);assert.equal(calls[0].o.credentials,'same-origin');
});
test('QA success requires confirmed ownership and preserves the payload and media capability',async()=>{
 const calls=[];const p={...payload,media:{items:[{fixture:true}]}};
 const result=await send(config,p,async(u,o)=>{calls.push({u,o});return calls.length===1?response(200,{authenticated:true}):response(201,{state:'owner_confirmed'});},origin,'fixture-capability');
 assert.equal(result.ok,true);assert.equal(calls.length,2);assert.equal(calls[1].o.credentials,'same-origin');assert.equal(calls[1].o.headers['X-BadAround-Media-Capability'],'fixture-capability');assert.deepEqual(JSON.parse(calls[1].o.body),p);
});
test('QA pending ownership is not shown as completed and a retry reuses the same submission',async()=>{
 const bodies=[];let count=0;const fetcher=async(u,o)=>{count++;if(count%2)return response(200,{authenticated:true});bodies.push(o.body);return response(202,{state:'pending'});};
 for(let i=0;i<2;i++){const result=await send(config,payload,fetcher,origin);assert.equal(result.ok,false);assert.equal(result.error.retryable,true);assert.equal(result.error.code,'identity_pending');}
 assert.equal(bodies[0],bodies[1]);
});
test('QA rejects alternate routes and cross-origin endpoints before any request',async()=>{
 for(const endpoint of ['https://example.test/wp-json/badaround-identity-qa/v1/reports',origin+'/wp-json/badaround/v1/reports',config.endpoint+'?fallback=1']){
 let count=0;const result=await send({...config,endpoint},payload,async()=>{count++;},origin);assert.equal(result.ok,false);assert.equal(count,0);
 }
});
test('QA rejects a native success response and never falls back after a failure',async()=>{
 const calls=[];const result=await send(config,payload,async(u,o)=>{calls.push(u);return calls.length===1?response(200,{authenticated:true}):response(201,{status:'success',submission_id:payload.submission_id,schema_version:payload.schema_version,next_state:'moderation_pending'});},origin);
 assert.equal(result.ok,false);assert.equal(calls.length,2);assert.equal(calls[1],config.endpoint);
});
test('Baseline native requests keep anonymous transport and native receipt validation',async()=>{
 const normal={...config,identityQa:false,endpoint:origin+'/wp-json/badaround/v1/reports',schemaVersion:payload.schema_version};
 let options;const result=await send(normal,payload,async(u,o)=>{options=o;return response(201,{status:'success',submission_id:payload.submission_id,schema_version:payload.schema_version,next_state:'moderation_pending'});},origin);
 assert.equal(result.ok,true);assert.equal(options.credentials,'omit');
});
