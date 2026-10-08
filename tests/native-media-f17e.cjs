'use strict';
const {test}=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const {Media,MediaTransport}=require('../wordpress/themes/badaround-child/assets/js/native-report/media.js');
const {create,tick,cap}=require('./native-media-dom-harness.cjs');
const id=()=>crypto.randomUUID(),file=name=>new File([new Uint8Array(70)],name || 'synthetic.png',{type:'image/png'});
const accepted=(f,mid=id())=>({state:'accepted_quarantined',descriptor:{media_id:mid,mime_type:'image/png',file_size:f.size,extension:'png'}});
const failure=code=>Object.assign(new Error('Synthetic failure'),{code});
function controller(overrides={}) {
 const calls=[],revoked=[],actions=[],session=id();
 const transport={create:async(s,n)=>{calls.push(['create',s,n]);return {media_session_id:session,capability:cap,upload_expires_at:2000000000,commit_expires_at:2000007200,limits:{max_items:5,max_bytes_per_item:5242880},supported_codecs:['png']};},upload:async(s,c,k,f)=>{calls.push(['upload',s,c,k]);return accepted(f);},remove:async(s,c,m)=>{calls.push(['remove',s,c,m]);return {state:'removed',media_id:m};},status:async()=>({state:'open'}),...overrides};
 const media=new Media({},id(),{transport,changed:a=>actions.push(a),urls:{createObjectURL:()=> 'blob:'+id(),revokeObjectURL:u=>revoked.push(u)}});
 return {media,transport,calls,revoked,actions};
}
test('removal retains the last canonical descriptor until server confirmation',async()=>{
 const h=controller();h.media.select([file()]);await tick();const before=h.media.descriptors();let done;
 h.transport.remove=async(s,c,m)=>new Promise(r=>{done=()=>r({state:'removed',media_id:m});});
 const pending=h.media.remove(h.media.items[0].id);assert.equal(h.media.items[0].state,'removing');assert.deepEqual(h.media.descriptors(),before);assert.throws(()=>h.media.freeze());done();await pending;assert.deepEqual(h.media.descriptors(),[]);h.media.dispose();
});
test('same media UUID with conflicting descriptor is not silently deduplicated',async()=>{
 const mid=id(),h=controller({upload:async(s,c,k,f)=>accepted(f,mid)});
 h.media.select([file('a.png'),new File([new Uint8Array(71)],'b.png',{type:'image/png'})]);await tick();
 assert.equal(h.media.items[1].state,'failed');assert.equal(h.media.items[1].error,'media_descriptor_mismatch');assert.throws(()=>h.media.freeze());h.media.dispose();
});
test('late upload cannot trigger a DELETE after controller teardown',async()=>{
 let finish,attempt=0,deletes=0;const h=controller({upload:async(s,c,k,f)=>{if(++attempt===1)throw failure('network_error');return new Promise(r=>{finish=()=>r(accepted(f));});},remove:async()=>{deletes++;throw failure('synthetic');}});
 h.media.select([file()]);await tick();const pending=h.media.remove(h.media.items[0].id);await tick();h.media.dispose();finish();await pending;
 assert.equal(deletes,0);assert.equal(h.revoked.length,1);assert.equal(h.media.reportCapability(),null);assert.equal(h.media.count,0);
});
test('teardown suppresses late report completion and detaches wizard listeners',async()=>{
 let finish;const h=create({fetch(url,request,body){if(url.endsWith('/reports'))return new Promise(r=>{finish=()=>r({ok:true,status:200,json:async()=>({status:'success',schema_version:body.schema_version,submission_id:body.submission_id,next_state:'moderation_pending'})});});}});
 h.fill();h.set('media.availability','yes');h.select();await tick();h.goReview();h.submit();await tick();const snapshot=h.model.snapshot;
 h.root.nativeReport.teardown();h.doc.querySelector('[data-native-next]').dispatchEvent(new h.w.Event('click'));assert.equal(h.model.step,6);
 finish();await tick();assert.equal(h.model.completed,false);assert.equal(h.model.snapshot,snapshot);assert(h.doc.querySelector('[data-native-success]').hidden);h.close();
});
test('final review separates unresolved selections from accepted images',async()=>{
 const h=create({upload(xhr){queueMicrotask(()=>xhr.finish('media_service_unavailable'));}});h.fill();h.set('media.availability','yes');h.select();await tick();h.goReview();
 assert.equal(h.doc.querySelectorAll('.ba-native-review-media img').length,0);assert(h.doc.querySelector('[data-native-review]').textContent.includes('da completare o rimuovere'));h.close();
});
test('serialized queue prevents out-of-order completion; delayed retry keeps selection ordering',async()=>{
 let releases=[],attempts=new Map();const mids=new Map();const h=controller({upload:async(s,c,k,f)=>{
  const n=(attempts.get(k)||0)+1;attempts.set(k,n);if(f.name==='first.png'&&n===1)throw failure('network_error');
  return new Promise(resolve=>releases.push({key:k,name:f.name,finish:()=>{const mid=id();mids.set(f.name,mid);resolve(accepted(f,mid));}}));
 }});
 h.media.select([file('first.png'),file('second.png'),file('third.png')]);await tick();assert.equal(releases.length,1);assert.equal(releases[0].name,'second.png');
 releases[0].finish();await tick();assert.equal(releases.length,2);releases[1].finish();await tick();const firstKey=h.media.items[0].id;
 h.media.retry(firstKey);h.media.retry(firstKey);await tick();assert.equal(releases.length,3);releases[2].finish();await tick();
 assert.deepEqual(h.media.descriptors().map(d=>d.media_id),['first.png','second.png','third.png'].map(n=>mids.get(n)));
 await h.media.remove(h.media.items[1].id);h.media.select([file('fourth.png')]);await tick();releases[3].finish();await tick();
 assert.deepEqual(h.media.descriptors().map(d=>d.media_id),['first.png','third.png','fourth.png'].map(n=>mids.get(n)));assert.equal(attempts.get(firstKey),2);h.media.dispose();
});
test('concurrent selections share one in-flight create and stable nonce after lost create response',async()=>{
 let done,first=true;const h=controller();const original=h.transport.create;
 h.transport.create=async(...args)=>{const response=await original(...args);if(first){first=false;return new Promise((_,reject)=>{done=()=>reject(failure('network_error'));});}return response;};
 h.media.select([file('first.png')]);h.media.select([file('second.png')]);await tick();assert.equal(h.calls.filter(c=>c[0]==='create').length,1);done();await tick();
 assert.equal(h.media.items[0].state,'failed');assert.equal(h.media.items[1].state,'accepted');h.media.retry(h.media.items[0].id);await tick();
 const creates=h.calls.filter(c=>c[0]==='create');assert.equal(creates.length,2);assert.equal(creates[0][1],creates[1][1]);assert.equal(creates[0][2],creates[1][2]);assert.equal(Buffer.from(creates[0][2],'base64url').length,32);h.media.dispose();
});
test('double remove sends one DELETE; failed remove retains descriptor, repeat then frees one slot',async()=>{
 const h=controller();h.media.select(Array.from({length:4},(_,i)=>file(i+'.png')));await tick();const errors=h.media.select([file('fifth.png'),file('sixth.png')]);await tick();assert.equal(errors.length,1);assert.equal(h.media.count,5);
 let done,deletes=0;h.transport.remove=async(s,c,m)=>{deletes++;return new Promise(r=>{done=()=>r({state:'removed',media_id:m});});};
 const key=h.media.items[2].id,pending=h.media.remove(key);await h.media.remove(key);assert.equal(deletes,1);done();await pending;
 h.media.select([file('replacement.png')]);await tick();assert.equal(h.media.count,5);assert.notEqual(h.media.items.at(-1).id,key);assert.equal(h.revoked.length,1);h.media.dispose();
});
test('unknown MIME/uppercase extension/hostile filename stay UX-only; exact limit and +1 distinct',async()=>{
 const h=controller();h.media.select([new File([new Uint8Array(5242880)],'<img onerror=secret>.PNG',{type:''})]);await tick();assert.equal(h.media.items[0].state,'accepted');
 assert.equal(h.media.select([new File([new Uint8Array(5242881)],'too-large.png',{type:''})])[0].code,'media_file_too_large');
 assert.equal(h.media.select([new File([],'zero.png')])[0].code,'media_image_invalid');assert.equal(h.media.select([file('no-extension')])[0].code,'media_type_unsupported');h.media.dispose();
});
test('transport aborts XHR once, rejects teardown, discards captured late response/progress',async()=>{
 let xhr,aborts=0;const seen=[];const t=new MediaTransport({endpoint:'/sessions'},{origin:'https://staging.badaround.it',fetch:async()=>{},xhr:()=>xhr={upload:{},open(){},setRequestHeader(){},send(){},abort(){aborts++;}},form:()=>new FormData()});
 const pending=t.upload(id(),cap,id(),file(),p=>seen.push(p));const failureCheck=assert.rejects(pending,e=>e.code==='network_error'),load=xhr.onload,progress=xhr.upload.onprogress;
 t.dispose();t.dispose();xhr.responseText=JSON.stringify({status:'success'});xhr.status=201;load();progress({lengthComputable:true,loaded:1,total:1});await failureCheck;assert.equal(aborts,1);assert.deepEqual(seen,[]);await assert.rejects(t.upload(id(),cap,id(),file(),()=>{}));
});
for(const response of [null,[],42,'not-an-envelope',{}])test('malformed XHR envelope settles safely: '+JSON.stringify(response),async()=>{
 let xhr;const t=new MediaTransport({endpoint:'/sessions'},{origin:'https://staging.badaround.it',fetch:async()=>{},xhr:()=>xhr={upload:{},open(){},setRequestHeader(){},send(){}},form:()=>new FormData()});
 const p=t.upload(id(),cap,id(),file(),()=>{});const rejected=assert.rejects(p,e=>e.code==='invalid_response');xhr.status=201;xhr.responseText=JSON.stringify(response);xhr.onload();await rejected;t.dispose();
});
test('JSON cancellation aborts fetch signal and late create never restores ownership',async()=>{
 let finish,signal;const t=new MediaTransport({endpoint:'/sessions'},{origin:'https://staging.badaround.it',fetch:async(u,o)=>{signal=o.signal;return new Promise(r=>{finish=()=>r({ok:true,json:async()=>({status:'success'})});});}});
 const p=t.create(id(),'B'.repeat(43)),rejected=assert.rejects(p,e=>e.code==='network_error');t.dispose();assert(signal.aborted);finish();await rejected;
 const h=controller();let resolve;h.transport.create=async()=>new Promise(r=>{resolve=r;});h.media.select([file()]);await tick();h.media.dispose();resolve({});await tick();assert.equal(h.media.count,0);assert.equal(h.media.reportCapability(),null);assert.equal(h.calls.length,0);assert.throws(()=>h.media.freeze());
});
test('late DELETE completion never double-revokes URLs or re-renders detached state',async()=>{
 let done;const h=controller();h.media.select([file()]);await tick();h.transport.remove=async(s,c,m)=>new Promise(r=>{done=()=>r({state:'removed',media_id:m});});const pending=h.media.remove(h.media.items[0].id);h.media.dispose();const actions=h.actions.length;done();await pending;assert.equal(h.actions.length,actions);assert.equal(h.revoked.length,1);assert.equal(h.media.count,0);
});
test('navigation during upload retains live queue, preview/focus and request identity',async()=>{
 const h=create({upload(){}});h.fill();h.set('media.availability','yes');for(let i=0;i<4;i++)h.click('next');h.select();await tick();const preview=h.doc.querySelector('[data-media-list] img').src,key=h.uploads[0].key;
 for(const action of ['next','back','back','next','back','next','next','back'])h.click(action);
 assert.equal(h.model.step,4);assert.equal(h.uploads.length,1);assert.equal(h.doc.querySelector('[data-media-list] img').src,preview);assert.equal(h.doc.activeElement.tagName,'H2');
 h.uploads[0].finish();await tick();assert.equal(h.uploads[0].key,key);assert.equal(h.doc.querySelectorAll('[data-media-list] li').length,1);assert.equal(h.doc.querySelector('[data-media-state]').textContent,'Accettata e riservata');h.close();
});
test('100 percent upload is awaiting acceptance, not a canonical accepted image',async()=>{
 const h=create({upload(){}});h.fill();h.set('media.availability','yes');h.select();await tick();h.uploads[0].upload.onprogress({lengthComputable:true,loaded:100,total:100});
 assert.equal(h.doc.querySelector('progress').value,100);assert(h.doc.querySelector('[data-media-state]').textContent.includes('Caricamento in corso'));assert(h.doc.querySelector('[data-media-count]').textContent.startsWith('0 immagini accettate'));h.goReview();h.submit();assert.equal(h.model.snapshot,null);h.uploads[0].finish();await tick();h.close();
});
test('rerender/reinitialization does not create listeners, session, rows or a new submission',async()=>{
 const h=create();h.fill();h.set('media.availability','yes');h.select();await tick();const model=h.model,uuid=model.submissionId;
 const wizard=fs.readFileSync(path.join(__dirname,'../wordpress/themes/badaround-child/assets/js/native-report/wizard.js'),'utf8');h.w.eval(wizard);h.w.eval(wizard);
 assert.equal(h.root.nativeReport.model,model);assert.equal(model.submissionId,uuid);for(let i=0;i<4;i++){h.click('next');assert.equal(model.step,i+1);}h.click('back');h.click('next');
 assert.equal(h.uploads.length,1);assert.equal(h.requests.filter(r=>r.url.endsWith('/report-media-sessions')).length,1);assert.equal(h.doc.querySelectorAll('[data-media-list] li').length,1);h.close();
});
test('double Continue at review cannot leave seven-step bounds; frozen handlers cannot navigate',async()=>{
 const h=create({fetch(url){if(url.endsWith('/reports'))throw Error('uncertain');}});h.fill();h.set('media.availability','yes');h.select();await tick();h.goReview();h.click('next');h.click('next');assert.equal(h.model.step,6);h.submit();await tick();const snapshot=h.model.snapshot;
 h.doc.querySelector('[data-native-back]').dispatchEvent(new h.w.Event('click'));h.doc.querySelector('[data-native-next]').dispatchEvent(new h.w.Event('click'));assert.equal(h.model.step,6);assert.equal(h.model.snapshot,snapshot);h.close();
});
for(const [code,status,retryable] of [['media_upload_incomplete',409,true],['media_commit_in_progress',409,true],['media_binding_failed',500,true],['media_binding_invariant_failed',500,false],['media_service_unavailable',503,true],['media_rate_limited',429,true],['media_manifest_conflict',409,false],['media_session_expired',410,false]])test('ambiguous/post-pin code never unlocks manifest: '+code,async()=>{
 const h=create({fetch(url){if(url.endsWith('/reports'))return {ok:false,status,json:async()=>({status:'error',error:{code,retryable,field:'media.items'}})};}});
 h.fill();h.set('media.availability','yes');h.select();await tick();h.goReview();h.submit();h.submit();await tick();const frozen=h.model.snapshot,body=h.requests.find(r=>r.url.endsWith('/reports')).request.body;
 assert(frozen && Object.isFrozen(frozen.media.items));assert.equal(h.model.step,6);assert(!h.requests.some(r=>r.request.method==='GET'));assert(h.doc.querySelector('input[type=file]').disabled);
 h.select();h.doc.querySelector('[data-media-action=remove]').click();assert.equal(h.model.snapshot,frozen);assert.equal(h.uploads.length,1);
 if(!h.model.blocked){h.submit();await tick();assert(h.requests.filter(r=>r.url.endsWith('/reports')).every(r=>r.request.body===body));}h.close();
});
test('teardown during owner status cannot unlock snapshot; BFCache preserves state until actual exit',async()=>{
 let done;const h=create({fetch(url,request){if(url.endsWith('/reports'))return {ok:false,status:422,json:async()=>({status:'error',error:{code:'media_manifest_invalid',retryable:false}})};if(request.method==='GET')return new Promise(r=>{done=()=>r({ok:true,json:async()=>({status:'success',state:'open'})});});}});
 h.fill();h.set('media.availability','yes');h.select();await tick();h.w.dispatchEvent(new h.w.PageTransitionEvent('pagehide',{persisted:true}));assert.equal(h.revoked.length,0);h.goReview();h.submit();await tick();const frozen=h.model.snapshot;
 h.w.dispatchEvent(new h.w.PageTransitionEvent('pagehide',{persisted:false}));done();await tick();assert.equal(h.model.snapshot,frozen);assert.equal(h.revoked.length,1);assert(h.doc.querySelector('[data-native-success]').hidden);h.close();
});
test('hostile filename/error body has no executable DOM, bearer or raw original representation',async()=>{
 const h=create({upload(xhr){queueMicrotask(()=>{xhr.status=503;xhr.responseURL=xhr.url;xhr.responseText=JSON.stringify({status:'error',error:{code:'media_service_unavailable',message:cap+' /private/secret',stack:'raw'},capability:cap,original_url:'https://invalid.test/raw'});xhr.onload();});}});
 h.fill();h.set('media.availability','yes');const picker=h.doc.querySelector('input[type=file]');Object.defineProperty(picker,'files',{value:[new h.w.File([new Uint8Array(70)],'<img src=x onerror=alert(1)>.png',{type:'image/png'})]});picker.dispatchEvent(new h.w.Event('change',{bubbles:true}));await tick();
 assert(h.doc.querySelector('[data-media-name]').textContent.includes('<img'));assert.equal(h.doc.querySelectorAll('[onerror]').length,0);assert(!h.root.innerHTML.includes(cap));assert(!h.root.textContent.includes('/private/secret'));assert(!h.root.innerHTML.includes('https://invalid.test/raw'));assert.equal(h.w.localStorage.length,0);assert.equal(h.w.sessionStorage.length,0);h.close();
});
test('five-card mobile/keyboard structure at 390x844 and 360x800',async()=>{
 for(const [width,height] of [[390,844],[360,800]]){
  const h=create();Object.defineProperty(h.w,'innerWidth',{value:width});Object.defineProperty(h.w,'innerHeight',{value:height});h.fill();h.set('media.availability','yes');h.select(5);await tick();for(let i=0;i<4;i++)h.click('next');
  const cards=h.doc.querySelectorAll('[data-media-list] li');assert.equal(cards.length,5);
  for(const card of cards){const remove=card.querySelector('[data-media-action=remove]');remove.focus();assert.equal(h.doc.activeElement,remove);assert.equal(remove.type,'button');assert.equal(card.querySelectorAll('img').length,1);assert(card.querySelector('progress').getAttribute('aria-label'));assert(remove.getAttribute('aria-describedby'));}
  h.click('next');h.click('back');assert.equal(h.doc.activeElement.tagName,'H2');assert.equal(h.doc.querySelectorAll('[data-media-list] li').length,5);h.close();
 }
});
test('expired upload session remains explicit and blocks final without recreating a session',async()=>{
 const h=create({upload(xhr){queueMicrotask(()=>xhr.finish('media_session_expired'));}});h.fill();h.set('media.availability','yes');h.select();await tick();
 assert(h.doc.querySelector('[data-media-error]').textContent.includes('scaduta'));h.doc.querySelector('[data-media-action=retry]').click();await tick();assert.equal(h.requests.filter(r=>r.url.endsWith('/report-media-sessions')).length,1);assert.equal(h.uploads[0].key,h.uploads[1].key);
 h.goReview();h.submit();assert.equal(h.model.snapshot,null);assert.equal(h.model.step,4);assert(!h.requests.some(r=>r.url.endsWith('/reports')));h.close();
});
