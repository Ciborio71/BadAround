'use strict';
const {test}=require('node:test'),assert=require('node:assert/strict');
const {Media,MediaTransport}=require('../wordpress/themes/badaround-child/assets/js/native-report/media.js');
const id=()=>crypto.randomUUID(),tick=()=>new Promise(r=>setImmediate(r));
test('later binary/HTTP rejection cannot erase prior acceptance uncertainty',async()=>{
 let uploads=0;const media=new Media({},id(),{urls:{createObjectURL:()=> 'blob:test',revokeObjectURL:()=>{}},transport:{create:async()=>({media_session_id:id(),capability:'A'.repeat(43),upload_expires_at:2000000000,commit_expires_at:2000007200,limits:{max_items:5,max_bytes_per_item:5242880},supported_codecs:['png']}),upload:async()=>{throw Object.assign(new Error('Synthetic'),{code:++uploads===1?'network_error':'media_file_too_large'});}}});
 media.select([new File([new Uint8Array(70)],'synthetic.png',{type:'image/png'})]);await tick();media.retry(media.items[0].id);await tick();await media.remove(media.items[0].id);
 assert.equal(uploads,3);assert.equal(media.count,1);assert.equal(media.items[0].state,'failed');assert.throws(()=>media.freeze());media.dispose();
});
test('XHR transport sets only media headers and multipart fields; progress is measured, never simulated',async()=>{
 let xhr,form;const t=new MediaTransport({endpoint:'/wp-json/badaround/v1/report-media-sessions'},{origin:'https://staging.badaround.it',fetch:async()=>{},form:()=>{form=new FormData();return form;},xhr:()=>{xhr={upload:{},headers:{},open(m,u){this.method=m;this.url=u;},setRequestHeader(k,v){this.headers[k]=v;},send(body){assert.equal(body,form);}};return xhr;}});
 const session=id(),key=id(),file=new File([new Uint8Array(70)],'synthetic.png',{type:'image/png'}),progress=[];
 const result=t.upload(session,'A'.repeat(43),key,file,p=>progress.push(p));assert.equal(form.get('client_upload_id'),key);assert.equal(form.get('file'),file);assert.equal(Array.from(form.keys()).length,2);assert.equal(xhr.headers['X-BadAround-Media'],'badaround-report-media/v1');assert.equal(xhr.withCredentials,false);assert(!xhr.headers['Content-Type']);assert(!xhr.url.includes('A'.repeat(43)));assert.equal(progress.length,0);
 xhr.upload.onprogress({lengthComputable:false});xhr.upload.onprogress({lengthComputable:true,loaded:2,total:5});assert.deepEqual(progress,[null,40]);xhr.status=200;xhr.responseURL=xhr.url;xhr.responseText=JSON.stringify({status:'success',duplicate:true});xhr.onload();assert((await result).duplicate);
});
test('malformed session replies are rejected and never cause a multipart upload',async()=>{
 for(const changed of [{media_session_id:'guessed'},{capability:'short'},{limits:{max_items:5,max_bytes_per_item:2097152}},{supported_codecs:['heic']},{commit_expires_at:1}]){
  let uploads=0;const media=new Media({},id(),{urls:{createObjectURL:()=> 'blob:test',revokeObjectURL:()=>{}},transport:{create:async()=>({...{media_session_id:id(),capability:'A'.repeat(43),upload_expires_at:2000000000,commit_expires_at:2000007200,limits:{max_items:5,max_bytes_per_item:5242880},supported_codecs:['png']},...changed}),upload:async()=>{uploads++;}}});media.select([new File([new Uint8Array(70)],'synthetic.png',{type:'image/png'})]);await tick();assert.equal(media.items[0].error,'invalid_response');assert.equal(uploads,0);assert.equal(media.reportCapability(),null);media.dispose();
 }
});
test('an unexpected bound upload response is not a new accepted file',async()=>{
 const media=new Media({},id(),{urls:{createObjectURL:()=> 'blob:test',revokeObjectURL:()=>{}},transport:{create:async()=>({media_session_id:id(),capability:'A'.repeat(43),upload_expires_at:2000000000,commit_expires_at:2000007200,limits:{max_items:5,max_bytes_per_item:5242880},supported_codecs:['png']}),upload:async()=>({state:'bound_pending_review',descriptor:{media_id:id(),file_size:70,mime_type:'image/png',extension:'png'}})}});
 media.select([new File([new Uint8Array(70)],'synthetic.png',{type:'image/png'})]);await tick();assert.equal(media.items[0].error,'media_descriptor_mismatch');assert.equal(media.descriptors().length,0);media.dispose();
});
