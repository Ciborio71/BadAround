'use strict';
const {test}=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const {execFileSync}=require('node:child_process'),{JSDOM}=require('jsdom');
const html=execFileSync(process.env.BA_PHP || 'php',[path.join(__dirname,'native-report-f16.php'),'--html'],{encoding:'utf8'});
const base=path.join(__dirname,'../wordpress/themes/badaround-child/assets/js/native-report');
const cap='A'.repeat(43),sid='11111111-1111-4111-8111-111111111111',mediaId='22222222-2222-4222-8222-222222222222';
const data={'event.category':'hazard','event.subtype':'hazard_road_obstruction','reporter.relationship':'territory_observer','location.region':'Lazio','location.province':'Roma','location.municipality':'Pomezia','location.locality':'Torvaianica','location.exact_address':'Via Test 1, Pomezia','location.place_type':'road_sidewalk','location.immediate_danger':'no','time.mode':'exact','time.date':'2026-10-01','time.knowledge':'unknown','content.description':'Segnalazione sintetica F1.6.','damage.status':'no','authority.status':'no','media.availability':'no','reporter.first_name':'QA','reporter.last_name':'Synthetic','reporter.email':'qa@example.invalid','reporter.contact_preference':'none','consents.truthfulness':true,'consents.media_rights':true,'consents.publication_rules':true,'consents.terms':true,'consents.privacy':true};
const tick=()=>new Promise(r=>setImmediate(r));
function create(options={}) {
 const dom=new JSDOM(html,{url:'https://staging.badaround.it/segnala-un-evento/?native_report=1',runScripts:'outside-only'}),w=dom.window,requests=[],uploads=[],revoked=[];
 w.AbortController=AbortController;w.URL.createObjectURL=()=>`blob:https://staging.badaround.it/${globalThis.crypto.randomUUID()}`;w.URL.revokeObjectURL=u=>revoked.push(u);
 let uploadNumber=0;
 class XHR {
  constructor(){this.upload={};this.headers={};}
  open(method,url){this.method=method;this.url=url;}
  setRequestHeader(n,v){this.headers[n]=v;}
  send(form){this.file=form.get('file');this.key=form.get('client_upload_id');uploads.push(this);const n=++uploadNumber;
   this.finish=(code=null)=>{this.status=code?503:201;this.responseURL=this.url;this.responseText=JSON.stringify(code?{status:'error',error:{code}}:{status:'success',state:'accepted_quarantined',duplicate:options.duplicate===true,descriptor:{media_id:options.duplicate?mediaId:globalThis.crypto.randomUUID(),mime_type:this.file.type,file_size:this.file.size,extension:'png'}});this.onload();};
   if(options.upload)options.upload(this,n);else queueMicrotask(()=>this.finish());
  }
 }
 w.XMLHttpRequest=XHR;
 w.fetch=async(url,request)=>{
  requests.push({url,request});const body=request.body?JSON.parse(request.body):null;
  if(options.fetch){const response=await options.fetch(url,request,body,requests);if(response)return response;}
  const response=request.method==='DELETE'?{status:'success',state:'removed',media_id:url.split('/').pop()}:request.method==='GET'?{status:'success',state:options.sessionState || 'open',items:[]}:url.endsWith('/report-media-sessions')?{status:'success',media_session_id:sid,capability:cap,upload_expires_at:2000000000,commit_expires_at:2000007200,limits:{max_items:5,max_bytes_per_item:5242880},supported_codecs:['jpeg','png','webp']}:{status:'success',schema_version:body.schema_version,submission_id:body.submission_id,next_state:'moderation_pending',duplicate:options.completedDuplicate===true};
  return {ok:true,status:200,json:async()=>response};
 };
 for(const p of options.frozen?['model','api','errors','wizard']:['model','api','errors','media','media-view','wizard']){
  const source=options.frozen?execFileSync('git',['show',`bcc2c74ca3391f1d16f9b1eb9719650ea4d27952:wordpress/themes/badaround-child/assets/js/native-report/${p}.js`],{encoding:'utf8'}):fs.readFileSync(path.join(base,p+'.js'),'utf8');w.eval(source);
 }
 const doc=w.document,root=doc.querySelector('[data-native-report]'),model=root.nativeReport.model;
 const set=(key,value,event='input')=>{const input=doc.querySelector(`[name="${key}"]`);if(input.type==='checkbox')input.checked=value;else input.value=value;input.dispatchEvent(new w.Event(event,{bubbles:true}));};
 const select=(number=1)=>{const picker=doc.querySelector('input[type=file]');Object.defineProperty(picker,'files',{configurable:true,value:Array.from({length:number},(_,i)=>new w.File([new Uint8Array(70)],`synthetic-${i}.png`,{type:'image/png'}))});picker.dispatchEvent(new w.Event('change',{bubbles:true}));};
 const click=key=>doc.querySelector(`[data-native-${key}]`).click();
 const submit=()=>doc.querySelector('form').dispatchEvent(new w.Event('submit',{bubbles:true,cancelable:true}));
 const fill=()=>Object.entries(data).forEach(([k,v])=>set(k,v));
 const goReview=()=>{while(model.step<6){const previous=model.step;click('next');assert(model.step>previous);}assert.equal(model.step,6);};
 const close=()=>{root.nativeReport.teardown?.();dom.window.close();};
 return {w,dom,doc,root,model,requests,uploads,revoked,set,select,click,submit,fill,goReview,close};
}

module.exports={create,tick,cap,sid,mediaId};
