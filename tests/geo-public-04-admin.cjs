'use strict';
const {test}=require('node:test');const assert=require('node:assert/strict');
const {execFileSync}=require('node:child_process');const {JSDOM}=require('jsdom');
function fixture(approx=false){return new JSDOM(execFileSync(process.env.BA_PHP||'php',['tests/geo-public-04-moderation.php',approx?'--html-approx':'--html'],{encoding:'utf8'}),{runScripts:'dangerously'}).window;}
test('Classic Editor, rejection and geographic decision have independent forms',()=>{
 const w=fixture(),d=w.document,post=d.getElementById('post'),geo=d.getElementById('ba-geo-decision-form'),reject=d.getElementById('ba-reject-event-form');
 assert(post&&geo&&reject);assert(!post.contains(geo));assert(!post.contains(reject));
 assert.equal(d.getElementById('ba-geo-mode').form,geo);
 assert(!Array.from(post.elements).some(e=>e.id==='ba-geo-mode'));
 assert(post.checkValidity());assert(!geo.checkValidity());
 w.close();
});
test('explicit choice reveals only required reason/confirmation and never pre-approves',()=>{
 const w=fixture(),d=w.document,s=d.getElementById('ba-geo-mode'),r=d.getElementById('ba-geo-reason'),c=d.getElementById('ba-geo-exact-confirm'),form=s.form;
 assert.equal(s.value,'');assert(d.getElementById('ba-geo-exact-wrap').hidden);assert(d.getElementById('ba-geo-reason-wrap').hidden);
 s.value='exact_point';s.dispatchEvent(new w.Event('change'));assert(c.required&&!c.disabled);assert(!form.checkValidity());c.checked=true;assert(form.checkValidity());
 s.value='approximate_area';s.dispatchEvent(new w.Event('change'));assert(!c.checked&&c.disabled);assert(r.required&&!r.disabled);assert(!form.checkValidity());r.value='privacy';assert(form.checkValidity());
 s.value='territory_only';s.dispatchEvent(new w.Event('change'));assert(r.required);assert(d.getElementById('post').checkValidity());w.close();
});
test('approximate request has no exact/street option and municipality requires reason',()=>{
 const w=fixture(true),d=w.document,s=d.getElementById('ba-geo-mode'),r=d.getElementById('ba-geo-reason');
 assert.deepEqual(Array.from(s.options).map(x=>x.value),['','approximate_area','municipality','territory_only']);
 s.value='approximate_area';s.dispatchEvent(new w.Event('change'));assert(!r.required&&r.disabled);
 s.value='municipality';s.dispatchEvent(new w.Event('change'));assert(r.required&&!r.disabled);w.close();
});
